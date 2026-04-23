# QUICK START: MQTT in 5 Minuti

**Data:** 23-04-2026

Questa è una guida rapida e semplice per far funzionare la comunicazione MQTT tra Laravel, ESP32 e HiveMQ.

---

## Step 1: Configurare il file .env (2 minuti)

Il tuo `.env` ha **già le configurazioni MQTT**. Verifica che ci siano:

```env
MQTT_HOST=4a9747976f4d480f8994db0b416ae029.s1.eu.hivemq.cloud
MQTT_PORT=8883
MQTT_USERNAME=Utente
MQTT_PASSWORD=Utente1!
MQTT_USE_TLS=true

MQTT_TOPIC_ROOT=smart-dispenser
MQTT_TOPIC_TELEMETRY_SUFFIX=events/telemetry
MQTT_TOPIC_DOSE_LOG_SUFFIX=events/dose-log
MQTT_TOPIC_STATUS_SUFFIX=status
```

✅ **È già configurato!** Non devi fare nulla.

---

## Step 2: Avviare il Listener (1 minuto)

Il listener è il servizio che **ascolta i messaggi da ESP32 e li salva nel database**.

Apri un terminale e esegui:

```bash
php artisan device:mqtt-listen
```

**Output atteso:**

```
Connesso al broker MQTT 4a9747976f4d480f8994db0b416ae029.s1.eu.hivemq.cloud:8883
Sottoscritto topic: smart-dispenser/+/events/telemetry
Sottoscritto topic: smart-dispenser/+/events/dose-log
Sottoscritto topic: smart-dispenser/+/status
Listener MQTT avviato. Premi CTRL+C per interrompere.
Listening...
```

**Con timeout di 5 minuti:**

```bash
php artisan device:mqtt-listen --max-seconds=300
```

---

## Step 3: Testare la Comunicazione (1 minuto)

### Opzione A: Dal Web Client HiveMQ

1. Apri: http://localhost:8884 (se HiveMQ locale)
2. Oppure accedi al cloud HiveMQ con le tue credenziali
3. Sottoscrivi a: `#` (per ricevere tutto)
4. Pubblica un messaggio di test:

**Topic:**
```
smart-dispenser/device-001/events/telemetry
```

**Payload:**
```json
{
  "temperature": 24.5,
  "humidity": 50.2,
  "recorded_at": "2026-04-23T16:35:00+02:00"
}
```

5. **Nel listener dovresti vedere:**
```
[2026-04-23 16:35:00] Message received on smart-dispenser/device-001/events/telemetry
Telemetria acquisita da device-001
```

6. **Nel database (verifica in phpMyAdmin):**
```sql
SELECT * FROM sensor_logs WHERE dispenser_id = 1 ORDER BY created_at DESC LIMIT 1;
```

### Opzione B: Da Laravel Tinker

Apri un altro terminale:

```bash
php artisan tinker

# Pubblica un comando al dispositivo
$dispenser = Dispenser::find(1);
$publisher = new \App\Services\MqttPublisher();
$publisher->publishCommand($dispenser, 'sync_plan', ['test' => true]);

# Output: true (messaggio inviato)
```

**Nel listener dovresti vedere:**
```
[2026-04-23 16:35:00] Message received on smart-dispenser/device-001/commands/sync_plan
```

---

## Step 4: Aggiungere un Nuovo ESP32 (1 minuto)

Ogni ESP32 ha un **device_uid** univoco.

### In 3 Passaggi:

#### 1. Crea il dispositivo nel database

```bash
php artisan tinker

Dispenser::create([
    'patient_id' => 1,
    'name' => 'ESP32 Stanza A',
    'device_uid' => 'esp32-stanza-a',
    'api_token' => str()->random(32),
    'mqtt_base_topic' => 'smart-dispenser/esp32-stanza-a',
    'is_active' => true,
]);
```

#### 2. Configura l'ESP32 con il `device_uid`

Nel tuo firmware ESP32, usa:

```cpp
const char* device_uid = "esp32-stanza-a";
```

#### 3. L'ESP32 pubblica i messaggi

```
Topic: smart-dispenser/esp32-stanza-a/events/telemetry
Payload: {"temperature": 22.5, "humidity": 48.3}
```

**Fatto!** Il listener riceve automaticamente dal nuovo dispositivo. Non serve configurare nulla di aggiuntivo.

---

## Tutti i Topic da Usare

| Direzione | Topic | Chi pubblica | Payload |
|-----------|-------|-------------|---------|
| LARAVEL → ESP32 | `smart-dispenser/{device_uid}/commands/sync_plan` | Web App | Schema Erogazione |
| ESP32 → LARAVEL | `smart-dispenser/{device_uid}/events/dose-log` | ESP32 | Erogazione Effettuata |
| ESP32 → LARAVEL | `smart-dispenser/{device_uid}/events/telemetry` | ESP32 | Temperatura + Umidità |
| ESP32 → LARAVEL | `smart-dispenser/{device_uid}/status` | ESP32 | Status Online |

