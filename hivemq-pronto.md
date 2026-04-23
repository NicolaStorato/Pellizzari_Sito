# HiveMQ Pronto - Guida Operativa per Questo Progetto

Questa guida ti fa configurare HiveMQ e collegarlo al progetto `Pellizzari_Sito` in modo pratico.

## 1) Flusso che andremo ad attivare

1. Il dispositivo pubblica eventi su HiveMQ (telemetria, dose-log, status).
2. Laravel ascolta quei topic con `php artisan device:mqtt-listen`.
3. Laravel salva i dati su DB e aggiorna dashboard/log/alert.
4. Dal pannello Laravel puoi inviare comandi MQTT al dispositivo.

## 2) Crea il broker su HiveMQ Cloud

1. Vai su `https://www.hivemq.com/` e crea account.
2. Crea un cluster HiveMQ Cloud (Starter o Serverless).
3. Quando il cluster e pronto, apri `Manage Cluster`.
4. In `Overview` recupera:
   - `URL` (host del broker)
   - `Port` (porta MQTT TLS)
   - `WebSocket Port` (porta WSS, utile per web client)

Nota:
- In HiveMQ Cloud le connessioni sono sicure TLS.
- Usa l'host senza prefisso (`mqtt://` o `https://`).

## 3) Crea credenziali MQTT

1. Apri `Access Management`.
2. Crea una coppia `username/password` per i client MQTT.
3. Assegna permessi publish+subscribe (o ruolo equivalente).
4. Salva credenziali in un posto sicuro.

## 4) Configura Laravel (`.env`)

Nel file `.env` imposta:

```dotenv
MQTT_HOST=tuo-cluster-url.hivemq.cloud
MQTT_PORT=8883
MQTT_USERNAME=tuo_username_mqtt
MQTT_PASSWORD=tua_password_mqtt
MQTT_CLIENT_ID=smart-dispenser-web
MQTT_USE_TLS=true
MQTT_CLEAN_SESSION=true

MQTT_TOPIC_ROOT=smart-dispenser
MQTT_TOPIC_TELEMETRY_SUFFIX=events/telemetry
MQTT_TOPIC_DOSE_LOG_SUFFIX=events/dose-log
MQTT_TOPIC_STATUS_SUFFIX=status
```

Poi esegui:

```powershell
php artisan config:clear
```

## 5) Configura almeno un dispenser nel portale

Dal menu `Dispenser` crea/modifica un dispositivo con:

- `device_uid` univoco (esempio: `device-001`)
- `api_token` (serve alle API dispositivo)
- `mqtt_base_topic` opzionale

Topic effettivi usati dal progetto:

- Telemetria: `<root>/<device_uid>/events/telemetry`
- Dose log: `<root>/<device_uid>/events/dose-log`
- Status: `<root>/<device_uid>/status`
- Comandi da Laravel a device: `<base-topic>/commands/<command>`

Dove:

- `<root>` = `MQTT_TOPIC_ROOT`
- `<base-topic>` = `mqtt_base_topic` del dispenser, altrimenti `<root>/<device_uid>`

## 6) Avvia l'ascolto MQTT su Laravel

Per test in foreground:

```powershell
php artisan device:mqtt-listen
```

Per test limitato (es. 60 secondi):

```powershell
php artisan device:mqtt-listen --max-seconds=60
```

Per produzione/dev continuo via scheduler:

```powershell
php artisan schedule:work
```

(`routes/console.php` ha gia una schedule che rilancia il listener ogni minuto.)

## 7) Come "vedere" il broker e i messaggi

### Opzione A (consigliata): HiveMQ Cloud Web Client

Dal quick start di HiveMQ Cloud puoi aprire il web client integrato e:

1. Connetterti con le credenziali MQTT.
2. Fare subscribe a:
   - `smart-dispenser/+/events/telemetry`
   - `smart-dispenser/+/events/dose-log`
   - `smart-dispenser/+/status`
   - `smart-dispenser/+/commands/+`
3. Pubblicare payload di test.

### Opzione B: MQTT WebSocket Client HiveMQ

Usa `https://www.hivemq.com/demos/websocket-client/` e inserisci:

- Host: URL cluster
- Port: WebSocket Port del cluster
- SSL: ON
- Username/Password MQTT

Poi fai subscribe/publish come sopra.

## 8) Payload pronti per test

### Telemetria

Topic:

```text
smart-dispenser/device-001/events/telemetry
```

Payload:

```json
{
  "temperature": 24.7,
  "humidity": 48.3,
  "recorded_at": "2026-04-22T18:30:00+02:00"
}
```

### Dose log

Topic:

```text
smart-dispenser/device-001/events/dose-log
```

Payload:

```json
{
  "status": "taken",
  "scheduled_for": "2026-04-22T20:00:00+02:00",
  "event_at": "2026-04-22T20:02:00+02:00",
  "notes": "Dose assunta regolarmente"
}
```

### Status

Topic:

```text
smart-dispenser/device-001/status
```

Payload:

```json
{
  "is_online": true,
  "last_seen_at": "2026-04-22T18:35:00+02:00"
}
```

## 9) Verifica propagazione end-to-end

1. Lascia aperto `php artisan device:mqtt-listen`.
2. Pubblica uno dei payload di test.
3. Controlla nel portale:
   - `Log Sensori`
   - `Alert`
   - `Dashboard`
   - dettaglio `Dispenser`

Se vuoi monitorare errori backend:

```powershell
php artisan pail
```

## 10) Problemi tipici

1. Non si connette al broker:
   - host/porta sbagliati
   - `MQTT_USE_TLS` non coerente con porta cloud
   - credenziali MQTT errate
2. Non arrivano dati su Laravel:
   - listener non avviato
   - topic pubblicato diverso dal pattern previsto
   - `device_uid` non allineato al topic
3. Comandi non arrivano al device:
   - `mqtt_base_topic` non corretto sul dispenser
   - dispositivo non subscribe su `.../commands/+`

## Riferimenti ufficiali HiveMQ

- Quick Start HiveMQ Cloud: `https://docs.hivemq.com/hivemq-cloud/quick-start-guide.html`
- Auth e credenziali: `https://docs.hivemq.com/hivemq-cloud/authn-authz.html`
- WebSocket client demo: `https://www.hivemq.com/demos/websocket-client/`
