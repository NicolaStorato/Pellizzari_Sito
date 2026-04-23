# MQTT Subscribers — ESP32 Smart Dispenser

Questo file elenca tutti i **topic a cui l'ESP32 deve sottoscriversi** e tutti i **topic che deve pubblicare**,
con i payload JSON attesi dal server Laravel.

---

## Credenziali broker (HiveMQ Cloud)

```cpp
const char* MQTT_HOST     = "4a9747976f4d480f8994db0b416ae029.s1.eu.hivemq.cloud";
const int   MQTT_PORT     = 8883;          // TLS
const char* MQTT_USER     = "Utente";
const char* MQTT_PASS     = "Utente1!";
const char* DEVICE_UID    = "ESP32-XXXX";  // ← cambia con il device_uid del dispenser nel DB
const char* TOPIC_ROOT    = "smart-dispenser";
```

Il `DEVICE_UID` deve corrispondere esattamente al campo `device_uid` del record Dispenser nel database.

---

## Topic base del dispositivo

```
smart-dispenser/ESP32-XXXX/
```

Tutti i topic sotto usano questo prefisso. Sostituire `ESP32-XXXX` con il device_uid reale.

---

## PUBLISH — L'ESP32 pubblica questi topic

### 1. Telemetria sensori (temperatura e umidità)
**Topic:** `smart-dispenser/{DEVICE_UID}/events/telemetry`
**QoS:** 0
**Frequenza consigliata:** ogni 30–60 secondi

```json
{
  "temperature": 22.5,
  "humidity": 55.3,
  "recorded_at": "2025-01-01T12:00:00+00:00"
}
```

> Il campo `recorded_at` è opzionale. Se omesso Laravel usa l'ora di ricezione.
> Il server controlla automaticamente le soglie dei farmaci del paziente e genera Alert se superate.

---

### 2. Evento dose
**Topic:** `smart-dispenser/{DEVICE_UID}/events/dose-log`
**QoS:** 1
**Quando:** ogni volta che avviene un evento relativo a una dose (erogazione, conferma, mancata assunzione)

```json
{
  "status": "Dispensed",
  "therapy_plan_id": 3,
  "medicine_id": 7,
  "scheduled_for": "2025-01-01T08:00:00+00:00",
  "event_at": "2025-01-01T08:01:45+00:00",
  "notes": null
}
```

Valori validi per `status`:
| Valore      | Quando inviarlo                                  |
|-------------|--------------------------------------------------|
| `Dispensed` | La pillola è stata fisicamente erogata           |
| `Taken`     | Il paziente ha confermato l'assunzione           |
| `Missed`    | L'orario è passato senza che la dose fosse presa |
| `Snoozed`   | Il paziente ha posticipato                       |
| `Skipped`   | Il paziente ha saltato intenzionalmente          |

> ⚠️ Se invii `status: "Missed"` Laravel crea automaticamente un **Alert di tipo MissedDose** (severità High).

---

### 3. Heartbeat / Stato online
**Topic:** `smart-dispenser/{DEVICE_UID}/status`
**QoS:** 0
**Frequenza consigliata:** ogni 60 secondi (heartbeat) + all'avvio + allo spegnimento

```json
{
  "is_online": true,
  "last_seen_at": "2025-01-01T12:00:00+00:00"
}
```

Per notificare lo spegnimento (es. nel Last Will MQTT):
```json
{
  "is_online": false,
  "last_seen_at": "2025-01-01T12:00:00+00:00"
}
```

> Configura il **Last Will** MQTT con questo payload sul topic `status` così il broker lo invia
> automaticamente se il dispositivo si disconnette in modo inatteso.

---

## SUBSCRIBE — L'ESP32 si sottoscrive a questi topic

### 1. Tutti i comandi (wildcard)
**Topic filter:** `smart-dispenser/{DEVICE_UID}/commands/#`
**QoS:** 1

Questo singolo subscribe con `#` cattura tutti i comandi. In alternativa puoi
sottoscriverti a ogni comando individualmente (vedi sotto).

---

### 2. Comandi individuali

#### dispense_now — Eroga subito
**Topic:** `smart-dispenser/{DEVICE_UID}/commands/dispense_now`

```json
{
  "command": "dispense_now",
  "issued_at": "2025-01-01T12:00:00+00:00",
  "payload": {
    "slot": 1
  }
}
```
**Azione ESP32:** erogare immediatamente la pillola dal vano `slot` indicato.

---

#### pause_therapy — Pausa terapia
**Topic:** `smart-dispenser/{DEVICE_UID}/commands/pause_therapy`

```json
{
  "command": "pause_therapy",
  "issued_at": "2025-01-01T12:00:00+00:00",
  "payload": {
    "minutes": 30
  }
}
```
**Azione ESP32:** sospendere l'esecuzione automatica del piano per `minutes` minuti.

---

#### resume_therapy — Riprendi terapia
**Topic:** `smart-dispenser/{DEVICE_UID}/commands/resume_therapy`

```json
{
  "command": "resume_therapy",
  "issued_at": "2025-01-01T12:00:00+00:00",
  "payload": {}
}
```
**Azione ESP32:** riattivare il piano dopo una pausa.

---

#### sync_plan — Sincronizza piano terapia
**Topic:** `smart-dispenser/{DEVICE_UID}/commands/sync_plan`

