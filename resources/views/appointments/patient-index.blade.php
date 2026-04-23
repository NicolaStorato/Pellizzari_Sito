@extends('layouts.app')

@section('content')
    <section class="grid grid-cols-1 gap-6 xl:grid-cols-[1.05fr_1.4fr]">
        <article class="panel">
            <div class="panel-header">Nuovo Appuntamento</div>
            <form method="POST" action="{{ route('appointments.store') }}" class="panel-body space-y-4">
                @csrf

                <div>
                    <label class="text-sm font-medium text-slate-700" for="doctor_id">Dottore</label>
                    <select class="form-input" id="doctor_id" name="doctor_id" required>
                        <option value="">Seleziona un dottore</option>
                        @foreach ($availableDoctors as $doctor)
                            <option value="{{ $doctor->id }}" @selected(old('doctor_id') == $doctor->id)>
                                {{ $doctor->name }} ({{ $doctor->email }})
                            </option>
                        @endforeach
                    </select>
                    @if ($availableDoctors->isEmpty())
                        <p class="mt-2 text-xs text-rose-600">Non hai dottori associati al tuo profilo: non puoi prenotare appuntamenti finche non vieni associato.</p>
                    @endif
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-700" for="scheduled_at">Data e ora</label>
                    <input
                        class="form-input"
                        type="datetime-local"
                        id="scheduled_at"
                        name="scheduled_at"
                        value="{{ old('scheduled_at') }}"
                        required
                    >
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-700" for="patient_notes">Note per il dottore</label>
                    <textarea class="form-input min-h-24" id="patient_notes" name="patient_notes" placeholder="Motivo visita, sintomi, richieste...">{{ old('patient_notes') }}</textarea>
                </div>

                <button type="submit" class="btn-primary w-full" @disabled($availableDoctors->isEmpty())>Richiedi Appuntamento</button>
            </form>
        </article>

        <article class="panel">
            <div class="panel-header">Calendario Appuntamenti</div>
            <div class="panel-body">
                @if ($appointments->isEmpty())
                    <p class="text-sm text-slate-500">Non hai ancora richiesto appuntamenti.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead class="text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="pb-2">Quando</th>
                                    <th class="pb-2">Dottore</th>
                                    <th class="pb-2">Stato</th>
                                    <th class="pb-2">Note</th>
                                    <th class="pb-2">Azioni</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($appointments as $appointment)
                                    <tr>
                                        <td class="py-3">{{ $appointment->scheduled_at?->format('d/m/Y H:i') }}</td>
                                        <td class="py-3">
                                            <p class="font-medium text-slate-900">{{ $appointment->doctor->name }}</p>
                                            <p class="text-xs text-slate-500">{{ $appointment->doctor->email }}</p>
                                        </td>
                                        <td class="py-3">
                                            @php
                                                $statusClasses = match ($appointment->status) {
                                                    \App\Models\Appointment::STATUS_CONFIRMED => 'bg-emerald-100 text-emerald-700',
                                                    \App\Models\Appointment::STATUS_COMPLETED => 'bg-sky-100 text-sky-700',
                                                    \App\Models\Appointment::STATUS_REJECTED => 'bg-rose-100 text-rose-700',
                                                    \App\Models\Appointment::STATUS_CANCELLED => 'bg-slate-200 text-slate-700',
                                                    default => 'bg-amber-100 text-amber-800',
                                                };
                                            @endphp
                                            <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $statusClasses }}">
                                                {{ $appointment->status }}
                                            </span>
                                        </td>
                                        <td class="py-3">
                                            <p class="max-w-72 text-xs text-slate-600">{{ $appointment->patient_notes ?: '-' }}</p>
                                        </td>
                                        <td class="py-3">
                                            @if (in_array($appointment->status, [\App\Models\Appointment::STATUS_PENDING, \App\Models\Appointment::STATUS_CONFIRMED], true))
                                                <form method="POST" action="{{ route('appointments.cancel', $appointment) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button class="btn-secondary btn-sm" type="submit">Annulla</button>
                                                </form>
                                            @else
                                                <span class="text-xs text-slate-500">Nessuna azione</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4">{{ $appointments->links() }}</div>
                @endif
            </div>
        </article>
    </section>
@endsection
