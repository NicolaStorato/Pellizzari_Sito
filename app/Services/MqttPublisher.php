<?php

namespace App\Services;

use App\Models\Dispenser;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Exceptions\MqttClientException;
use PhpMqtt\Client\MqttClient;

class MqttPublisher
{
    /**
     * Pubblica un comando strutturato sul topic comandi univoco del dispenser.
     * Tutti i comandi convergono su <topicBase>/commands.
     * Il campo "command" nel payload JSON identifica il tipo di operazione.
     *
     * @param  array<string, mixed>  $payload
     */
    public function publishCommand(Dispenser $dispenser, string $command, array $payload = []): bool
    {
        $topicRoot = trim((string) config('services.mqtt.topic_root', 'smart-dispenser'), '/');
        $topicBase = $dispenser->mqtt_base_topic ?: $topicRoot.'/'.$dispenser->device_uid;
        $topic = $topicBase.'/commands';

        return $this->publishRaw($topic, json_encode([
            'command' => $command,
            'issued_at' => now()->toIso8601String(),
            'payload' => $payload,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * Pubblica un payload JSON grezzo su qualsiasi topic arbitrario.
     * Usato ad esempio per rispondere al topic reply_to delle richieste di login mobile.
     *
     * @param  array<string, mixed>  $payload
     */
    public function publishTo(string $topic, array $payload): bool
    {
        try {
            return $this->publishRaw($topic, json_encode($payload, JSON_THROW_ON_ERROR));
        } catch (\JsonException) {
            return false;
        }
    }

    /**
     * Apre una connessione, pubblica il messaggio e si disconnette.
     */
    private function publishRaw(string $topic, string $message): bool
    {
        $host = (string) config('services.mqtt.host', '');

        if ($host === '') {
            return false;
        }

        $port = (int) config('services.mqtt.port', 1883);
        $clientId = (string) config('services.mqtt.client_id', 'smart-dispenser-web');
        $cleanSession = (bool) config('services.mqtt.clean_session', true);
        $username = config('services.mqtt.username');
        $password = config('services.mqtt.password');
        $useTls = (bool) config('services.mqtt.use_tls', false);

        $mqtt = new MqttClient($host, $port, $clientId.'-'.str()->random(8));

        $connectionSettings = (new ConnectionSettings)
            ->setUsername($username)
            ->setPassword($password)
            ->setUseTls($useTls);

        try {
            $mqtt->connect($connectionSettings, $cleanSession);
            $mqtt->publish($topic, $message, 0);
            $mqtt->disconnect();

            return true;
        } catch (MqttClientException|\JsonException) {
            return false;
        }
    }
}
