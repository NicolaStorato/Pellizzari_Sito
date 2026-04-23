<?php

namespace Database\Seeders;

use App\Models\Alert;
use App\Models\Appointment;
use App\Models\Dispenser;
use App\Models\DoseLog;
use App\Models\Medicine;
use App\Models\PatientAssignment;
use App\Models\SensorLog;
use App\Models\TherapyPlan;
use App\Models\TherapyPlanSchedule;
use App\Models\User;
use App\UserRole;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = User::factory()->admin()->create([
            'name' => 'Admin Smart Dispenser',
            'email' => 'admin@smartdispenser.local',
            'password' => 'password',
        ]);

        $doctors = collect([
            ['name' => 'Dr. Marco Rossi', 'email' => 'doctor.marco@smartdispenser.local'],
            ['name' => 'Dr.ssa Giulia Verdi', 'email' => 'doctor.giulia@smartdispenser.local'],
            ['name' => 'Dr. Luca Neri', 'email' => 'doctor.luca@smartdispenser.local'],
        ])->map(static fn (array $doctor): User => User::factory()->doctor()->create([
            'name' => $doctor['name'],
            'email' => $doctor['email'],
            'password' => 'password',
        ]));

        $caregivers = collect([
            ['name' => 'Anna Bianchi', 'email' => 'caregiver.anna@smartdispenser.local'],
            ['name' => 'Paolo Bianchi', 'email' => 'caregiver.paolo@smartdispenser.local'],
            ['name' => 'Sara Conti', 'email' => 'caregiver.sara@smartdispenser.local'],
            ['name' => 'Marco Conti', 'email' => 'caregiver.marco@smartdispenser.local'],
            ['name' => 'Elisa Ferri', 'email' => 'caregiver.elisa@smartdispenser.local'],
        ])->map(static fn (array $caregiver): User => User::factory()->caregiver()->create([
            'name' => $caregiver['name'],
            'email' => $caregiver['email'],
            'password' => 'password',
        ]));

        $patients = collect([
            ['name' => 'Mario Bianchi', 'email' => 'patient.mario@smartdispenser.local'],
            ['name' => 'Lucia Conti', 'email' => 'patient.lucia@smartdispenser.local'],
            ['name' => 'Giovanni Ferri', 'email' => 'patient.giovanni@smartdispenser.local'],
        ])->map(static fn (array $patient): User => User::factory()->patient()->create([
            'name' => $patient['name'],
            'email' => $patient['email'],
            'password' => 'password',
        ]));

        $careTeamMap = [
            [
                'patient' => $patients[0],
                'doctor' => $doctors[0],
                'caregivers' => [$caregivers[0], $caregivers[1]],
            ],
            [
                'patient' => $patients[1],
                'doctor' => $doctors[1],
                'caregivers' => [$caregivers[2]],
            ],
            [
                'patient' => $patients[2],
                'doctor' => $doctors[1],
                'caregivers' => [$caregivers[3]],
            ],
        ];

        foreach ($careTeamMap as $mapping) {
            /** @var User $patient */
            $patient = $mapping['patient'];

            /** @var User $doctor */
            $doctor = $mapping['doctor'];

            /** @var array<int, User> $assignedCaregivers */
            $assignedCaregivers = $mapping['caregivers'];

            PatientAssignment::query()->create([
                'patient_id' => $patient->id,
                'member_id' => $doctor->id,
                'assigned_by_id' => $admin->id,
                'role' => UserRole::Doctor->value,
            ]);

            foreach ($assignedCaregivers as $caregiver) {
                PatientAssignment::query()->create([
                    'patient_id' => $patient->id,
                    'member_id' => $caregiver->id,
                    'assigned_by_id' => $admin->id,
                    'role' => UserRole::Caregiver->value,
                ]);
            }

            $dispenser = Dispenser::factory()->create([
                'patient_id' => $patient->id,
                'name' => 'Dispenser '.$patient->name,
            ]);

            $medicines = Medicine::factory()->count(2)->create([
                'patient_id' => $patient->id,
                'created_by_id' => $doctor->id,
            ]);

            foreach ($medicines as $medicine) {
                $therapyPlan = TherapyPlan::factory()->create([
                    'patient_id' => $patient->id,
                    'doctor_id' => $doctor->id,
                    'medicine_id' => $medicine->id,
                ]);

                TherapyPlanSchedule::query()->create([
                    'therapy_plan_id' => $therapyPlan->id,
                    'scheduled_time' => '08:00',
                    'week_days' => [1, 2, 3, 4, 5, 6, 7],
                    'timezone' => 'Europe/Rome',
                ]);

                TherapyPlanSchedule::query()->create([
                    'therapy_plan_id' => $therapyPlan->id,
                    'scheduled_time' => '20:00',
                    'week_days' => [1, 2, 3, 4, 5, 6, 7],
                    'timezone' => 'Europe/Rome',
                ]);

                DoseLog::factory()->count(8)->create([
                    'patient_id' => $patient->id,
                    'dispenser_id' => $dispenser->id,
                    'therapy_plan_id' => $therapyPlan->id,
                    'medicine_id' => $medicine->id,
                ]);
            }

            SensorLog::factory()->count(24)->create([
                'patient_id' => $patient->id,
                'dispenser_id' => $dispenser->id,
            ]);

            Alert::factory()->count(3)->create([
                'patient_id' => $patient->id,
                'dispenser_id' => $dispenser->id,
            ]);

            Appointment::factory()->create([
                'patient_id' => $patient->id,
                'doctor_id' => $doctor->id,
                'scheduled_at' => now()->addDays(2)->setTime(10, 30),
                'status' => Appointment::STATUS_PENDING,
                'patient_notes' => 'Controllo periodico terapia.',
            ]);
        }
    }
}
