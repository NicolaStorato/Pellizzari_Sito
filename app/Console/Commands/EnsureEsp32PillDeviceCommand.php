<?php

namespace App\Console\Commands;

use App\Models\Dispenser;
use App\Models\Medicine;
use App\Models\PatientAssignment;
use App\Models\TherapyPlan;
use App\Models\TherapyPlanSchedule;
use App\Models\User;
use App\UserRole;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Garantisce che il dispositivo ESP32-PILL-001 esista nel database.
 *
 * Se manca, crea automaticamente:
 *   - 1 paziente  (Aldo Pellizzari)
 *   - 1 dottore   (Dr. Enrico Pellizzari)
 *   - 1 caregiver (Carla Pellizzari)
 *   - 1 dispenser (device_uid = ESP32-PILL-001) collegato al paziente
 *   - 1 medicina  di default per il paziente
 *   - 1 piano terapeutico attivo con orari mattina/sera
 *
 * Il comando e' idempotente: rieseguirlo non crea duplicati.
 */
class EnsureEsp32PillDeviceCommand extends Command
{
    protected $signature = 'device:ensure-esp32
                            {--device-uid=ESP32-PILL-001 : UID del dispenser da verificare/creare}
                            {--force : Stampa il riepilogo anche se il dispositivo esiste gia}';

    protected $description = 'Verifica che ESP32-PILL-001 esista nel DB e, se no, crea paziente/dottore/caregiver/dispenser/terapia';

    public function handle(): int
    {
        $deviceUid = (string) $this->option('device-uid');

        $this->info("=== Verifica dispositivo: {$deviceUid} ===");

        $dispenser = Dispenser::query()
            ->where('device_uid', $deviceUid)
            ->first();

        if ($dispenser !== null) {
            $this->info("OK  Dispenser [{$deviceUid}] gia presente nel database (ID: {$dispenser->id}).");

            // Controlla se ha terapie attive, e le crea se mancano
            $this->ensureTherapyPlans($dispenser);

            if ($this->option('force')) {
                $this->printSummary($dispenser);
            }

            return self::SUCCESS;
        }

        $this->warn("!!  Dispenser [{$deviceUid}] NON trovato. Avvio creazione automatica...");

        $dispenser = DB::transaction(function () use ($deviceUid): Dispenser {

            // -- Cerca o crea il paziente -----------------------------------------
            $patient = User::query()->firstOrCreate(
                ['email' => 'pellizzari.aldo@smartdispenser.local'],
                [
                    'name'      => 'Aldo Pellizzari',
                    'password'  => Hash::make(Str::random(24)),
                    'role'      => UserRole::Patient->value,
                    'is_active' => true,
                ],
            );
            $this->line("  Paziente   : {$patient->name} <{$patient->email}> (ID {$patient->id})");

            // -- Cerca o crea il dottore ------------------------------------------
            $doctor = User::query()->firstOrCreate(
                ['email' => 'pellizzari.enrico.dr@smartdispenser.local'],
                [
                    'name'      => 'Dr. Enrico Pellizzari',
                    'password'  => Hash::make(Str::random(24)),
                    'role'      => UserRole::Doctor->value,
                    'is_active' => true,
                ],
            );
            $this->line("  Dottore    : {$doctor->name} <{$doctor->email}> (ID {$doctor->id})");

            // -- Cerca o crea il caregiver ----------------------------------------
            $caregiver = User::query()->firstOrCreate(
                ['email' => 'pellizzari.carla@smartdispenser.local'],
                [
                    'name'      => 'Carla Pellizzari',
                    'password'  => Hash::make(Str::random(24)),
                    'role'      => UserRole::Caregiver->value,
                    'is_active' => true,
                ],
            );
            $this->line("  Caregiver  : {$caregiver->name} <{$caregiver->email}> (ID {$caregiver->id})");

            // -- Admin di sistema (per assigned_by_id) ----------------------------
            $admin = User::query()->where('role', UserRole::Admin->value)->first();

            // -- Assegnazioni care-team -------------------------------------------
            PatientAssignment::query()->firstOrCreate(
                ['patient_id' => $patient->id, 'member_id' => $doctor->id],
                ['assigned_by_id' => $admin?->id ?? $doctor->id, 'role' => UserRole::Doctor->value],
            );
            PatientAssignment::query()->firstOrCreate(
                ['patient_id' => $patient->id, 'member_id' => $caregiver->id],
                ['assigned_by_id' => $admin?->id ?? $doctor->id, 'role' => UserRole::Caregiver->value],
            );

            // -- Crea il dispenser ------------------------------------------------
            $topicRoot = trim((string) config('services.mqtt.topic_root', 'smart-dispenser'), '/');

            $dispenser = Dispenser::query()->create([
                'patient_id'      => $patient->id,
                'name'            => 'Smart Dispenser Pellizzari',
                'device_uid'      => $deviceUid,
                'api_token'       => Str::random(40),
                'mqtt_base_topic' => $topicRoot.'/'.$deviceUid,
                'is_active'       => true,
                'is_online'       => false,
            ]);
            $this->line("  Dispenser  : {$dispenser->name} (ID {$dispenser->id})");

            // -- Medicina e piano terapeutico di default --------------------------
            $this->createDefaultTherapy($patient, $doctor, $dispenser);

            return $dispenser;
        });

        $this->newLine();
        $this->info('Creazione completata.');
        $this->printSummary($dispenser);

        return self::SUCCESS;
    }

