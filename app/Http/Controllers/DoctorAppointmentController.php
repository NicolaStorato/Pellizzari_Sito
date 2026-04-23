<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAppointmentStatusRequest;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DoctorAppointmentController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $doctor */
        $doctor = $request->user();

        $status = $request->string('status')->toString();

        $appointments = Appointment::query()
            ->forDoctor($doctor->id)
            ->with('patient:id,name,email')
            ->when($status !== '', function ($query) use ($status): void {
                $query->where('status', $status);
            })
            ->orderBy('scheduled_at')
            ->paginate(20)
            ->withQueryString();

        return view('appointments.doctor-index', [
            'appointments' => $appointments,
            'status' => $status,
        ]);
    }

    public function update(UpdateAppointmentStatusRequest $request, Appointment $appointment): RedirectResponse
    {
        /** @var User $doctor */
        $doctor = $request->user();
        abort_if($appointment->doctor_id !== $doctor->id, 403);

        if ($appointment->status === Appointment::STATUS_CANCELLED) {
            return back()->with('status', 'Appuntamento annullato dal paziente, nessuna azione eseguita.');
        }

        $validated = $request->validated();
        $nextStatus = (string) $validated['status'];

        $appointment->update([
            'status' => $nextStatus,
            'doctor_notes' => $validated['doctor_notes'] ?? null,
            'confirmed_at' => $nextStatus === Appointment::STATUS_CONFIRMED ? now() : $appointment->confirmed_at,
        ]);

        return back()->with('status', 'Stato appuntamento aggiornato.');
    }
}
