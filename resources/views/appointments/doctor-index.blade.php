@extends('layouts.app')

@section('content')
    <section class="panel">
        <div class="panel-header flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <span>Pagina Appuntamenti</span>
            <form method="GET" class="flex items-center gap-2">
                <label class="text-xs uppercase tracking-wide text-slate-500" for="status">Filtro stato</label>
                <select id="status" name="status" class="form-input max-w-52 py-2">
                    <option value="">Tutti</option>
                    @foreach ([\App\Models\Appointment::STATUS_PENDING, \App\Models\Appointment::STATUS_CONFIRMED, \App\Models\Appointment::STATUS_COMPLETED, \App\Models\Appointment::STATUS_REJECTED, \App\Models\Appointment::STATUS_CANCELLED] as $filterStatus)
                        <option value="{{ $filterStatus }}" @selected($status === $filterStatus)>{{ $filterStatus }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn-secondary btn-sm">Filtra</button>
            </form>
        </div>
        <div class="panel-body">
            @if ($appointments->isEmpty())
                <p class="text-sm text-slate-500">Nessun appuntamento trovato.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="pb-2">Quando</th>
                                <th class="pb-2">Paziente</th>
                                <th class="pb-2">Stato</th>
                                <th class="pb-2">Note Paziente</th>
                                <th class="pb-2">Aggiorna</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($appointments as $appointment)
                                <tr>
                                    <td class="py-3">{{ $appointment->scheduled_at?->format('d/m/Y H:i') }}</td>
                                    <td class="py-3">
                                        <p class="font-medium text-slate-900">{{ $appointment->patient->name }}</p>
                                        <p class="text-xs text-slate-500">{{ $appointment->patient->email }}</p>
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
                                        @if ($appointment->status === \App\Models\Appointment::STATUS_CANCELLED)
                                            <span class="text-xs text-slate-500">Annullato dal paziente</span>
                                        @else
                                            <form method="POST" action="{{ route('doctor-appointments.update', $appointment) }}" class="grid gap-2 lg:grid-cols-[minmax(0,1fr)_auto]">
                                                @csrf
                                                @method('PATCH')
                                                <div class="space-y-2">
                                                    <select class="form-input py-2" name="status" required>
                                                        <option value="">Stato...</option>
                                                        <option value="{{ \App\Models\Appointment::STATUS_CONFIRMED }}">Conferma</option>
                                                        <option value="{{ \App\Models\Appointment::STATUS_COMPLETED }}">Segna completato</option>
                                                        <option value="{{ \App\Models\Appointment::STATUS_REJECTED }}">Rifiuta</option>
                                                    </select>
                                                    <textarea class="form-input min-h-20" name="doctor_notes" placeholder="Note per il paziente...">{{ $appointment->doctor_notes }}</textarea>
                                                </div>
                                                <button type="submit" class="btn-primary btn-sm h-fit">Salva</button>
                                            </form>
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
    </section>
@endsection
