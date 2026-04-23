<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAppointmentRequest;
use App\Models\Appointment;
use App\Models\User;
use App\UserRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PatientAppointmentController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $patient */
        $patient = $request->user();

        $appointments = Appointment::query()
            ->forPatient($patient->id)
            ->with('doctor:id,name,email')
            ->orderByDesc('scheduled_at')
            ->paginate(15);

        $availableDoctors = $patient->careTeamMembers()
            ->wherePivot('role', UserRole::Doctor->value)
            ->where('users.is_active', true)
            ->orderBy('users.name')
            ->get(['users.id', 'users.name', 'users.email']);

        return view('appointments.patient-index', [
            'appointments' => $appointments,
            'availableDoctors' => $availableDoctors,
        ]);
    }

    public function store(StoreAppointmentRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        /** @var User $patient */
        $patient = $request->user();

        $doctorId = (int) $validated['doctor_id'];
        $doctorIsAssigned = $patient->careTeamMembers()
            ->wherePivot('role', UserRole::Doctor->value)
            ->where('users.id', $doctorId)
            ->exists();

        if (! $doctorIsAssigned) {
            throw ValidationException::withMessages([
                'doctor_id' => 'Puoi prenotare solo con un dottore associato al tuo profilo.',
            ]);
        }

        Appointment::query()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctorId,
            'scheduled_at' => $validated['scheduled_at'],
            'status' => Appointment::STATUS_PENDING,
            'patient_notes' => $validated['patient_notes'] ?? null,
        ]);

        return redirect()
            ->route('appointments.index')
            ->with('status', 'Appuntamento richiesto con successo.');
    }

    public function cancel(Request $request, Appointment $appointment): RedirectResponse
    {
        /** @var User $patient */
        $patient = $request->user();

        abort_if($appointment->patient_id !== $patient->id, 403);

        if ($appointment->status === Appointment::STATUS_CANCELLED) {
            return back()->with('status', 'Appuntamento gia annullato.');
        }

        if (in_array($appointment->status, [Appointment::STATUS_COMPLETED, Appointment::STATUS_REJECTED], true)) {
            return back()->with('status', 'Questo appuntamento non puo essere annullato.');
        }

        $appointment->update([
            'status' => Appointment::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);

        return back()->with('status', 'Appuntamento annullato.');
    }
}
