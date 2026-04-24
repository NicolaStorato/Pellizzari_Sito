# MQTT Schedule API — Documentazione per firmware ESP32

## Broker
- Host: 7fb87909f8654170b12ec51736fcb6e0.s1.eu.hivemq.cloud
- Port: 8883 (TLS obbligatorio)
- Username: Utente
- Password: Utente1!

---

## Come ottenere la schedule settimanale

Il backend Laravel pubblica automaticamente la schedule sul topic di risposta
ogni volta che l'ESP32 pubblica sul topic `status`.

### Flusso completo

1. ESP32 pubblica un messaggio JSON sul topic status:

```
Topic (PUBLISH):  smart-dispenser/ESP32-V0JWWIHEIC/status
Payload:          {"is_online": true}
```

2. Laravel riceve il messaggio, aggiorna lo stato del dispositivo e pubblica
   automaticamente la schedule settimanale sul topic di risposta:

```
Topic (SUBSCRIBE): esp32/schedule_response/ESP32-V0JWWIHEIC
```

### Formato della risposta

```json
{
  "days": [
    { "date": "2026-04-24", "time": "08:00" },
    { "date": "2026-04-25", "time": "08:00" },
    { "date": "2026-04-26", "time": "08:00" },
    { "date": "2026-04-27", "time": "08:00" },
    { "date": "2026-04-28", "time": "08:00" },
    { "date": "2026-04-29", "time": "08:00" },
    { "date": "2026-04-30", "time": "08:00" }
  ]
}
```

- `date`: stringa ISO 8601, formato `YYYY-MM-DD`
- `time`: stringa orario, formato `HH:MM` (24h)
- L'array copre sempre i prossimi 7 giorni (da oggi incluso)
- L'array è ordinato per data e orario crescente
- Se il paziente non ha terapie attive, `days` è un array vuoto `[]`
- Ogni elemento rappresenta un singolo slot di erogazione

### Richiesta esplicita (opzionale)

In alternativa al trigger via status, l'ESP32 può richiedere la schedule
esplicitamente su un topic dedicato:

```
Topic (PUBLISH):  esp32/schedule_request
Payload:          {"device_uid": "ESP32-V0JWWIHEIC"}
```

La risposta arriva sullo stesso topic standard:

```
Topic (SUBSCRIBE): esp32/schedule_response/ESP32-V0JWWIHEIC
```

---

## Riepilogo topic ESP32

| Direzione       | Topic                                            | Scopo                          |
|-----------------|--------------------------------------------------|--------------------------------|
| ESP32 → Broker  | smart-dispenser/ESP32-V0JWWIHEIC/status          | Heartbeat → trigger schedule   |
| Broker → ESP32  | esp32/schedule_response/ESP32-V0JWWIHEIC         | Ricezione schedule settimanale |
| ESP32 → Broker  | esp32/schedule_request                           | Richiesta schedule esplicita   |
| ESP32 → Broker  | smart-dispenser/ESP32-V0JWWIHEIC/events/telemetry| Invio temperatura/umidità      |
| ESP32 → Broker  | smart-dispenser/ESP32-V0JWWIHEIC/events/dose-log | Log erogazione dose            |
| Broker → ESP32  | smart-dispenser/ESP32-V0JWWIHEIC/commands        | Ricezione comandi dal server   |

---

## Logica di implementazione consigliata (lato ESP32)

```
Al boot:
  1. Connetti al broker MQTT (TLS, porta 8883)
  2. Sottoscrivi esp32/schedule_response/ESP32-V0JWWIHEIC
  3. Pubblica {"is_online": true} su smart-dispenser/ESP32-V0JWWIHEIC/status
  4. Attendi il messaggio sul topic schedule_response
  5. Parsa il JSON, salva l'array "days" in memoria/NVS
  6. Configura il timer RTC per ogni {date, time} ricevuto

Periodicamente (ogni ora o al riconnect):
  - Ripubblica su /status per ricevere la schedule aggiornata
```
