<?php

namespace App\Console\Commands;

use App\Models\Dispenser;
use App\Models\DoseLog;
use App\Models\TherapyPlan;
use App\Models\User;
use App\Services\DeviceEventIngestionService;
use App\Services\MqttPublisher;
use App\UserRole;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
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
    protected $signature = 'device:mqtt-listen
                            {--topic-root= : Root topic MQTT (default da MQTT_TOPIC_ROOT)}
                            {--login-topic=esp32/login_request : Topic su cui ascoltare le richieste di login mobile}
                            {--schedule-topic=esp32/schedule_request : Topic su cui ascoltare le richieste di disposizione settimanale medicine}
                            {--max-seconds=0 : Arresta il listener dopo N secondi (0 = infinito)}';

    protected $description = 'Ascolta eventi MQTT dei dispenser e li salva nel database applicativo';

    public function __construct(
        private readonly DeviceEventIngestionService $deviceEventIngestionService,
        private readonly MqttPublisher $mqttPublisher,
    ) {
        parent::__construct();
    }

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
        $doseLogSuffix   = trim((string) config('services.mqtt.topic_dose_log_suffix', 'events/dose-log'), '/');
        $statusSuffix    = trim((string) config('services.mqtt.topic_status_suffix', 'status'), '/');
        $loginTopic      = (string) $this->option('login-topic');
        $scheduleTopic   = (string) $this->option('schedule-topic');

        $port         = (int)  config('services.mqtt.port', 1883);
        $clientId     = (string) config('services.mqtt.client_id', 'smart-dispenser-web');
        $cleanSession = (bool) config('services.mqtt.clean_session', true);
        $username     = config('services.mqtt.username');
        $password     = config('services.mqtt.password');
        $useTls       = (bool) config('services.mqtt.use_tls', false);
        $maxSeconds   = max(0, (int) $this->option('max-seconds'));

        $mqtt = new MqttClient($host, $port, $clientId.'-listener-'.Str::lower(Str::random(8)));

        $connectionSettings = (new ConnectionSettings)
            ->setUsername($username)
            ->setPassword($password)
            ->setUseTls($useTls);

        try {
            $mqtt->connect($connectionSettings, $cleanSession);
            $this->info('Connesso al broker MQTT '.$host.':'.$port);

            $this->subscribe($mqtt, $topicRoot.'/+/'.$telemetrySuffix, function (string $topic, string $message) use ($telemetrySuffix): void {
                $this->ingestTelemetryMessage($topic, $message, $telemetrySuffix);
            });

            $this->subscribe($mqtt, $topicRoot.'/+/'.$doseLogSuffix, function (string $topic, string $message) use ($doseLogSuffix): void {
                $this->ingestDoseLogMessage($topic, $message, $doseLogSuffix);
            });

            $this->subscribe($mqtt, $topicRoot.'/+/'.$statusSuffix, function (string $topic, string $message) use ($statusSuffix): void {
                $this->ingestStatusMessage($topic, $message, $statusSuffix);
            });

            $this->subscribe($mqtt, $loginTopic, function (string $topic, string $message): void {
                $this->handleMobileLoginRequest($topic, $message);
            });

            $this->subscribe($mqtt, $scheduleTopic, function (string $topic, string $message): void {
                $this->handleWeeklyScheduleRequest($topic, $message);
            });

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

    // =========================================================================
    // Disposizione 7 giorni di medicine via MQTT (tecnica reply_to)
    // =========================================================================

    /**
     * Gestisce una richiesta di disposizione medicine per i prossimi 7 giorni.
     *
     * Payload atteso:
     *   {
     *     "device_uid": "ESP32-PILL-001",
     *     "reply_to":   "esp32/schedule_response/ESP32-PILL-001"
     *   }
     *
     * Risposta pubblicata sul topic reply_to:
     *   {
     *     "success": true,
     *     "patient_id": 13,
     *     "days": [
     *       {
     *         "date": "2026-04-23",
     *         "day_name": "Wednesday",
     *         "prescriptions": [
     *           {
     *             "therapy_plan_id": 7,
     *             "medicine_name":   "Aspirina",
     *             "dose_amount":     "1.00",
     *             "dose_unit":       "compressa",
     *             "time":            "08:00:00",
     *             "instructions":    "Da prendere a stomaco pieno."
     *           }
     *         ]
     *       },
     *       ...  (7 giorni totali, da oggi a oggi+6)
     *     ],
     *     "replied_at": "2026-04-23T10:00:00+00:00"
     *   }
     *
     * Logica week_days:
     *   - array vuoto []  → il farmaco va preso OGNI giorno
     *   - [1,2,3,4,5,6,7] → ISO weekday: 1=Lunedì … 7=Domenica
     *
     * Risposta in caso di errore:
     *   { "success": false, "error": "...", "replied_at": "..." }
     */
    private function handleWeeklyScheduleRequest(string $topic, string $message): void
    {
        $this->line('[SCHEDULE] Richiesta esplicita ricevuta su '.$topic);

        $payload = $this->decodePayload($message, $topic);
        if ($payload === null) {
            return;
        }

        $validator = Validator::make($payload, [
            'device_uid' => ['required', 'string', 'max:255'],
            'reply_to'   => ['nullable', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            $this->warn('[SCHEDULE] Payload non valido: '.$validator->errors()->first());
            return;
        }

        $validated = $validator->validated();
        $deviceUid = (string) $validated['device_uid'];

        /** @var Dispenser|null $dispenser */
        $dispenser = Dispenser::query()
            ->where('device_uid', $deviceUid)
            ->where('is_active', true)
            ->first();

        if ($dispenser === null) {
            $this->warn('[SCHEDULE] Nessun dispenser attivo per device_uid: '.$deviceUid);
            return;
        }

        // Usa sempre il topic standard; reply_to è opzionale e ignorato
        $this->publishWeekSchedule($dispenser);
    }

    // =========================================================================
    // Login mobile via MQTT (tecnica reply_to)
    // =========================================================================

    private function handleMobileLoginRequest(string $topic, string $message): void
    {
        $this->line('[LOGIN] Richiesta ricevuta su '.$topic);

        $payload = $this->decodePayload($message, $topic);
        if ($payload === null) {
            return;
        }

        $validator = Validator::make($payload, [
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'reply_to' => ['required', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            $this->warn('[LOGIN] Payload non valido: '.$validator->errors()->first());
            return;
        }

        $validated = $validator->validated();
        $replyTo   = (string) $validated['reply_to'];

        /** @var User|null $user */
        $user = User::query()
            ->where('email', $validated['username'])
            ->orWhere('name', $validated['username'])
            ->first();

        if ($user === null || ! Hash::check($validated['password'], $user->password)) {
            $this->warn('[LOGIN] Autenticazione fallita per: '.$validated['username']);
            $this->mqttPublisher->publishTo($replyTo, [
                'success'    => false,
                'error'      => 'Credenziali non valide.',
                'replied_at' => now()->toIso8601String(),
            ]);
            return;
        }

        if (! $user->is_active) {
            $this->warn('[LOGIN] Account disattivato: '.$user->email);
            $this->mqttPublisher->publishTo($replyTo, [
                'success'    => false,
                'error'      => 'Account disattivato. Contatta il tuo medico.',
                'replied_at' => now()->toIso8601String(),
            ]);
            return;
        }

        $user->update(['last_login_at' => now()]);

        $dispenserUid = null;
        if ($user->hasRole(UserRole::Patient)) {
            $dispenser    = Dispenser::query()->where('patient_id', $user->id)->where('is_active', true)->first();
            $dispenserUid = $dispenser?->device_uid;
        }

        $this->info('[LOGIN] Autenticazione riuscita per: '.$user->email);

        $this->mqttPublisher->publishTo($replyTo, [
            'success'       => true,
            'user_id'       => $user->id,
            'name'          => $user->name,
            'email'         => $user->email,
            'role'          => $user->role?->value,
            'dispenser_uid' => $dispenserUid,
            'replied_at'    => now()->toIso8601String(),
        ]);
    }

    // =========================================================================
    // Ingest dispenser events
    // =========================================================================

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
            'humidity'    => ['required', 'numeric', 'between:0,100'],
            'recorded_at' => ['nullable', 'date'],
        ]);

        if ($validator->fails()) {
            $this->warn('Telemetria non valida su '.$topic.': '.$validator->errors()->first());
            return;
        }

        $result = $this->deviceEventIngestionService->ingestTelemetry(
            dispenser: $dispenser,
            payload: $validator->validated(),
        );

        if ($result['sensor_log'] !== null) {
            $this->line('Telemetria acquisita da '.$dispenser->device_uid);
        } else {
            $this->line('Telemetria ricevuta da '.$dispenser->device_uid.' (throttle: già salvato in questa ora)');
        }
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
            'medicine_id'     => ['nullable', 'integer', 'exists:medicines,id'],
            'status'          => [
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
            'event_at'      => ['nullable', 'date'],
            'notes'         => ['nullable', 'string', 'max:1000'],
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
            'is_online'    => ['nullable', 'boolean'],
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

        // Risponde automaticamente con la schedule settimanale (solo date e orari)
        $this->publishWeekSchedule($dispenser);
    }

    // =========================================================================
    // Schedule settimanale (solo date + orari)
    // =========================================================================

    /**
     * Pubblica la schedule settimanale pulita (solo date e orari) sul topic
     * esp32/schedule_response/{device_uid}.
     * Chiamato sia dallo status trigger sia dalla richiesta esplicita.
     */
    private function publishWeekSchedule(Dispenser $dispenser): void
    {
        $replyTopic = 'esp32/schedule_response/'.$dispenser->device_uid;
        $days       = $this->buildSimpleWeekSchedule($dispenser);

        $this->mqttPublisher->publishTo($replyTopic, ['days' => $days]);

        $this->info(sprintf(
            '[SCHEDULE] Schedule inviata a %s (%d slot nei prossimi 7 giorni)',
            $dispenser->device_uid,
            count($days),
        ));
    }

    /**
     * Costruisce l'array piatto dei prossimi 7 giorni.
     * Ogni elemento contiene SOLO "date" (YYYY-MM-DD) e "time" (HH:MM).
     * Nessun altro campo.
     *
     * @return list<array{date: string, time: string}>
     */
    private function buildSimpleWeekSchedule(Dispenser $dispenser): array
    {
        $plans = TherapyPlan::query()
            ->with('schedules')
            ->where('patient_id', $dispenser->patient_id)
            ->where('is_active', true)
            ->get();

        $today = Carbon::today();
        $slots = [];

        for ($offset = 0; $offset < 7; $offset++) {
            $day        = $today->copy()->addDays($offset);
            $dateKey    = $day->toDateString();
            $isoWeekday = $day->isoWeekday(); // 1=Lun … 7=Dom

            foreach ($plans as $plan) {
                // Piano non ancora iniziato o già terminato in questa data
                if ($plan->starts_on->greaterThan($day)) {
                    continue;
                }
                if ($plan->ends_on !== null && $plan->ends_on->lessThan($day)) {
                    continue;
                }

                foreach ($plan->schedules as $schedule) {
                    $weekDays    = $schedule->week_days ?? [];
                    $activeToday = empty($weekDays) || in_array($isoWeekday, $weekDays, true);

                    if (! $activeToday) {
                        continue;
                    }

                    $slots[] = [
                        'date' => $dateKey,
                        'time' => substr((string) $schedule->scheduled_time, 0, 5), // "HH:MM"
                    ];
                }
            }
        }

        // Ordina per data, poi per orario crescente all'interno dello stesso giorno
        usort($slots, static fn (array $a, array $b): int =>
            $a['date'] === $b['date']
                ? strcmp($a['time'], $b['time'])
                : strcmp($a['date'], $b['date'])
        );

        return $slots;
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /** @param callable(string, string):void $handler */
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

    /** @param array<string, mixed> $payload @return array<string, mixed> */
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

    /** @return array<string, mixed>|null */
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

        $baseTopic        = Str::beforeLast($topic, $suffixPath);
        $deviceIdentifier = Str::afterLast($baseTopic, '/');

        return Dispenser::query()
            ->where('mqtt_base_topic', $baseTopic)
            ->orWhere('device_uid', $deviceIdentifier)
            ->first();
    }
}
