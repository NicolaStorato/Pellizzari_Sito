# Guida Operativa Terapie + HiveMQ

Data riferimento: 23-04-2026

## 1) Come fare arrivare una terapia al dispositivo

Nel progetto attuale il flusso corretto e questo:

1. Il dottore crea o modifica il piano terapia dal portale web.
2. Il dispositivo deve leggere i piani via API: `GET /api/v1/device/plans` con header `X-Device-Token`.
3. Se vuoi forzare subito la sincronizzazione, invia il comando MQTT `sync_plan` dalla pagina Dispenser.
4. Il firmware del dispositivo, quando riceve `sync_plan`, deve richiamare `GET /api/v1/device/plans`.

Nota importante:
- La creazione del piano terapia NON aggiorna automaticamente il dispositivo se il firmware non esegue la chiamata all'API piani.
- La Dashboard web Laravel non e realtime via WebSocket: per vedere i nuovi numeri subito, aggiorna la pagina.

## 2) Subscriber HiveMQ da usare (quelli che ti servono davvero)

Dallo screenshot il tuo Web Client e connesso ma ha `Topic Subscriptions = 0`, quindi non vede nulla.

### 2.1 Subscriber generici consigliati

Inserisci questi topic nel Web Client HiveMQ:

- `smart-dispenser/+/events/telemetry`
- `smart-dispenser/+/events/dose-log`
- `smart-dispenser/+/status`
- `smart-dispenser/+/commands/+`

Per debug totale puoi usare anche:

- `#`

### 2.2 Subscriber specifici dei dispositivi presenti ora nel DB

Base topic attuali:

- `smart-dispenser/bsfpwtw1`
- `smart-dispenser/gpnr8tzv`
- `smart-dispenser/anadwaic`
- `smart-dispenser/device-001`

Per ogni base topic puoi sottoscrivere:

- `<base>/events/telemetry`
- `<base>/events/dose-log`
- `<base>/status`
- `<base>/commands/+`

## 3) Topic che il backend sottoscrive automaticamente

Il comando Laravel `php artisan device:mqtt-listen` sottoscrive automaticamente:

- `MQTT_TOPIC_ROOT/+/MQTT_TOPIC_TELEMETRY_SUFFIX`
- `MQTT_TOPIC_ROOT/+/MQTT_TOPIC_DOSE_LOG_SUFFIX`
- `MQTT_TOPIC_ROOT/+/MQTT_TOPIC_STATUS_SUFFIX`

Con la tua config attuale diventano:

- `smart-dispenser/+/events/telemetry`
- `smart-dispenser/+/events/dose-log`
- `smart-dispenser/+/status`

## 4) JSON telemetria: cosa e accettato adesso

Il backend ora accetta entrambe le varianti di chiavi:

### Variante consigliata

```json
{
  "temperature": 22.5,
  "humidity": 48.3,
  "recorded_at": "2026-04-23T16:30:00+02:00"
}
```

### Variante compatibile con il testo del tuo compagno

```json
{
  "temperatura": 22.5,
  "umidita": 48.3,
  "sensore_id": "ESP32_01"
}
```

Attenzione:
- `sensore_id` non sostituisce il topic. Il backend identifica prima il dispositivo dal topic MQTT.
- Quindi il topic deve comunque essere coerente con il formato del progetto.

## 5) Verifica rapida end-to-end

1. Avvia listener:

```powershell
php artisan device:mqtt-listen --max-seconds=300
```

2. Nel Web Client HiveMQ sottoscrivi `#`.
3. Pubblica telemetria su un topic valido, per esempio:

Topic:

```text
smart-dispenser/device-001/events/telemetry
```

Payload:

```json
{
  "temperature": 24.7,
  "humidity": 48.3
}
```

4. Verifica nel portale: Dashboard, Log Sensori, Alert.

## 6) Nota su `infomediot/sensori`

Il topic `infomediot/sensori` citato nel testo del compagno non e allineato alla configurazione corrente del backend Laravel.

Per evitare problemi, usa i topic del progetto:

- `smart-dispenser/<device_uid>/events/telemetry`
- `smart-dispenser/<device_uid>/events/dose-log`
- `smart-dispenser/<device_uid>/status`
