# Firmware ESP32 per Smart Dispenser

**Data:** 23-04-2026

Questo è il codice completo per un ESP32 che comunica con il sistema MQTT di Smart Dispenser.

---

## Prerequisiti

### Hardware

- ESP32 (es: ESP32-WROOM-32)
- Sensore DHT22 (temperatura + umidità)
- Motore/Servo per erogazione
- Connessione WiFi

### Librerie Arduino necessarie

Installa in Arduino IDE → Gestione Librerie:

1. **PubSubClient** by Nick O'Leary
   - Versione: 2.8.0+
   - Per comunicazione MQTT

2. **Adafruit DHT sensor library** by Adafruit
   - Versione: 1.4.0+
   - Per sensore DHT22

3. **Adafruit Unified Sensor** by Adafruit
   - Versione: 1.1.14+
   - Dipendenza di DHT

---

## Connessioni Hardware

```
DHT22 Pin         → ESP32 Pin
- Vcc             → 3.3V
- GND             → GND
- Data            → GPIO 27

Motore Erogazione → ESP32 Pin
- PWM             → GPIO 12
- GND             → GND
- Vcc             → 5V (tramite relay/transistor)

LED Status        → ESP32 Pin
- Anode           → GPIO 2 (con resistenza 330Ω)
- Cathode         → GND
```

---

## Codice Arduino Completo