---

## Comandi Utili

### Ascoltare con timeout

```bash
php artisan device:mqtt-listen --max-seconds=300
```

### Testare la connessione MQTT

```bash
php artisan tinker

$mqtt = new \PhpMqtt\Client\MqttClient('4a9747976f4d480f8994db0b416ae029.s1.eu.hivemq.cloud', 8883, 'test-client');
$settings = (new \PhpMqtt\Client\ConnectionSettings())->setUsername('Utente')->setPassword('Utente1!')->setUseTls(true);
$mqtt->connect($settings);
echo "Connesso!";
$mqtt->disconnect();
```

### Visualizzare tutti i dispositivi

```bash
php artisan tinker

Dispenser::all(['device_uid', 'name', 'is_online', 'last_seen_at']);
```

### Visualizzare gli ultimi sensori registrati

```bash
php artisan tinker

SensorLog::with('dispenser')->latest()->limit(10)->get();
```

---

## Payload Esempi

### Telemetria (da ESP32 a Laravel)

```json
{
  "temperature": 22.5,
  "humidity": 48.3,
  "recorded_at": "2026-04-23T16:35:00+02:00"
}
```

### Dose Log (da ESP32 a Laravel)

```json
{
  "status": "Dispensed",
  "therapy_plan_id": 1,
  "medicine_id": 5,
  "scheduled_for": "2026-04-23T14:30:00+02:00",
  "event_at": "2026-04-23T14:32:15+02:00",
  "notes": "Erogazione completata"
}
```

### Status (da ESP32 a Laravel)

```json
{
  "online": true,
  "battery": 85,
  "signal_strength": -65,
  "last_sync": "2026-04-23T16:30:00+02:00"
}
```

### Comando Sincronia (da Laravel a ESP32)

```json
{
  "command": "sync_plan",
  "issued_at": "2026-04-23T16:30:00+02:00",
  "payload": {
    "therapy_plans": [
      {
        "id": 1,
        "medicine_id": 5,
        "scheduled_for": "14:30",
        "quantity": 2
      }
    ]
  }
}
```

---

## Supporto Multipli ESP32

**Sì, il sistema supporta automaticamente multipli ESP32.**

Ogni dispositivo deve avere un `device_uid` univoco:

- `device-001`
- `esp32-stanza-a`
- `esp32-stanza-b`
- `medication-device-patient-5`

Il listener usa un wildcard (`+`) nel topic, quindi riceve messaggi da qualsiasi dispositivo automaticamente.

```
smart-dispenser/+/events/telemetry
```

Non serve configurare nulla per aggiungere dispositivi. Basta:

1. Aggiungere un record in `dispensers` con un `device_uid` univoco
2. Configurare l'ESP32 con quello stesso `device_uid`
3. L'ESP32 pubblica i messaggi
4. Il listener li riceve automaticamente

---

## Troubleshooting

### Il listener dice: "MQTT_HOST non configurato"

✅ **Soluzione:** Aggiungi le variabili MQTT al `.env`

```env
MQTT_HOST=4a9747976f4d480f8994db0b416ae029.s1.eu.hivemq.cloud
MQTT_PORT=8883
MQTT_USERNAME=Utente
MQTT_PASSWORD=Utente1!
MQTT_USE_TLS=true
```

### Non ricevo messaggi dal listener

✅ **Soluzione 1:** Verifica che il dispositivo pubblica sul topic corretto

```
smart-dispenser/{device_uid}/events/telemetry
```

✅ **Soluzione 2:** Verifica che il `device_uid` esista nel database

```bash
php artisan tinker
Dispenser::pluck('device_uid');  # Vedi tutti i device_uid
```

✅ **Soluzione 3:** Verifica i log Laravel

```bash
tail -f storage/logs/laravel.log
```

### Il dispositivo non è "online"

✅ **Soluzione:** Il dispositivo diventa online quando pubblica il primo messaggio di telemetria o status

```
smart-dispenser/{device_uid}/events/telemetry
smart-dispenser/{device_uid}/status
```

---

## Prossimi Passi

1. ✅ Leggi questa guida (5 minuti)
2. ✅ Configura il `.env` (già fatto!)
3. ✅ Avvia il listener: `php artisan device:mqtt-listen`
4. ✅ Testa con il Web Client HiveMQ
5. ✅ Configura l'ESP32 con il firmware
6. ✅ Aggiungi più dispositivi se necessario

---

## Guida Completa

Per dettagli approfonditi su MQTT, topic, architettura e firmware ESP32 completo, vedi:

📖 [GUIDA-MQTT-COMPLETA.md](GUIDA-MQTT-COMPLETA.md)

