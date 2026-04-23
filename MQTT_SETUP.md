# Guida MQTT — Smart Dispenser Pillole

## Architettura generale

```
ESP32 (Dispenser)
      │  MQTT publish/subscribe
      ▼
HiveMQ Cloud (broker)
      │  MQTT subscribe/publish
      ▼
Laravel (XAMPP) ──── MySQL
      ▲
      │  REST API HTTP
Mobile App (paziente)
      │  MQTT subscribe (notifiche)
      ▼
HiveMQ Cloud
```

Il broker è **HiveMQ Cloud** su `4a9747976f4d480f8994db0b416ae029.s1.eu.hivemq.cloud`.
- Laravel si connette via TLS (porta **8883**) usando `php-mqtt/client`
- L'ESP32 si connette via Wi-Fi con TLS (porta 8883)
- La Mobile App può sottoscriversi direttamente al broker per ricevere notifiche in tempo reale

---

## Configurazione .env

```env
MQTT_HOST=4a9747976f4d480f8994db0b416ae029.s1.eu.hivemq.cloud
MQTT_PORT=8883
MQTT_USERNAME=Utente
MQTT_PASSWORD=Utente1!
MQTT_CLIENT_ID=smart-dispenser-web
MQTT_USE_TLS=true
MQTT_CLEAN_SESSION=true

MQTT_TOPIC_ROOT=smart-dispenser
MQTT_TOPIC_TELEMETRY_SUFFIX=events/telemetry
MQTT_TOPIC_DOSE_LOG_SUFFIX=events/dose-log
MQTT_TOPIC_STATUS_SUFFIX=status
```

> ⚠️ Non committare mai le credenziali reali su Git. Il file `.env` è già in `.gitignore`.

---

## Struttura dei topic MQTT

```
smart-dispenser/
├── {device_uid}/
│   ├── events/
│   │   ├── telemetry        ← ESP32 pubblica temperatura/umidità
│   │   └── dose-log         ← ESP32 pubblica eventi dose (presa, saltata, ecc.)
│   ├── status               ← ESP32 pubblica heartbeat / stato online
│   └── commands/
│       ├── dispense_now     ← Laravel pubblica → ESP32 sottoscrive
│       ├── pause_therapy    ← Laravel pubblica → ESP32 sottoscrive
│       ├── resume_therapy   ← Laravel pubblica → ESP32 sottoscrive
│       ├── sync_plan        ← Laravel pubblica → ESP32 sottoscrive
│       └── ping             ← Laravel pubblica → ESP32 sottoscrive
```

`{device_uid}` corrisponde al campo `device_uid` nel record `Dispenser` del database.
Esempio: `smart-dispenser/ESP32-A1B2C3/events/telemetry`

---

## Avviare il listener Laravel

Il listener è un Artisan command che si connette al broker e resta in ascolto sui topic dei dispenser.

```bash
# Avvio normale (loop infinito)
php artisan device:mqtt-listen

# Con topic root custom
php artisan device:mqtt-listen --topic-root=smart-dispenser

# Con timeout automatico (es. 60 secondi, utile per test)
php artisan device:mqtt-listen --max-seconds=60
```

Il listener sottoscrive automaticamente questi tre filtri wildcarded:
| Topic filter                              | Gestisce                            |
|-------------------------------------------|-------------------------------------|
| `smart-dispenser/+/events/telemetry`      | Telemetria sensori (temp/umidità)   |
| `smart-dispenser/+/events/dose-log`       | Eventi dose (presa/saltata/erogata) |
| `smart-dispenser/+/status`               | Heartbeat e stato online/offline    |

### Avvio in produzione / sviluppo continuo

In sviluppo il listener va avviato come processo separato.
Con `composer dev` gira già `php artisan queue:listen` — **il listener MQTT è distinto**, va avviato a parte:

```bash
# Finestra terminale separata
php artisan device:mqtt-listen
```

In produzione usare **Supervisor** per mantenerlo sempre attivo:

```ini
[program:mqtt-listener]
command=php /path/to/artisan device:mqtt-listen
autostart=true
autorestart=true
stderr_logfile=/var/log/mqtt-listener.err.log
stdout_logfile=/var/log/mqtt-listener.out.log
```

---

## Inviare comandi dal pannello admin

Laravel pubblica comandi verso i dispenser tramite il `MqttPublisher` service.
La rotta è:

```
POST /dispensers/{dispenser}/mqtt-command
```

Payload form:
```
command  = dispense_now | pause_therapy | resume_therapy | sync_plan | ping
payload  = {"slot": 1}   (JSON opzionale)
```

Il payload inviato sul broker ha questa forma:
```json
{
  "command": "dispense_now",
  "issued_at": "2025-01-01T12:00:00+00:00",
  "payload": {
    "slot": 1
  }
}
```

Topic pubblicato: `smart-dispenser/{device_uid}/commands/dispense_now`

---

## Payload attesi dal listener

### events/telemetry
```json
{
  "temperature": 22.5,
  "humidity": 55.3,
  "recorded_at": "2025-01-01T12:00:00+00:00"
}
```
> `recorded_at` è opzionale: se assente viene usato `now()`.
> Sono supportati anche i campi italiani `temperatura` e `umidita` (normalizzati automaticamente).

**Soglie**: il sistema controlla automaticamente le soglie configurate sui farmaci del paziente e genera Alert se violate.

---

### events/dose-log
```json
{
  "status": "Taken",
  "therapy_plan_id": 3,
  "medicine_id": 7,
  "scheduled_for": "2025-01-01T08:00:00+00:00",
  "event_at": "2025-01-01T08:02:11+00:00",
  "notes": null
}
```

Valori validi per `status`:
| Valore      | Significato                          |
|-------------|--------------------------------------|
| `Pending`   | In attesa di erogazione              |
| `Dispensed` | Erogato dal dispenser                |
| `Taken`     | Confermato assunto dal paziente      |
| `Missed`    | Non assunto → genera Alert High      |
| `Snoozed`   | Posticipato                          |
| `Skipped`   | Saltato intenzionalmente             |

---

### status
```json
{
  "is_online": true,
  "last_seen_at": "2025-01-01T12:00:00+00:00"
}
```

---

## Aggiungere un nuovo dispenser

1. Dal pannello admin → **Dispensers** → Crea nuovo
2. Imposta `device_uid` (es. `ESP32-A1B2C3`) — deve coincidere con quello programmato nell'ESP32
3. Il campo `mqtt_base_topic` è opzionale: se vuoto il sistema costruisce il topic come `smart-dispenser/{device_uid}`
4. L'`api_token` viene usato per autenticazione REST API dall'ESP32

---

## Test rapido con MQTT Explorer / MQTTX

1. Host: `4a9747976f4d480f8994db0b416ae029.s1.eu.hivemq.cloud`
2. Porta: `8883` (TLS abilitato)
3. Username: `Utente` / Password: `Utente1!`
4. Subscribe a `smart-dispenser/#` per vedere tutto
5. Pubblica su `smart-dispenser/TEST-001/events/telemetry`:
   ```json
   {"temperature": 24.1, "humidity": 60.0}
   ```
6. Verifica nei log Laravel che la telemetria viene acquisita
