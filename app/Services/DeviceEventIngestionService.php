<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\Dispenser;
use App\Models\DoseLog;
use App\Models\Medicine;
use App\Models\SensorLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DeviceEventIngestionService
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *     sensor_log: SensorLog|null,
     *     violations: Collection<int, array<string, mixed>>
     * }
     */
    public function ingestTelemetry(Dispenser $dispenser, array $payload): array
    {
        $recordedAt = isset($payload['recorded_at'])
            ? Carbon::parse((string) $payload['recorded_at'])
            : now();

        $temperature = (float) $payload['temperature'];
        $humidity = (float) $payload['humidity'];

        // Aggiorna sempre lo stato online del dispenser, indipendentemente dal throttle
        $dispenser->update([
            'last_seen_at' => $recordedAt,
            'is_online' => true,
        ]);

        // Salva nel DB al massimo 1 log di telemetria per ora per dispenser
        $alreadyLoggedThisHour = SensorLog::query()
            ->where('dispenser_id', $dispenser->id)
            ->where('recorded_at', '>=', now()->startOfHour())
            ->exists();

        if ($alreadyLoggedThisHour) {
            return ['sensor_log' => null, 'violations' => collect()];
        }

        $violations = $this->detectThresholdViolations(
            patientId: $dispenser->patient_id,
            temperature: $temperature,
            humidity: $humidity,
        );

        $sensorLog = SensorLog::query()->create([
            'dispenser_id' => $dispenser->id,
            'patient_id' => $dispenser->patient_id,
            'temperature' => $temperature,
            'humidity' => $humidity,
            'threshold_exceeded' => $violations->isNotEmpty(),
            'threshold_violations' => $violations->values()->all(),
            'recorded_at' => $recordedAt,
        ]);

        foreach ($violations as $violation) {
            $alreadyOpen = Alert::query()
                ->where('patient_id', $dispenser->patient_id)
                ->where('dispenser_id', $dispenser->id)
                ->where('type', $violation['type'])
                ->open()
                ->where('triggered_at', '>=', now()->subHour())
                ->exists();

            if (! $alreadyOpen) {
                Alert::query()->create([
                    'patient_id' => $dispenser->patient_id,
                    'dispenser_id' => $dispenser->id,
                    'sensor_log_id' => $sensorLog->id,
                    'type' => $violation['type'],
                    'severity' => $violation['severity'],
                    'message' => $violation['message'],
                    'triggered_at' => $recordedAt,
                    'notified_caregiver' => false,
                    'notified_doctor' => false,
                ]);
            }
        }

        return [
            'sensor_log' => $sensorLog,
            'violations' => $violations->values(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function ingestDoseLog(Dispenser $dispenser, array $payload): DoseLog
    {
        $eventAt = isset($payload['event_at']) ? Carbon::parse((string) $payload['event_at']) : now();

        $doseLog = DoseLog::query()->create([
            'patient_id' => $dispenser->patient_id,
            'dispenser_id' => $dispenser->id,
            'therapy_plan_id' => $payload['therapy_plan_id'] ?? null,
            'medicine_id' => $payload['medicine_id'] ?? null,
            'status' => $payload['status'],
            'source' => 'Device',
            'scheduled_for' => $payload['scheduled_for'] ?? null,
            'event_at' => $eventAt,
            'notes' => $payload['notes'] ?? null,
        ]);

        if ($doseLog->status === DoseLog::STATUS_MISSED) {
            Alert::query()->create([
                'patient_id' => $dispenser->patient_id,
                'dispenser_id' => $dispenser->id,
                'dose_log_id' => $doseLog->id,
                'type' => Alert::TYPE_MISSED_DOSE,
                'severity' => 'High',
                'message' => 'Dose non assunta dal paziente.',
                'triggered_at' => $eventAt,
            ]);
        }

        $dispenser->update([
            'last_seen_at' => $eventAt,
            'is_online' => true,
        ]);

        return $doseLog;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function ingestStatus(Dispenser $dispenser, array $payload): void
    {
        $isOnline = filter_var(
            $payload['is_online'] ?? true,
            FILTER_VALIDATE_BOOL,
            FILTER_NULL_ON_FAILURE,
        );
        $lastSeenAt = isset($payload['last_seen_at'])
            ? Carbon::parse((string) $payload['last_seen_at'])
            : now();

        $dispenser->update([
            'is_online' => $isOnline ?? true,
            'last_seen_at' => $lastSeenAt,
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function detectThresholdViolations(int $patientId, float $temperature, float $humidity): Collection
    {
        $medicines = Medicine::query()
            ->where('patient_id', $patientId)
            ->get([
                'minimum_temperature',
                'maximum_temperature',
                'minimum_humidity',
                'maximum_humidity',
            ]);

        if ($medicines->isEmpty()) {
            return collect();
        }

        $violations = collect();
        $minTemperature = $medicines->pluck('minimum_temperature')->filter(static fn ($value): bool => $value !== null)->max();
        $maxTemperature = $medicines->pluck('maximum_temperature')->filter(static fn ($value): bool => $value !== null)->min();
        $minHumidity = $medicines->pluck('minimum_humidity')->filter(static fn ($value): bool => $value !== null)->max();
        $maxHumidity = $medicines->pluck('maximum_humidity')->filter(static fn ($value): bool => $value !== null)->min();

        if ($minTemperature !== null && $temperature < (float) $minTemperature) {
            $violations->push([
                'type' => Alert::TYPE_TEMPERATURE,
                'severity' => 'High',
                'message' => 'Temperatura troppo bassa: '.$temperature.' C',
            ]);
        }

        if ($maxTemperature !== null && $temperature > (float) $maxTemperature) {
            $violations->push([
                'type' => Alert::TYPE_TEMPERATURE,
                'severity' => 'High',
                'message' => 'Temperatura troppo alta: '.$temperature.' C',
            ]);
        }

        if ($minHumidity !== null && $humidity < (float) $minHumidity) {
            $violations->push([
                'type' => Alert::TYPE_HUMIDITY,
                'severity' => 'Medium',
                'message' => 'Umidita troppo bassa: '.$humidity.'%',
            ]);
        }

        if ($maxHumidity !== null && $humidity > (float) $maxHumidity) {
            $violations->push([
                'type' => Alert::TYPE_HUMIDITY,
                'severity' => 'High',
                'message' => 'Umidita troppo alta: '.$humidity.'%',
            ]);
        }

        return $violations;
    }
}