```cpp
#include <WiFi.h>
#include <PubSubClient.h>
#include <DHT.h>
#include <ArduinoJson.h>
#include <time.h>

// ===== CONFIGURAZIONE WiFi =====
const char* ssid = "YOUR_SSID";
const char* password = "YOUR_PASSWORD";

// ===== CONFIGURAZIONE MQTT =====
const char* mqtt_server = "4a9747976f4d480f8994db0b416ae029.s1.eu.hivemq.cloud";
const int mqtt_port = 8883;
const char* mqtt_username = "Utente";
const char* mqtt_password = "Utente1!";
const bool mqtt_use_tls = true;

// ===== CONFIGURAZIONE DISPOSITIVO =====
const char* device_uid = "device-001";  // Deve essere unico per ogni dispositivo
const char* topic_root = "smart-dispenser";

// ===== CONFIGURAZIONE SENSORI/HARDWARE =====
#define DHT_PIN 27
#define DHT_TYPE DHT22

#define MOTOR_PIN 12
#define MOTOR_PWM_CHANNEL 0
#define MOTOR_PWM_FREQ 1000
#define MOTOR_PWM_RESOLUTION 8

#define LED_STATUS_PIN 2

#define BATTERY_PIN 34

// ===== GLOBALI =====
WiFiClientSecure espClient;
PubSubClient mqtt_client(espClient);

DHT dht(DHT_PIN, DHT_TYPE);

float temperature = 0.0;
float humidity = 0.0;
int battery_level = 100;

unsigned long last_telemetry_publish = 0;
unsigned long last_status_publish = 0;
unsigned long last_sensor_read = 0;

const unsigned long TELEMETRY_INTERVAL = 60000;  // 1 minuto
const unsigned long STATUS_INTERVAL = 300000;    // 5 minuti
const unsigned long SENSOR_READ_INTERVAL = 10000; // 10 secondi

// ===== SETUP =====
void setup() {
    Serial.begin(115200);
    delay(1000);
    
    Serial.println("\n\nSmart Dispenser ESP32 Starting...");
    Serial.println("Device UID: " + String(device_uid));
    
    // Inizializza GPIO
    setupGPIO();
    
    // Inizializza DHT
    dht.begin();
    delay(500);
    
    // Connetti WiFi
    setupWiFi();
    
    // Configura MQTT
    mqtt_client.setServer(mqtt_server, mqtt_port);
    mqtt_client.setCallback(onMqttMessage);
    
    // Imposta ora via NTP
    configTime(3600, 3600, "pool.ntp.org", "time.nist.gov");
    
    Serial.println("Setup completo!");
}

// ===== LOOP PRINCIPALE =====
void loop() {
    // Connetti WiFi se disconnesso
    if (WiFi.status() != WL_CONNECTED) {
        reconnectWiFi();
    }
    
    // Connetti MQTT se disconnesso
    if (!mqtt_client.connected()) {
        reconnectMqtt();
    }
    
    // Processa messaggi MQTT
    mqtt_client.loop();
    
    // Leggi sensori ogni 10 secondi
    if (millis() - last_sensor_read > SENSOR_READ_INTERVAL) {
        readSensors();
        last_sensor_read = millis();
    }
    
    // Pubblica telemetria ogni minuto
    if (millis() - last_telemetry_publish > TELEMETRY_INTERVAL) {
        publishTelemetry();
        last_telemetry_publish = millis();
    }
    
    // Pubblica status ogni 5 minuti
    if (millis() - last_status_publish > STATUS_INTERVAL) {
        publishStatus();
        last_status_publish = millis();
    }
}

// ===== GPIO SETUP =====
void setupGPIO() {
    // Motor PWM
    ledcSetup(MOTOR_PWM_CHANNEL, MOTOR_PWM_FREQ, MOTOR_PWM_RESOLUTION);
    ledcAttachPin(MOTOR_PIN, MOTOR_PWM_CHANNEL);
    
    // LED Status
    pinMode(LED_STATUS_PIN, OUTPUT);
    digitalWrite(LED_STATUS_PIN, LOW);
}

// ===== WiFi =====
void setupWiFi() {
    Serial.println("\nConnecting to WiFi: " + String(ssid));
    
    WiFi.mode(WIFI_STA);
    WiFi.begin(ssid, password);
    
    int attempts = 0;
    while (WiFi.status() != WL_CONNECTED && attempts < 30) {
        delay(500);
        Serial.print(".");
        attempts++;
    }
    
    if (WiFi.status() == WL_CONNECTED) {
        Serial.println("\nWiFi connected!");
        Serial.println("IP: " + WiFi.localIP().toString());
        Serial.println("Signal: " + String(WiFi.RSSI()) + " dBm");
    } else {
        Serial.println("\nFailed to connect to WiFi!");
    }
}

void reconnectWiFi() {
    if (WiFi.status() != WL_CONNECTED) {
        Serial.println("Riconnessione WiFi...");
        setupWiFi();
    }
}

// ===== MQTT =====
void reconnectMqtt() {
    int attempts = 0;
    
    while (!mqtt_client.connected() && attempts < 5) {
        Serial.print("Attempting MQTT connection... ");
        
        String client_id = String(device_uid) + "-" + String(random(0xffff), HEX);
        
        if (mqtt_client.connect(client_id.c_str(), mqtt_username, mqtt_password)) {
            Serial.println("connected!");
            
            // Sottoscrivi ai comandi
            char cmd_topic[200];
            snprintf(cmd_topic, sizeof(cmd_topic), "%s/%s/commands/#", topic_root, device_uid);
            mqtt_client.subscribe(cmd_topic);
            Serial.println("Subscribed to: " + String(cmd_topic));
            
            // Pubblica status iniziale
            publishStatus();
            
            // Accendi LED per 1 secondo
            digitalWrite(LED_STATUS_PIN, HIGH);
            delay(1000);
            digitalWrite(LED_STATUS_PIN, LOW);
            
        } else {
            Serial.print("failed, rc=");
            Serial.print(mqtt_client.state());
            Serial.println(" try again in 5 seconds");
            delay(5000);
            attempts++;
        }
    }
}

// ===== SENSORI =====
void readSensors() {
    // Leggi DHT
    temperature = dht.readTemperature();
    humidity = dht.readHumidity();
    
    if (isnan(temperature) || isnan(humidity)) {
        Serial.println("Failed to read DHT sensor!");
        return;
    }
    
    // Leggi batteria
    int raw_battery = analogRead(BATTERY_PIN);
    battery_level = map(raw_battery, 0, 4095, 0, 100);
    battery_level = constrain(battery_level, 0, 100);
    
    Serial.printf("Temperature: %.1f°C, Humidity: %.1f%%, Battery: %d%%\n", 
                  temperature, humidity, battery_level);
}

// ===== PUBBLICAZIONE MESSAGGI =====
void publishTelemetry() {
    char topic[200];
    char payload[512];
    
    snprintf(topic, sizeof(topic), "%s/%s/events/telemetry", topic_root, device_uid);
    
    String timestamp = getIsoTimestamp();
    
    snprintf(payload, sizeof(payload),
        "{"
        "\"temperature\": %.1f, "
        "\"humidity\": %.1f, "
        "\"recorded_at\": \"%s\""
        "}",
        temperature, humidity, timestamp.c_str());
    
    if (mqtt_client.publish(topic, payload)) {
        Serial.println("Telemetry published: " + String(payload));
    } else {
        Serial.println("Failed to publish telemetry");
    }
}

void publishStatus() {
    char topic[200];
    char payload[512];
    
    snprintf(topic, sizeof(topic), "%s/%s/status", topic_root, device_uid);
    
    String timestamp = getIsoTimestamp();
    int signal = WiFi.RSSI();
    
    snprintf(payload, sizeof(payload),
        "{"
        "\"online\": true, "
        "\"battery\": %d, "
        "\"signal_strength\": %d, "
        "\"last_sync\": \"%s\""
        "}",
        battery_level, signal, timestamp.c_str());
    
    if (mqtt_client.publish(topic, payload)) {
        Serial.println("Status published: " + String(payload));
    } else {
        Serial.println("Failed to publish status");
    }
}

void publishDoseLog(String status, int medicine_id) {
    char topic[200];
    char payload[512];
    
    snprintf(topic, sizeof(topic), "%s/%s/events/dose-log", topic_root, device_uid);
    
    String timestamp = getIsoTimestamp();
    
    snprintf(payload, sizeof(payload),
        "{"
        "\"status\": \"%s\", "
        "\"medicine_id\": %d, "
        "\"event_at\": \"%s\""
        "}",
        status.c_str(), medicine_id, timestamp.c_str());
    
    if (mqtt_client.publish(topic, payload)) {
        Serial.println("Dose log published: " + String(payload));
    } else {
        Serial.println("Failed to publish dose log");
    }
}

// ===== CALLBACK MQTT =====
void onMqttMessage(char* topic, byte* payload, unsigned int length) {
    Serial.print("Message received on topic: ");
    Serial.println(topic);
    
    // Converti payload in stringa
    String message = "";
    for (int i = 0; i < length; i++) {
        message += (char)payload[i];
    }
    Serial.println("Payload: " + message);
    
    // Accendi LED
    digitalWrite(LED_STATUS_PIN, HIGH);
    
    // Parsa JSON
    StaticJsonDocument<512> doc;
    DeserializationError error = deserializeJson(doc, message);
    
    if (error) {
        Serial.println("JSON parse error!");
        digitalWrite(LED_STATUS_PIN, LOW);
        return;
    }
    
    // Estrai comando
    String command = doc["command"] | "";
    
    Serial.println("Command: " + command);
    
    // ===== GESTISCI COMANDI =====
    
    if (command == "sync_plan") {
        handleSyncPlan(doc);
    } 
    else if (command == "dispense_now") {
        handleDispenseNow(doc);
    } 
    else if (command == "pause_therapy") {
        handlePauseTherapy(doc);
    } 
    else if (command == "resume_therapy") {
        handleResumeTherapy(doc);
    } 
    else if (command == "ping") {
        handlePing();
    }
    else {
        Serial.println("Unknown command!");
    }
    
    // Spegni LED dopo 500ms
    delay(500);
    digitalWrite(LED_STATUS_PIN, LOW);
}

// ===== HANDLER COMANDI =====
void handleSyncPlan(StaticJsonDocument<512> doc) {
    Serial.println("Syncing therapy plan...");
    
    // Parsa therapy_plans dall'array
    JsonArray plans = doc["payload"]["therapy_plans"];
    
    for (JsonObject plan : plans) {
        int medicine_id = plan["medicine_id"] | 0;
        String scheduled = plan["scheduled_for"] | "";
        int quantity = plan["quantity"] | 1;
        
        Serial.printf("Plan: Medicine %d, Time %s, Qty %d\n", medicine_id, scheduled.c_str(), quantity);
        
        // Salva il piano in memoria (EEPROM)
        // Oppure invia al backend via REST API
    }
    
    publishStatus();  // Conferma
}

void handleDispenseNow(StaticJsonDocument<512> doc) {
    Serial.println("Dispensing now...");
    
    int slot = doc["payload"]["slot"] | 1;
    
    // Attiva motore
    dispenseMedicine(slot);
    
    // Pubblica dose log
    publishDoseLog("Dispensed", slot);
}

void handlePauseTherapy(StaticJsonDocument<512> doc) {
    Serial.println("Pausing therapy...");
    
    int minutes = doc["payload"]["minutes"] | 30;
    Serial.printf("Paused for %d minutes\n", minutes);
    
    // Implementa logica di pausa
}

void handleResumeTherapy(StaticJsonDocument<512> doc) {
    Serial.println("Resuming therapy...");
    
    // Implementa logica di ripresa
}

void handlePing() {
    Serial.println("Ping received, responding...");
    publishStatus();
}

// ===== MOTORE EROGAZIONE =====
void dispenseMedicine(int slot) {
    Serial.printf("Dispensing from slot %d\n", slot);
    
    // Accendi motore (PWM 255 = max)
    ledcWrite(MOTOR_PWM_CHANNEL, 255);
    
    // Aziona per 2 secondi
    delay(2000);
    
    // Spegni motore
    ledcWrite(MOTOR_PWM_CHANNEL, 0);
    
    Serial.println("Dispensing complete!");
}

// ===== UTILITY =====
String getIsoTimestamp() {
    time_t now = time(nullptr);
    struct tm* timeinfo = localtime(&now);
    
    char buffer[30];
    strftime(buffer, sizeof(buffer), "%FT%T+02:00", timeinfo);
    
    return String(buffer);
}
```

