<?php

namespace App\Console\Commands;

use App\Models\Dispenser;
use App\Models\DoseLog;
use App\Services\DeviceEventIngestionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use JsonException;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Exceptions\MqttClientException;
use PhpMqtt\Client\MqttClient;
use Throwable;

class DeviceMqttListenCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'device:mqtt-listen
                            {--topic-root= : Root topic MQTT (default da MQTT_TOPIC_ROOT)}
                            {--max-seconds=0 : Arresta il listener dopo N secondi (0 = infinito)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ascolta eventi MQTT dei dispenser e li salva nel database applicativo';

    public function __construct(private readonly DeviceEventIngestionService $deviceEventIngestionService)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $host = (string) config('services.mqtt.host', '');

        if ($host === '') {
            $this->error('MQTT_HOST non configurato. Imposta il broker prima di avviare il listener.');

            return self::FAILURE;
        }

        $topicRoot = trim((string) ($this->option('topic-root') ?: config('services.mqtt.topic_root', 'smart-dispenser')), '/');

        if ($topicRoot === '') {
            $this->error('Topic root MQTT non valido.');

            return self::FAILURE;
        }

        $telemetrySuffix = trim((string) config('services.mqtt.topic_telemetry_suffix', 'events/telemetry'), '/');
        $doseLogSuffix = trim((string) config('services.mqtt.topic_dose_log_suffix', 'events/dose-log'), '/');
        $statusSuffix = trim((string) config('services.mqtt.topic_status_suffix', 'status'), '/');
        $port = (int) config('services.mqtt.port', 1883);
        $clientId = (string) config('services.mqtt.client_id', 'smart-dispenser-web');
        $cleanSession = (bool) config('services.mqtt.clean_session', true);
        $username = config('services.mqtt.username');
        $password = config('services.mqtt.password');
        $useTls = (bool) config('services.mqtt.use_tls', false);
        $maxSeconds = max(0, (int) $this->option('max-seconds'));

        $mqtt = new MqttClient($host, $port, $clientId.'-listener-'.Str::lower(Str::random(8)));

        $connectionSettings = (new ConnectionSettings)
            ->setUsername($username)
            ->setPassword($password)
            ->setUseTls($useTls);

        try {
            $mqtt->connect($connectionSettings, $cleanSession);
            $this->info('Connesso al broker MQTT '.$host.':'.$port);

            $this->subscribe(
                mqtt: $mqtt,
                topicFilter: $topicRoot.'/+/'.$telemetrySuffix,
                handler: function (string $topic, string $message) use ($telemetrySuffix): void {
                    $this->ingestTelemetryMessage($topic, $message, $telemetrySuffix);
                },
            );

            $this->subscribe(
                mqtt: $mqtt,
                topicFilter: $topicRoot.'/+/'.$doseLogSuffix,
                handler: function (string $topic, string $message) use ($doseLogSuffix): void {
                    $this->ingestDoseLogMessage($topic, $message, $doseLogSuffix);
                },
            );

            $this->subscribe(
                mqtt: $mqtt,
                topicFilter: $topicRoot.'/+/'.$statusSuffix,
                handler: function (string $topic, string $message) use ($statusSuffix): void {
                    $this->ingestStatusMessage($topic, $message, $statusSuffix);
                },
            );

            if ($maxSeconds > 0) {
                $mqtt->registerLoopEventHandler(function (MqttClient $client, float $elapsedTime) use ($maxSeconds): void {
                    if ($elapsedTime >= $maxSeconds) {
                        $this->info('Timeout raggiunto, listener MQTT fermato.');
                        $client->interrupt();
                    }
                });
            }

            $this->info('Listener MQTT avviato. Premi CTRL+C per interrompere.');
            $mqtt->loop(true);

            return self::SUCCESS;
        } catch (MqttClientException|Throwable $exception) {
            $this->error('Errore listener MQTT: '.$exception->getMessage());

            return self::FAILURE;
        } finally {
            try {
                if ($mqtt->isConnected()) {
                    $mqtt->disconnect();
                }
            } catch (MqttClientException) {
                // Ignora eventuali errori di disconnessione.
            }
        }
    }

    /**
     * @param  callable(string, string):void  $handler
     */
    private function subscribe(MqttClient $mqtt, string $topicFilter, callable $handler): void
    {
        $mqtt->subscribe(
            $topicFilter,
            static function (string $topic, string $message, bool $retained, ?array $matchedWildcards) use ($handler): void {
                $handler($topic, $message);
            },
            0,
        );

        $this->line('Sottoscritto topic: '.$topicFilter);
    }

    private function ingestTelemetryMessage(string $topic, string $message, string $telemetrySuffix): void
    {
        $dispenser = $this->resolveDispenserFromTopic($topic, $telemetrySuffix);

        if ($dispenser === null) {
            $this->warn('Telemetria ignorata: nessun dispenser trovato per topic '.$topic);

            return;
        }

        $payload = $this->decodePayload($message, $topic);

        if ($payload === null) {
            return;
        }

        $payload = $this->normalizeTelemetryPayload($payload);

        $validator = Validator::make($payload, [
            'temperature' => ['required', 'numeric', 'between:-40,120'],
            'humidity' => ['required', 'numeric', 'between:0,100'],
            'recorded_at' => ['nullable', 'date'],
        ]);

        if ($validator->fails()) {
            $this->warn('Telemetria non valida su '.$topic.': '.$validator->errors()->first());

            return;
        }

        $this->deviceEventIngestionService->ingestTelemetry(
            dispenser: $dispenser,
            payload: $validator->validated(),
        );

        $this->line('Telemetria acquisita da '.$dispenser->device_uid);
    }

    private function ingestDoseLogMessage(string $topic, string $message, string $doseLogSuffix): void
    {
        $dispenser = $this->resolveDispenserFromTopic($topic, $doseLogSuffix);

        if ($dispenser === null) {
            $this->warn('Dose-log ignorato: nessun dispenser trovato per topic '.$topic);

            return;
        }

        $payload = $this->decodePayload($message, $topic);

        if ($payload === null) {
            return;
        }

        $validator = Validator::make($payload, [
            'therapy_plan_id' => ['nullable', 'integer', 'exists:therapy_plans,id'],
            'medicine_id' => ['nullable', 'integer', 'exists:medicines,id'],
            'status' => [
                'required',
                Rule::in([
                    DoseLog::STATUS_PENDING,
                    DoseLog::STATUS_DISPENSED,
                    DoseLog::STATUS_TAKEN,
                    DoseLog::STATUS_MISSED,
                    DoseLog::STATUS_SNOOZED,
                    DoseLog::STATUS_SKIPPED,
                ]),
            ],
            'scheduled_for' => ['nullable', 'date'],
            'event_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            $this->warn('Dose-log non valido su '.$topic.': '.$validator->errors()->first());

            return;
        }

        $this->deviceEventIngestionService->ingestDoseLog(
            dispenser: $dispenser,
            payload: $validator->validated(),
        );

        $this->line('Dose-log acquisito da '.$dispenser->device_uid);
    }

    private function ingestStatusMessage(string $topic, string $message, string $statusSuffix): void
    {
        $dispenser = $this->resolveDispenserFromTopic($topic, $statusSuffix);

        if ($dispenser === null) {
            $this->warn('Status ignorato: nessun dispenser trovato per topic '.$topic);

            return;
        }

        $payload = $this->decodePayload($message, $topic);

        if ($payload === null) {
            return;
        }

        $validator = Validator::make($payload, [
            'is_online' => ['nullable', 'boolean'],
            'last_seen_at' => ['nullable', 'date'],
        ]);

        if ($validator->fails()) {
            $this->warn('Status non valido su '.$topic.': '.$validator->errors()->first());

            return;
        }

        $this->deviceEventIngestionService->ingestStatus(
            dispenser: $dispenser,
            payload: $validator->validated(),
        );

        $this->line('Status aggiornato per '.$dispenser->device_uid);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeTelemetryPayload(array $payload): array
    {
        if (! array_key_exists('temperature', $payload) && array_key_exists('temperatura', $payload)) {
            $payload['temperature'] = $payload['temperatura'];
        }

        if (! array_key_exists('humidity', $payload) && array_key_exists('umidita', $payload)) {
            $payload['humidity'] = $payload['umidita'];
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodePayload(string $message, string $topic): ?array
    {
        try {
            $payload = json_decode($message, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->warn('Payload JSON non valido su '.$topic);

            return null;
        }

        if (! is_array($payload)) {
            $this->warn('Payload non valido su '.$topic.': atteso oggetto JSON');

            return null;
        }

        return $payload;
    }

    private function resolveDispenserFromTopic(string $topic, string $suffix): ?Dispenser
    {
        $suffixPath = '/'.trim($suffix, '/');

        if (! Str::endsWith($topic, $suffixPath)) {
            return null;
        }

        $baseTopic = Str::beforeLast($topic, $suffixPath);
        $deviceIdentifier = Str::afterLast($baseTopic, '/');

        return Dispenser::query()
            ->where('mqtt_base_topic', $baseTopic)
            ->orWhere('device_uid', $deviceIdentifier)
            ->first();
    }
}