    /**
     * Se il dispenser esiste ma il paziente non ha terapie attive, le crea.
     */
    private function ensureTherapyPlans(Dispenser $dispenser): void
    {
        if ($dispenser->patient_id === null) {
            return;
        }

        $hasTherapy = TherapyPlan::query()
            ->where('patient_id', $dispenser->patient_id)
            ->where('is_active', true)
            ->exists();

        if ($hasTherapy) {
            $this->line('  Terapie attive gia presenti, nessuna creazione necessaria.');
            return;
        }

        $this->warn('  Nessuna terapia attiva trovata. Creazione piano terapeutico di default...');

        $patient = User::query()->find($dispenser->patient_id);
        $doctor  = PatientAssignment::query()
            ->where('patient_id', $dispenser->patient_id)
            ->where('role', UserRole::Doctor->value)
            ->first()
            ?->member;

        if ($patient === null || $doctor === null) {
            $this->error('  Impossibile trovare paziente o dottore per la creazione della terapia.');
            return;
        }

        DB::transaction(fn () => $this->createDefaultTherapy($patient, $doctor, $dispenser));
        $this->info('  Piano terapeutico di default creato.');
    }

    /**
     * Crea una medicina generica + un piano terapeutico mattina/sera per il paziente.
     */
    private function createDefaultTherapy(User $patient, User $doctor, Dispenser $dispenser): void
    {
        // Medicina di default (una sola per paziente)
        $medicine = Medicine::query()->firstOrCreate(
            ['patient_id' => $patient->id, 'name' => 'Farmaco Generico ESP32'],
            [
                'created_by_id'    => $doctor->id,
                'description'      => 'Farmaco di default creato automaticamente per il dispenser ESP32.',
                'remaining_quantity' => 100,
                'reorder_threshold'  => 10,
            ],
        );
        $this->line("  Medicina   : {$medicine->name} (ID {$medicine->id})");

        // Piano terapeutico attivo
        $therapy = TherapyPlan::query()->firstOrCreate(
            [
                'patient_id'  => $patient->id,
                'medicine_id' => $medicine->id,
                'doctor_id'   => $doctor->id,
                'is_active'   => true,
            ],
            [
                'dose_amount'  => 1,
                'dose_unit'    => 'compressa',
                'instructions' => 'Assumere con un bicchiere d\'acqua.',
                'starts_on'    => now()->toDateString(),
                'ends_on'      => null,
            ],
        );
        $this->line("  Terapia    : piano ID {$therapy->id} attivo");

        // Orari: mattina e sera
        TherapyPlanSchedule::query()->firstOrCreate(
            ['therapy_plan_id' => $therapy->id, 'scheduled_time' => '08:00'],
            ['timezone' => 'Europe/Rome'],
        );
        TherapyPlanSchedule::query()->firstOrCreate(
            ['therapy_plan_id' => $therapy->id, 'scheduled_time' => '20:00'],
            ['timezone' => 'Europe/Rome'],
        );
        $this->line('  Orari      : 08:00 e 20:00');
    }

    private function printSummary(Dispenser $dispenser): void
    {
        $dispenser->load('patient');
        $therapyCount = TherapyPlan::query()
            ->where('patient_id', $dispenser->patient_id)
            ->where('is_active', true)
            ->count();

        $this->newLine();
        $this->line('Riepilogo dispositivo:');
        $this->line("  Device UID      : {$dispenser->device_uid}");
        $this->line("  Dispenser ID    : {$dispenser->id}");
        $this->line('  Paziente        : '.($dispenser->patient?->name ?? '-')." (ID {$dispenser->patient_id})");
        $this->line('  Topic MQTT      : '.($dispenser->mqtt_base_topic ?: '(non impostato)'));
        $this->line('  Attivo          : '.($dispenser->is_active ? 'si' : 'no'));
        $this->line('  Online          : '.($dispenser->is_online ? 'si' : 'no'));
        $this->line('  Ultimo segnale  : '.($dispenser->last_seen_at?->format('d/m/Y H:i') ?? 'mai'));
        $this->line("  Terapie attive  : {$therapyCount}");
    }
}