---

## Come Caricare il Codice

### 1. Installa Arduino IDE

- Scarica da: https://www.arduino.cc/en/software

### 2. Aggiungi ESP32 al Board Manager

- Vai a: File → Preferenze
- In "URL Schede aggiuntive" aggiungi:
  ```
  https://raw.githubusercontent.com/espressif/arduino-esp32/gh-pages/package_esp32_index.json
  ```
- Vai a: Strumenti → Scheda → Gestore Schede
- Cerca "esp32" e installa

### 3. Configura la Scheda

- Strumenti → Scheda → ESP32 → "ESP32 Dev Module"
- Strumenti → Velocità upload → 115200

### 4. Modifica il Codice

Cambia questi parametri con i tuoi:

```cpp
const char* ssid = "YOUR_SSID";           // Nome rete WiFi
const char* password = "YOUR_PASSWORD";   // Password WiFi
const char* device_uid = "device-001";    // ID univoco del dispositivo
```

### 5. Carica il Codice

- Collega l'ESP32 via USB
- Premi: Sketch → Carica
- Oppure: Ctrl+U

### 6. Apri il Monitor Seriale

- Strumenti → Monitor Seriale
- Velocità: 115200 baud

**Output atteso:**

```
Smart Dispenser ESP32 Starting...
Device UID: device-001
Connecting to WiFi: YOUR_SSID
...................
WiFi connected!
IP: 192.168.1.100
Signal: -65 dBm
Attempting MQTT connection... connected!
Subscribed to: smart-dispenser/device-001/commands/#
Status published: {...}
```