```json
{
  "command": "sync_plan",
  "issued_at": "2025-01-01T12:00:00+00:00",
  "payload": {
    "force": true
  }
}
```
**Azione ESP32:** fare una chiamata REST API GET `/api/v1/device/plans` con l'`api_token`
per scaricare il piano terapia aggiornato e ricaricare la schedulazione interna.

---

#### ping — Heartbeat forzato
**Topic:** `smart-dispenser/{DEVICE_UID}/commands/ping`

```json
{
  "command": "ping",
  "issued_at": "2025-01-01T12:00:00+00:00",
  "payload": {}
}
```
**Azione ESP32:** pubblicare immediatamente un messaggio sul topic `status` con `is_online: true`.

---

## Struttura riepilogativa

```
PUBLISH (ESP32 → broker → Laravel):
  smart-dispenser/{uid}/events/telemetry    QoS 0 — ogni ~60s
  smart-dispenser/{uid}/events/dose-log     QoS 1 — a ogni evento dose
  smart-dispenser/{uid}/status              QoS 0 — heartbeat + LWT

SUBSCRIBE (Laravel → broker → ESP32):
  smart-dispenser/{uid}/commands/#          QoS 1 — riceve tutti i comandi
    ├── commands/dispense_now
    ├── commands/pause_therapy
    ├── commands/resume_therapy
    ├── commands/sync_plan
    └── commands/ping
```

---

## Esempio sketch Arduino (struttura base)

```cpp
#include <WiFiClientSecure.h>
#include <PubSubClient.h>
#include <ArduinoJson.h>

const char* DEVICE_UID = "ESP32-XXXX";  // ← imposta il tuo device_uid

char topicTelemetry[80];
char topicDoseLog[80];
char topicStatus[80];
char topicCommands[80];

void buildTopics() {
  sprintf(topicTelemetry, "smart-dispenser/%s/events/telemetry", DEVICE_UID);
  sprintf(topicDoseLog,   "smart-dispenser/%s/events/dose-log",  DEVICE_UID);
  sprintf(topicStatus,    "smart-dispenser/%s/status",           DEVICE_UID);
  sprintf(topicCommands,  "smart-dispenser/%s/commands/#",       DEVICE_UID);
}

void onMqttMessage(char* topic, byte* payload, unsigned int length) {
  String topicStr = String(topic);
  String msg = String((char*)payload).substring(0, length);

  JsonDocument doc;
  deserializeJson(doc, msg);
  String command = doc["command"].as<String>();

  if (topicStr.endsWith("/commands/dispense_now")) {
    int slot = doc["payload"]["slot"] | 1;
    dispensePill(slot);

  } else if (topicStr.endsWith("/commands/pause_therapy")) {
    int minutes = doc["payload"]["minutes"] | 30;
    pauseTherapy(minutes);

  } else if (topicStr.endsWith("/commands/resume_therapy")) {
    resumeTherapy();

  } else if (topicStr.endsWith("/commands/sync_plan")) {
    syncPlanFromApi();

  } else if (topicStr.endsWith("/commands/ping")) {
    publishStatus(true);
  }
}

void publishTelemetry(float temp, float hum) {
  JsonDocument doc;
  doc["temperature"] = temp;
  doc["humidity"]    = hum;
  char buf[128];
  serializeJson(doc, buf);
  mqttClient.publish(topicTelemetry, buf, false);
}

void publishDoseEvent(const char* status, int therapyPlanId, int medicineId) {
  JsonDocument doc;
  doc["status"]          = status;
  doc["therapy_plan_id"] = therapyPlanId;
  doc["medicine_id"]     = medicineId;
  // doc["event_at"] = "2025-01-01T12:00:00+00:00";  // opzionale
  char buf[256];
  serializeJson(doc, buf);
  mqttClient.publish(topicDoseLog, buf, false);
}

void publishStatus(bool isOnline) {
  JsonDocument doc;
  doc["is_online"] = isOnline;
  char buf[64];
  serializeJson(doc, buf);
  mqttClient.publish(topicStatus, buf, isOnline ? false : true); // retain=true per LWT
}

void mqttConnect() {
  // Last Will: inviato automaticamente dal broker se ESP32 si disconnette
  mqttClient.connect(DEVICE_UID, MQTT_USER, MQTT_PASS,
    topicStatus, 0, true, "{\"is_online\":false}");

  mqttClient.subscribe(topicCommands, 1);
  publishStatus(true);
}
```

---

## Note importanti

- **TLS obbligatorio**: HiveMQ Cloud richiede TLS sulla porta 8883. Caricare il certificato root CA di HiveMQ nell'ESP32.
- **Client ID unico**: ogni dispositivo deve usare un Client ID diverso. Usare il `device_uid` come Client ID va bene.
- **Last Will**: configurare sempre il Last Will sul topic `status` con `is_online: false` così Laravel aggiorna il flag `is_online` anche in caso di disconnessione inattesa.
- **QoS 1 per dose-log**: usare QoS 1 per garantire che gli eventi dose vengano sempre consegnati.
- **REST API per il piano**: il comando `sync_plan` deve triggerare una chiamata HTTP GET a `/api/v1/device/plans` con header `Authorization: Bearer {api_token}` per scaricare il piano terapia completo.
