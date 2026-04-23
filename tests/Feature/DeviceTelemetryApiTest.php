<?php

use App\Models\Alert;
use App\Models\Dispenser;
use App\Models\DoseLog;
use App\Models\Medicine;
use App\Models\SensorLog;
use App\Models\User;

test('device telemetry is stored and creates alert when thresholds are exceeded', function () {
    $patient = User::factory()->patient()->create();
    $dispenser = Dispenser::factory()->create([
        'patient_id' => $patient->id,
        'api_token' => 'device-token-123',
    ]);

    Medicine::factory()->create([
        'patient_id' => $patient->id,
        'minimum_temperature' => 15,
        'maximum_temperature' => 25,
        'minimum_humidity' => 35,
        'maximum_humidity' => 60,
    ]);

    $response = $this
        ->withHeader('X-Device-Token', $dispenser->api_token)
        ->postJson(route('api.device.telemetry'), [
            'temperature' => 30.2,
            'humidity' => 76.5,
        ]);

    $response->assertCreated();

    $sensorLog = SensorLog::query()->first();

    expect($sensorLog)->not->toBeNull();
    expect($sensorLog->threshold_exceeded)->toBeTrue();

    expect(Alert::query()->where('patient_id', $patient->id)->count())->toBeGreaterThan(0);
});

test('missed dose reported by device creates a missed dose alert', function () {
    $patient = User::factory()->patient()->create();
    $dispenser = Dispenser::factory()->create([
        'patient_id' => $patient->id,
        'api_token' => 'device-token-missed',
    ]);

    $response = $this
        ->withHeader('X-Device-Token', $dispenser->api_token)
        ->postJson(route('api.device.dose-logs'), [
            'status' => DoseLog::STATUS_MISSED,
            'notes' => 'Dose non confermata entro 15 minuti',
        ]);

    $response->assertCreated();

    expect(
        Alert::query()
            ->where('patient_id', $patient->id)
            ->where('type', Alert::TYPE_MISSED_DOSE)
            ->exists()
    )->toBeTrue();
});

test('device telemetry accepts italian payload keys', function () {
    $patient = User::factory()->patient()->create();
    $dispenser = Dispenser::factory()->create([
        'patient_id' => $patient->id,
        'api_token' => 'device-token-it',
    ]);

    $response = $this
        ->withHeader('X-Device-Token', $dispenser->api_token)
        ->postJson(route('api.device.telemetry'), [
            'temperatura' => 22.5,
            'umidita' => 48.3,
        ]);

    $response->assertCreated();

    $sensorLog = SensorLog::query()->latest('id')->first();

    expect($sensorLog)->not->toBeNull();
    expect((float) $sensorLog->temperature)->toBe(22.5);
    expect((float) $sensorLog->humidity)->toBe(48.3);
});