---

## Cosa Pubblica l'ESP32

### 1. Ogni minuto: Telemetria

```
Topic: smart-dispenser/device-001/events/telemetry
Payload: {
  "temperature": 22.5,
  "humidity": 48.3,
  "recorded_at": "2026-04-23T16:35:00+02:00"
}
```

### 2. Ogni 5 minuti: Status

```
Topic: smart-dispenser/device-001/status
Payload: {
  "online": true,
  "battery": 85,
  "signal_strength": -65,
  "last_sync": "2026-04-23T16:35:00+02:00"
}
```

### 3. Quando eroga: Dose Log

```
Topic: smart-dispenser/device-001/events/dose-log
Payload: {
  "status": "Dispensed",
  "medicine_id": 5,
  "event_at": "2026-04-23T14:32:15+02:00"
}
```

---

## Cosa Riceve l'ESP32

### Comandi da Laravel

```
Topic: smart-dispenser/device-001/commands/sync_plan
Payload: {
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

## Troubleshooting

### ESP32 non si connette al WiFi

- Verifica SSID e password
- Verifica che il router supporti 2.4GHz (ESP32 non supporta 5GHz)
- Riavvia il router

### MQTT non si connette

- Verifica credenziali MQTT (host, username, password)
- Verifica che il broker HiveMQ sia online
- Se usi TLS, verifica il certificato

### Sensore DHT non legge

- Verifica la connessione GPIO 27
- Verifica che il sensore non sia difettoso
- Prova a scollegare/ricollegare

### LED non si accende

- Verifica la connessione GPIO 2
- Verifica che il resistore 330Ω sia inserito
- Prova con LED diverso

### Messaggi non arrivano a Laravel

- Verifica che il listener sia acceso: `php artisan device:mqtt-listen`
- Verifica il `device_uid` nel database: `Dispenser::pluck('device_uid')`
- Verifica i log Laravel: `tail -f storage/logs/laravel.log`

---

## Prossimi Passi

1. ✅ Installa Arduino IDE e librerie
2. ✅ Modifica il codice con i tuoi parametri
3. ✅ Carica il codice sull'ESP32
4. ✅ Apri il Monitor Seriale e verifica l'output
5. ✅ Avvia il listener Laravel: `php artisan device:mqtt-listen`
6. ✅ Verifica i messaggi in tempo reale

---

## Guida Completa

Per dettagli su MQTT, topic e architettura, vedi:

📖 [GUIDA-MQTT-COMPLETA.md](GUIDA-MQTT-COMPLETA.md)

