<?php

use App\Models\PatientAssignment;
use App\Models\User;
use App\UserRole;
use Illuminate\Support\Facades\Hash;

test('admin can register a doctor from user management', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)
        ->from(route('user-management.index'))
        ->post(route('user-management.store'), [
            'role' => UserRole::Doctor->value,
            'name' => 'Dr. Test Admin',
            'email' => 'doctor.from.admin@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'is_active' => '1',
        ]);

    $response->assertRedirect(route('user-management.index'));

    $doctor = User::query()->where('email', 'doctor.from.admin@example.com')->first();
    expect($doctor)->not->toBeNull();
    expect($doctor->role)->toBe(UserRole::Doctor);
});

test('doctor can register caregiver but cannot register another doctor', function () {
    $doctor = User::factory()->doctor()->create();

    $caregiverResponse = $this->actingAs($doctor)
        ->from(route('user-management.index'))
        ->post(route('user-management.store'), [
            'role' => UserRole::Caregiver->value,
            'name' => 'Familiare Test',
            'email' => 'caregiver.from.doctor@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

    $caregiverResponse->assertRedirect(route('user-management.index'));

    $createdCaregiver = User::query()->where('email', 'caregiver.from.doctor@example.com')->first();
    expect($createdCaregiver)->not->toBeNull();
    expect($createdCaregiver->role)->toBe(UserRole::Caregiver);

    $doctorResponse = $this->actingAs($doctor)
        ->from(route('user-management.index'))
        ->post(route('user-management.store'), [
            'role' => UserRole::Doctor->value,
            'name' => 'Doctor Non Consentito',
            'email' => 'doctor.not.allowed@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

    $doctorResponse->assertRedirect(route('user-management.index'));
    $doctorResponse->assertSessionHasErrors(['role']);

    expect(
        User::query()->where('email', 'doctor.not.allowed@example.com')->exists()
    )->toBeFalse();
});

test('patient cannot use legacy care-team linking actions', function () {
    $patient = User::factory()->patient()->create();
    $doctor = User::factory()->doctor()->create();
    $caregiver = User::factory()->caregiver()->create();

    $this->actingAs($patient)
        ->postJson('/care-team/doctor', [
            'doctor_id' => $doctor->id,
        ])->assertNotFound();

    $this->actingAs($patient)
        ->postJson('/care-team/caregiver', [
            'caregiver_id' => $caregiver->id,
        ])->assertNotFound();

    expect(
        PatientAssignment::query()
            ->where('patient_id', $patient->id)
            ->where('member_id', $doctor->id)
            ->where('role', UserRole::Doctor->value)
            ->exists()
    )->toBeFalse();

    expect(
        PatientAssignment::query()
            ->where('patient_id', $patient->id)
            ->where('member_id', $caregiver->id)
            ->where('role', UserRole::Caregiver->value)
            ->exists()
    )->toBeFalse();
});

test('doctor can update caregiver password and details from user management', function () {
    $doctor = User::factory()->doctor()->create();
    $caregiver = User::factory()->caregiver()->create([
        'password' => Hash::make('oldpassword'),
        'is_active' => true,
    ]);

    $response = $this->actingAs($doctor)
        ->from(route('user-management.edit', $caregiver))
        ->patch(route('user-management.update', $caregiver), [
            'name' => 'Familiare Aggiornato',
            'email' => 'caregiver.updated@example.com',
            'phone' => '0123456789',
            'address' => 'Via Nuova 1',
            'date_of_birth' => $caregiver->date_of_birth?->format('Y-m-d'),
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
            'is_active' => '1',
            'role' => $caregiver->role->value,
        ]);

    $response->assertRedirect(route('user-management.index'));

    $caregiver->refresh();

    expect($caregiver->name)->toBe('Familiare Aggiornato');
    expect($caregiver->email)->toBe('caregiver.updated@example.com');
    expect($caregiver->phone)->toBe('0123456789');
    expect(Hash::check('newpassword123', $caregiver->password))->toBeTrue();
});

test('caregiver cannot use legacy care-team linking actions', function () {
    $caregiver = User::factory()->caregiver()->create();
    $patient = User::factory()->patient()->create();

    $response = $this->actingAs($caregiver)
        ->postJson('/care-team/patient', [
            'patient_id' => $patient->id,
        ]);

    $response->assertNotFound();

    expect(
        PatientAssignment::query()
            ->where('patient_id', $patient->id)
            ->where('member_id', $caregiver->id)
            ->where('role', UserRole::Caregiver->value)
            ->exists()
    )->toBeFalse();
});
