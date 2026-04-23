@extends('layouts.app')

@section('content')
    <section class="panel">
        <div class="panel-header">Log Sensori</div>
        <div class="panel-body space-y-4">
            <form method="GET" class="grid grid-cols-1 gap-3 md:grid-cols-4">
                <div>
                    <label class="text-xs uppercase tracking-wider text-slate-500" for="patient_id">Paziente</label>
                    <select class="form-input" name="patient_id" id="patient_id">
                        <option value="">Tutti</option>
                        @foreach ($patients as $patient)
                            <option value="{{ $patient->id }}" @selected(($filters['patient_id'] ?? '') == $patient->id)>{{ $patient->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs uppercase tracking-wider text-slate-500" for="from">Da</label>
                    <input class="form-input" type="date" name="from" id="from" value="{{ $filters['from'] ?? '' }}">
                </div>
                <div>
                    <label class="text-xs uppercase tracking-wider text-slate-500" for="to">A</label>
                    <input class="form-input" type="date" name="to" id="to" value="{{ $filters['to'] ?? '' }}">
                </div>
                <div class="flex items-end">
                    <button type="submit" class="btn-primary w-full">Filtra</button>
                </div>
            </form>

            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="text-xs uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="pb-2">Data</th>
                            <th class="pb-2">Paziente</th>
                            <th class="pb-2">Dispenser</th>
                            <th class="pb-2">Temperatura</th>
                            <th class="pb-2">Umidita</th>
                            <th class="pb-2">Soglie</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($sensorLogs as $log)
                            <tr>
                                <td class="py-2">{{ $log->recorded_at?->format('d/m/Y H:i') }}</td>
                                <td class="py-2">{{ $log->patient->name ?? '-' }}</td>
                                <td class="py-2">{{ $log->dispenser->name ?? '-' }}</td>
                                <td class="py-2">{{ $log->temperature }} &deg;C</td>
                                <td class="py-2">{{ $log->humidity }} %</td>
                                <td class="py-2">
                                    @if ($log->threshold_exceeded)
                                        <span class="rounded-full bg-rose-100 px-2 py-1 text-xs font-semibold text-rose-700">Fuori soglia</span>
                                    @else
                                        <span class="rounded-full bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-700">OK</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-6 text-center text-slate-500">Nessun log sensore trovato.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div>{{ $sensorLogs->links() }}</div>
        </div>
    </section>
@endsection

