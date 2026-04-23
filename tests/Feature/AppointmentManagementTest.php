<?php

use App\Models\Appointment;
use App\Models\PatientAssignment;
use App\Models\User;
use App\UserRole;

test('patient can request appointment with assigned doctor', function () {
    $patient = User::factory()->patient()->create();
    $doctor = User::factory()->doctor()->create();

    PatientAssignment::query()->create([
        'patient_id' => $patient->id,
        'member_id' => $doctor->id,
        'assigned_by_id' => $doctor->id,
        'role' => UserRole::Doctor->value,
    ]);

    $response = $this->actingAs($patient)
        ->from(route('appointments.index'))
        ->post(route('appointments.store'), [
            'doctor_id' => $doctor->id,
            'scheduled_at' => now()->addDays(3)->setTime(11, 0)->toDateTimeString(),
            'patient_notes' => 'Controllo periodico.',
        ]);

    $response->assertRedirect(route('appointments.index'));

    expect(
        Appointment::query()
            ->where('patient_id', $patient->id)
            ->where('doctor_id', $doctor->id)
            ->where('status', Appointment::STATUS_PENDING)
            ->exists()
    )->toBeTrue();
});

test('patient cannot request appointment with non assigned doctor', function () {
    $patient = User::factory()->patient()->create();
    $doctor = User::factory()->doctor()->create();

    $response = $this->actingAs($patient)
        ->from(route('appointments.index'))
        ->post(route('appointments.store'), [
            'doctor_id' => $doctor->id,
            'scheduled_at' => now()->addDays(2)->setTime(9, 15)->toDateTimeString(),
        ]);

    $response->assertRedirect(route('appointments.index'));
    $response->assertSessionHasErrors(['doctor_id']);
});

test('doctor can update appointment status', function () {
    $patient = User::factory()->patient()->create();
    $doctor = User::factory()->doctor()->create();

    $appointment = Appointment::factory()->create([
        'patient_id' => $patient->id,
        'doctor_id' => $doctor->id,
        'status' => Appointment::STATUS_PENDING,
    ]);

    $response = $this->actingAs($doctor)
        ->from(route('doctor-appointments.index'))
        ->patch(route('doctor-appointments.update', $appointment), [
            'status' => Appointment::STATUS_CONFIRMED,
            'doctor_notes' => 'Confermato in ambulatorio.',
        ]);

    $response->assertRedirect(route('doctor-appointments.index'));

    $appointment->refresh();

    expect($appointment->status)->toBe(Appointment::STATUS_CONFIRMED);
    expect($appointment->doctor_notes)->toBe('Confermato in ambulatorio.');
    expect($appointment->confirmed_at)->not->toBeNull();
});

test('caregiver cannot access appointment management pages', function () {
    $caregiver = User::factory()->caregiver()->create();

    $this->actingAs($caregiver)
        ->getJson(route('appointments.index'))
        ->assertForbidden();

    $this->actingAs($caregiver)
        ->getJson(route('doctor-appointments.index'))
        ->assertForbidden();
});

test('patient cannot access doctor clinical monitoring pages', function () {
    $patient = User::factory()->patient()->create();

    $this->actingAs($patient)
        ->getJson(route('sensor-logs.index'))
        ->assertForbidden();

    $this->actingAs($patient)
        ->getJson(route('alerts.index'))
        ->assertForbidden();
});
