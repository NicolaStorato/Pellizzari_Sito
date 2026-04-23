# Guida MQTT Completa: Struttura Bidirezionale

Data: 23-04-2026  
Stato: **In produzione**

---

## INDICE

1. [Architettura Generale](#architettura-generale)
2. [Flussi di Comunicazione](#flussi-di-comunicazione)
3. [Topic MQTT](#topic-mqtt)
4. [Configurazione HiveMQ](#configurazione-hivemq)
5. [Implementazione Laravel](#implementazione-laravel)
6. [Firmware ESP32](#firmware-esp32)
7. [Verifica End-to-End](#verifica-end-to-end)
8. [Supporto Multipli ESP32](#supporto-multipli-esp32)

---

## ARCHITETTURA GENERALE

```
┌──────────────────┐
│    LARAVEL APP   │
│   (Web Portal)   │
└────────┬─────────┘
         │ PUBLISH
         │ - Schema Erogazione
         │ - Comandi Sincronia
         │
    ┌────▼────┐
    │  HiveMQ  │
    │  Broker  │
    └────┬────┘
    ┌────┴────────────┐
    │                 │ SUBSCRIBE
    │ - Telemetria    │
    │ - Dose Log      │
    │ - Status        │
    │                 │
    ▼                 ▼
┌──────────┐    ┌──────────┐
│  ESP32   │    │  LARAVEL │
│  Device  │    │ Listener │
│  #1      │    │ (Backend)│
└──────────┘    └──────────┘
```

---

## FLUSSI DI COMUNICAZIONE

### Flusso 1: LARAVEL → ESP32 (Schema Erogazione)

**Quando:** Il dottore crea/modifica un piano terapia

```
1. Dottore modifica Piano Terapia nel web portal
   ↓
2. Laravel salva nel DB
   ↓
3. Admin clicca "Sincronizza Dispositivo" (o comando automatico)
   ↓
4. Laravel pubblica su MQTT:
   Topic: smart-dispenser/{device_uid}/commands/sync_plan
   Payload:
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
   ↓
5. ESP32 riceve il comando
   ↓
6. ESP32 risponde con ACK (opzionale) oppure sincronizza
```

**Codice Laravel (già implementato):**

```php
// In un controller o job:
use App\Services\MqttPublisher;

$publisher = new MqttPublisher();
$dispenser = Dispenser::find($id);

$payload = [
    'therapy_plans' => $dispenser->patient->therapyPlans()
        ->where('active', true)
        ->get()
        ->map(fn($plan) => [
            'id' => $plan->id,
            'medicine_id' => $plan->medicine_id,
            'scheduled_for' => $plan->scheduled_time,
            'quantity' => $plan->quantity,
        ])
        ->toArray()
];

$publisher->publishCommand($dispenser, 'sync_plan', $payload);
```

---

### Flusso 2: ESP32 → LARAVEL (Erogazione Effettuata)

**Quando:** ESP32 dispensa una dose

```
1. ESP32 rileva che ha dispensato una dose
   ↓
2. ESP32 pubblica su MQTT:
   Topic: smart-dispenser/{device_uid}/events/dose-log
   Payload:
   {
     "status": "Dispensed",
     "therapy_plan_id": 1,
     "medicine_id": 5,
     "scheduled_for": "2026-04-23T14:30:00+02:00",
     "event_at": "2026-04-23T14:32:15+02:00",
     "notes": "Erogazione completata correttamente"
   }
   ↓
3. Laravel Listener riceve il messaggio
   ↓
4. Laravel salva in DB (DoseLog)
   ↓
5. Dashboard aggiornata in tempo reale
```

**Topic:** `smart-dispenser/{device_uid}/events/dose-log`

**Payload Accettato:**

```json
{
  "status": "Dispensed",
  "therapy_plan_id": 1,
  "medicine_id": 5,
  "scheduled_for": "2026-04-23T14:30:00+02:00",
  "event_at": "2026-04-23T14:32:15+02:00",
  "notes": "Descrizione evento (opzionale)"
}
```

---

### Flusso 3: ESP32 → LARAVEL (Telemetria: Umidità + Temperatura)

**Quando:** ESP32 legge sensori (ogni minuto o configurabile)

```
1. ESP32 legge sensori di umidità e temperatura
   ↓
2. ESP32 pubblica su MQTT:
   Topic: smart-dispenser/{device_uid}/events/telemetry
   Payload:
   {
     "temperature": 22.5,
     "humidity": 48.3,
     "recorded_at": "2026-04-23T16:35:00+02:00"
   }
   ↓
3. Laravel Listener riceve il messaggio
   ↓
4. Laravel controlla soglie e genera alert se necessario
   ↓
5. Dashboard mostra i sensori aggiornati
```

**Topic:** `smart-dispenser/{device_uid}/events/telemetry`

**Payload Accettato (entrambe le varianti):**

**Variante Consigliata:**

```json
{
  "temperature": 22.5,
  "humidity": 48.3,
  "recorded_at": "2026-04-23T16:35:00+02:00"
}
```

**Variante Compatibile:**

```json
{
  "temperatura": 22.5,
  "umidita": 48.3,
  "sensore_id": "ESP32_01"
}
```

---

### Flusso 4: ESP32 → LARAVEL (Status Online)

**Quando:** ESP32 si connette o periodicamente

```
1. ESP32 si connette a MQTT
   ↓
2. ESP32 pubblica su MQTT:
   Topic: smart-dispenser/{device_uid}/status
   Payload:
   {
     "online": true,
     "battery": 85,
     "signal_strength": -65,
     "last_sync": "2026-04-23T16:30:00+02:00"
   }
   ↓
3. Laravel riceve e aggiorna lo stato del dispositivo
```

**Topic:** `smart-dispenser/{device_uid}/status`

**Payload Accettato:**

```json
{
  "online": true,
  "battery": 85,
  "signal_strength": -65,
  "last_sync": "2026-04-23T16:30:00+02:00"
}
```

---

## TOPIC MQTT

### Struttura dei Topic

Tutti i topic seguono questa struttura:

```
smart-dispenser/{device_uid}/{category}/{event}
```

- `smart-dispenser` = Root topic (definito in config)
- `{device_uid}` = ID univoco del dispositivo (es: `device-001`)
- `{category}` = Categoria del messaggio (`events`, `commands`, `status`)
- `{event}` = Tipo specifico di evento

### Topic da usare

| Direzione | Topic | Payload |
|-----------|-------|---------|
| **LARAVEL → ESP32** | `smart-dispenser/{device_uid}/commands/sync_plan` | Schema Erogazione |
| **ESP32 → LARAVEL** | `smart-dispenser/{device_uid}/events/dose-log` | Erogazione Effettuata |
| **ESP32 → LARAVEL** | `smart-dispenser/{device_uid}/events/telemetry` | Umidità + Temperatura |
| **ESP32 → LARAVEL** | `smart-dispenser/{device_uid}/status` | Status Dispositivo |

### Esempi di Device UID

Questi sono i device UID nel tuo database:

- `device-001` → Dispenser Principale
- `bsfpwtw1` → Dispenser Test 1
- `gpnr8tzv` → Dispenser Test 2
- `anadwaic` → Dispenser Test 3

Se vuoi aggiungerne altri, modifica la tabella `dispensers`.

---

## CONFIGURAZIONE HIVEMQ

### Variabili di Ambiente (.env)

Aggiungi al tuo `.env`:

```dotenv
# HiveMQ Configuration
MQTT_HOST=localhost
MQTT_PORT=1883
MQTT_USERNAME=admin
MQTT_PASSWORD=admin
MQTT_USE_TLS=false
MQTT_CLIENT_ID=smart-dispenser-app
MQTT_CLEAN_SESSION=true

# MQTT Topic Configuration
MQTT_TOPIC_ROOT=smart-dispenser
MQTT_TOPIC_TELEMETRY_SUFFIX=events/telemetry
MQTT_TOPIC_DOSE_LOG_SUFFIX=events/dose-log
MQTT_TOPIC_STATUS_SUFFIX=status
```

### Subscriber nel Web Client HiveMQ

Vai su: http://localhost:8884 (HiveMQ WebUI)

Sottoscrivi a questi topic per vedere i messaggi in tempo reale:

```
# Tutti i messaggi (debug)
#

# Telemetria da tutti i dispositivi
smart-dispenser/+/events/telemetry

# Dose Log da tutti i dispositivi
smart-dispenser/+/events/dose-log

# Status da tutti i dispositivi
smart-dispenser/+/status

# Comandi a tutti i dispositivi
smart-dispenser/+/commands/+
```

**Wildcard:**
- `+` = un livello generico
- `#` = tutti i livelli (solo alla fine)

---

## IMPLEMENTAZIONE LARAVEL

### 1. Configurazione (`config/services.php`)

```php
// In config/services.php, aggiungi:
'mqtt' => [
    'host' => env('MQTT_HOST', 'localhost'),
    'port' => env('MQTT_PORT', 1883),
    'username' => env('MQTT_USERNAME'),
    'password' => env('MQTT_PASSWORD'),
    'use_tls' => env('MQTT_USE_TLS', false),
    'client_id' => env('MQTT_CLIENT_ID', 'smart-dispenser-app'),
    'clean_session' => env('MQTT_CLEAN_SESSION', true),
    'topic_root' => env('MQTT_TOPIC_ROOT', 'smart-dispenser'),
    'topic_telemetry_suffix' => env('MQTT_TOPIC_TELEMETRY_SUFFIX', 'events/telemetry'),
    'topic_dose_log_suffix' => env('MQTT_TOPIC_DOSE_LOG_SUFFIX', 'events/dose-log'),
    'topic_status_suffix' => env('MQTT_TOPIC_STATUS_SUFFIX', 'status'),
],
```

### 2. Avviare il Listener

Il listener è il servizio che ascolta tutti i messaggi MQTT e li salva nel database.

**Comando:**

```bash
php artisan device:mqtt-listen
```

**Comportamento:**
- Sottoscrive ai topic automaticamente
- Ascolta continuamente i messaggi
- Quando riceve un messaggio, lo elabora
- Salva telemetria, dose log, status nel database
- Rimane in ascolto indefinitamente (finché non prema Ctrl+C)

**Con timeout (300 secondi):**

```bash
php artisan device:mqtt-listen --max-seconds=300
```

**Output atteso:**

```
MQTT Listener started...
Subscribed to: smart-dispenser/+/events/telemetry
Subscribed to: smart-dispenser/+/events/dose-log
Subscribed to: smart-dispenser/+/status
Listening for messages...

[2026-04-23 16:35:00] Message received on smart-dispenser/device-001/events/telemetry
Temperature: 22.5°C, Humidity: 48.3%

[2026-04-23 16:36:15] Message received on smart-dispenser/device-001/events/dose-log
Status: Dispensed, Medicine: Tachipirina
```

### 3. Uso del Publisher (LARAVEL → ESP32)

Il Publisher serve per inviare comandi da Laravel all'ESP32.

```php
use App\Services\MqttPublisher;

// Nel controller o job
$dispenser = Dispenser::find(1);
$publisher = new MqttPublisher();

// Invia comando di sincronizzazione
$payload = [
    'therapy_plans' => [
        [
            'id' => 1,
            'medicine_id' => 5,
            'scheduled_for' => '14:30',
            'quantity' => 2,
        ]
    ]
];

$success = $publisher->publishCommand(
    $dispenser,
    'sync_plan',
    $payload
);

if ($success) {
    echo "Comando inviato con successo!";
} else {
    echo "Errore: Impossibile inviare il comando";
}
```

**Logica del Publisher:**
1. Legge le configurazioni MQTT dal `.env`
2. Si connette al broker HiveMQ
3. Pubblica il messaggio sul topic corretto
4. Si disconnette

---

## FIRMWARE ESP32

### Esempio di Codice ESP32 (Arduino)

```cpp
#include <WiFi.h>
#include <PubSubClient.h>

// WiFi
const char* ssid = "YOUR_SSID";
const char* password = "YOUR_PASSWORD";

// MQTT
const char* mqtt_server = "192.168.1.100";  // IP del server MQTT
const int mqtt_port = 1883;
const char* mqtt_username = "admin";
const char* mqtt_password = "admin";

// Device
const char* device_uid = "device-001";

WiFiClient espClient;
PubSubClient client(espClient);

// Variabili sensori
float temperature = 0;
float humidity = 0;

void setup() {
    Serial.begin(115200);
    setupWiFi();
    client.setServer(mqtt_server, mqtt_port);
    client.setCallback(onMqttMessage);
}

void loop() {
    if (!client.connected()) {
        reconnect();
    }
    client.loop();
    
    // Leggi sensori ogni minuto
    if (millis() % 60000 == 0) {
        temperature = readTemperature();  // La tua funzione
        humidity = readHumidity();         // La tua funzione
        publishTelemetry();
    }
}

void setupWiFi() {
    Serial.println("\nConnecting to WiFi: " + String(ssid));
    WiFi.begin(ssid, password);
    int attempts = 0;
    while (WiFi.status() != WL_CONNECTED && attempts < 20) {
        delay(500);
        Serial.print(".");
        attempts++;
    }
    Serial.println("\nWiFi connected!");
}

void reconnect() {
    while (!client.connected()) {
        Serial.print("Attempting MQTT connection...");
        if (client.connect(device_uid, mqtt_username, mqtt_password)) {
            Serial.println("connected");
            
            // Sottoscrivi ai comandi
            char topic[100];
            sprintf(topic, "smart-dispenser/%s/commands/#", device_uid);
            client.subscribe(topic);
            
            // Pubblica status
            publishStatus();
        } else {
            Serial.print("failed, rc=");
            Serial.print(client.state());
            Serial.println(" try again in 5 seconds");
            delay(5000);
        }
    }
}

void publishTelemetry() {
    char topic[100];
    char payload[200];
    
    sprintf(topic, "smart-dispenser/%s/events/telemetry", device_uid);
    sprintf(payload, "{\"temperature\": %.1f, \"humidity\": %.1f, \"recorded_at\": \"%s\"}", 
            temperature, humidity, getCurrentTime());
    
    client.publish(topic, payload);
    Serial.println("Telemetry published: " + String(payload));
}

void publishDoseLog(String status, int medicineId) {
    char topic[100];
    char payload[300];
    
    sprintf(topic, "smart-dispenser/%s/events/dose-log", device_uid);
    sprintf(payload, "{\"status\": \"%s\", \"medicine_id\": %d, \"event_at\": \"%s\"}",
            status.c_str(), medicineId, getCurrentTime());
    
    client.publish(topic, payload);
    Serial.println("Dose log published: " + String(payload));
}

void publishStatus() {
    char topic[100];
    char payload[200];
    
    sprintf(topic, "smart-dispenser/%s/status", device_uid);
    sprintf(payload, "{\"online\": true, \"battery\": 85, \"signal_strength\": %d, \"last_sync\": \"%s\"}",
            WiFi.RSSI(), getCurrentTime());
    
    client.publish(topic, payload);
    Serial.println("Status published");
}

void onMqttMessage(char* topic, byte* payload, unsigned int length) {
    Serial.print("Message received on topic: ");
    Serial.println(topic);
    
    // Converti payload in stringa
    String message = "";
    for (int i = 0; i < length; i++) {
        message += (char)payload[i];
    }
    Serial.println("Payload: " + message);
    
    // Se ricevi comando di sincronizzazione
    if (String(topic).indexOf("sync_plan") > 0) {
        // Parsa il JSON e sincronizza
        // ... il tuo codice di parsing JSON ...
        publishStatus();  // Conferma
    }
}

String getCurrentTime() {
    // Ritorna timestamp ISO 8601
    // Implementa con la tua logica RTC
    return "2026-04-23T16:35:00+02:00";
}

float readTemperature() {
    // Leggi dal sensore DHT/BME280
    // Ritorna il valore in Celsius
    return 22.5;
}

float readHumidity() {
    // Leggi dal sensore DHT/BME280
    // Ritorna il valore in %
    return 48.3;
}
```

---

## VERIFICA END-TO-END

### Step 1: Avvia il Broker MQTT

```bash
# Se HiveMQ è installato
cd /path/to/hivemq
./bin/run.sh

# Oppure con Docker
docker run -p 1883:1883 -p 8884:8884 hivemq/hivemq:latest
```

### Step 2: Avvia il Listener Laravel

```bash
php artisan device:mqtt-listen
```

### Step 3: Prova il Publisher

In un terminale separato:

```bash
php artisan tinker

// Pubblica un comando al dispositivo
$dispenser = Dispenser::first();
$publisher = new \App\Services\MqttPublisher();
$publisher->publishCommand($dispenser, 'sync_plan', ['test' => true]);

// Dovresti vedere nel listener:
// "Message received on smart-dispenser/device-001/commands/sync_plan"
```

### Step 4: Simula Messaggio da ESP32

Nel Web Client HiveMQ:

**Pubblica telemetria:**

- Topic: `smart-dispenser/device-001/events/telemetry`
- Payload: `{"temperature": 24.7, "humidity": 48.3}`

**Nel listener dovresti vedere:**

```
[2026-04-23 16:35:00] Message received on smart-dispenser/device-001/events/telemetry
Temperature: 24.7°C, Humidity: 48.3%
```

**Nel database (SensorLog):**

```sql
SELECT * FROM sensor_logs WHERE dispenser_id = 1 ORDER BY created_at DESC LIMIT 1;
```

---

## SUPPORTO MULTIPLI ESP32

### Come Aggiungere un Nuovo Dispositivo

#### Opzione 1: Via Web Portal (Consigliato)

1. Vai in Dashboard → Dispositivi → Aggiungi Dispositivo
2. Inserisci:
   - Nome: `ESP32 Stanza 1`
   - Device UID: `esp32-stanza-1` (deve essere univoco)
   - Topic Base (opzionale): sarà generato automaticamente
3. Salva
4. Copia il Device Token (per ESP32)
5. Configura ESP32 con il Device UID

#### Opzione 2: Via Database

```php
// In tinker
$dispenser = Dispenser::create([
    'patient_id' => 1,
    'name' => 'ESP32 Stanza 2',
    'device_uid' => 'esp32-stanza-2',
    'api_token' => str()->random(32),
    'mqtt_base_topic' => 'smart-dispenser/esp32-stanza-2',
    'is_active' => true,
]);
```

#### Opzione 3: Via Seeder

```php
// database/seeders/DispenserSeeder.php

public function run()
{
    $patient = User::where('role', 'Patient')->first();
    
    Dispenser::create([
        'patient_id' => $patient->id,
        'name' => 'ESP32 Stanza 3',
        'device_uid' => 'esp32-stanza-3',
        'api_token' => str()->random(32),
        'mqtt_base_topic' => 'smart-dispenser/esp32-stanza-3',
        'is_active' => true,
    ]);
}
```

### Listener Automaticamente Supporta Multipli

Il listener usa un wildcard nel topic:

```
smart-dispenser/+/events/telemetry
```

Il `+` significa "qualsiasi dispositivo". Quindi:

- `smart-dispenser/device-001/events/telemetry` ✅ Ricevuto
- `smart-dispenser/esp32-stanza-1/events/telemetry` ✅ Ricevuto
- `smart-dispenser/new-device/events/telemetry` ✅ Ricevuto

**Non serve configurare nulla!** Il listener riceve messaggi da qualsiasi dispositivo automaticamente.

### Routing dei Messaggi per Dispositivo

Il backend identifica il dispositivo dal topic:

```
Topic: smart-dispenser/device-001/events/telemetry
        ↓ Estrae "device-001"
        ↓
Cerca: SELECT * FROM dispensers WHERE device_uid = 'device-001'
        ↓
Se trovato: Salva il messaggio associato a quel dispositivo
Se NOT trovato: Log di errore, scarta il messaggio
```

---

## FAQ

### D: Posso usare più di un ESP32?

**R:** Sì, il sistema è progettato per supportare multipli ESP32 simultaneamente. Ogni dispositivo ha un `device_uid` univoco. Il listener sottoscrive a `smart-dispenser/+/...` (wildcard), quindi riceve messaggi da qualsiasi dispositivo. Non serve configurare nulla di aggiuntivo.

### D: Come sincronizziamo più ESP32 contemporaneamente?

**R:** Puoi inviare il comando a ognuno separatamente:

```php
$dispensers = Dispenser::where('is_active', true)->get();
$publisher = new MqttPublisher();

foreach ($dispensers as $dispenser) {
    $publisher->publishCommand($dispenser, 'sync_plan', [...]);
}
```

Oppure crea un Job asincrono:

```php
foreach ($dispensers as $dispenser) {
    SyncDispenserJob::dispatch($dispenser);
}
```

### D: Cosa succede se un ESP32 si disconnette?

**R:** Se l'ESP32 non pubblica messaggi per X minuti, il backend lo segna come offline:

```php
// In DeviceEventIngestionService
$dispenser->update(['is_online' => false]);
```

Quando si ricollega e pubblica di nuovo, torna online.

### D: Come monitorare il listener in produzione?

**R:** Usa Supervisor per monitorare il processo:

```ini
# /etc/supervisor/conf.d/mqtt-listener.conf
[program:mqtt-listener]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan device:mqtt-listen
autostart=true
autorestart=true
numprocs=1
redirect_stderr=true
stdout_logfile=/path/to/storage/logs/mqtt-listener.log
```

Riavvia Supervisor:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start mqtt-listener:*
```

---

## Prossimi Passi

1. ✅ Leggi questa guida
2. ✅ Configura `.env` con credenziali MQTT
3. ✅ Avvia il listener: `php artisan device:mqtt-listen`
4. ✅ Configura ESP32 con il `device_uid`
5. ✅ Testa la comunicazione end-to-end
6. ✅ Aggiungi più dispositivi se necessario

---

## Contatti & Debug

Se il listener non funziona:

```bash
# Verifica la connessione MQTT
php artisan tinker
$mqtt = new \PhpMqtt\Client\MqttClient('localhost', 1883, 'test-client');
$mqtt->connect(new \PhpMqtt\Client\ConnectionSettings());
// Se torna true, la connessione funziona

# Controlla i log
tail -f storage/logs/laravel.log
```

