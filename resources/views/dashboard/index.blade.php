@extends('layouts.app')

@section('content')
    <section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">
        <article class="panel">
            <div class="panel-body">
                <p class="text-xs uppercase tracking-wider text-slate-500">Pazienti</p>
                <p class="mt-2 text-3xl font-bold text-slate-900">{{ $patientsCount }}</p>
            </div>
        </article>
        <article class="panel">
            <div class="panel-body">
                <p class="text-xs uppercase tracking-wider text-slate-500">Piani Attivi</p>
                <p class="mt-2 text-3xl font-bold text-slate-900">{{ $activeTherapyPlans }}</p>
            </div>
        </article>
        <article class="panel">
            <div class="panel-body">
                <p class="text-xs uppercase tracking-wider text-slate-500">Alert Aperti</p>
                <p class="mt-2 text-3xl font-bold text-rose-700">{{ $openAlertsCount }}</p>
            </div>
        </article>
        <article class="panel">
            <div class="panel-body">
                <p class="text-xs uppercase tracking-wider text-slate-500">Scorte Critiche</p>
                <p class="mt-2 text-3xl font-bold text-amber-700">{{ $lowStockCount }}</p>
            </div>
        </article>
        <article class="panel">
            <div class="panel-body">
                <p class="text-xs uppercase tracking-wider text-slate-500">Aderenza (30gg)</p>
                <p class="mt-2 text-3xl font-bold {{ $adherenceRate !== null && $adherenceRate >= 80 ? 'text-emerald-700' : 'text-slate-900' }}">
                    {{ $adherenceRate !== null ? $adherenceRate.'%' : 'n/d' }}
                </p>
            </div>
        </article>
    </section>

    <section class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <article class="panel">
            <div class="panel-header">Trend Ambientale (ultimi 24 campioni)</div>
            <div class="panel-body space-y-3">
                @forelse ($recentTelemetry as $log)
                    <div class="rounded-xl border border-slate-200 p-3">
                        <div class="mb-2 flex items-center justify-between text-xs text-slate-500">
                            <span>{{ $log->recorded_at?->format('d/m H:i') }}</span>
                            <span>{{ $log->patient->name ?? 'Paziente' }}</span>
                        </div>
                        <div class="space-y-2 text-sm">
                            <div>
                                <div class="mb-1 flex justify-between"><span>Temperatura</span><span>{{ $log->temperature }}&deg;C</span></div>
                                <div class="h-2 overflow-hidden rounded-full bg-slate-200">
                                    <div class="h-full bg-sky-600" style="width: {{ min(100, max(0, ((float) $log->temperature / 40) * 100)) }}%"></div>
                                </div>
                            </div>
                            <div>
                                <div class="mb-1 flex justify-between"><span>Umidita</span><span>{{ $log->humidity }}%</span></div>
                                <div class="h-2 overflow-hidden rounded-full bg-slate-200">
                                    <div class="h-full bg-amber-500" style="width: {{ min(100, max(0, (float) $log->humidity)) }}%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Nessun dato di telemetria disponibile.</p>
                @endforelse
            </div>
        </article>

        <article class="panel">
            <div class="panel-header">Prossime Somministrazioni</div>
            <div class="panel-body">
                @if ($upcomingDoses->isEmpty())
                    <p class="text-sm text-slate-500">Nessuna dose programmata nel breve termine.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead class="text-xs uppercase tracking-wider text-slate-500">
                                <tr>
                                    <th class="pb-2">Paziente</th>
                                    <th class="pb-2">Farmaco</th>
                                    <th class="pb-2">Dose</th>
                                    <th class="pb-2">Orario</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($upcomingDoses as $dose)
                                    <tr>
                                        <td class="py-2">{{ $dose['patient_name'] }}</td>
                                        <td class="py-2">{{ $dose['medicine_name'] }}</td>
                                        <td class="py-2">{{ $dose['dose'] }}</td>
                                        <td class="py-2">{{ \Illuminate\Support\Carbon::parse($dose['scheduled_at'])->format('d/m H:i') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </article>
    </section>

    <section class="panel">
        <div class="panel-header">Alert Recenti</div>
        <div class="panel-body">
            @if ($recentAlerts->isEmpty())
                <p class="text-sm text-slate-500">Nessun alert registrato.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="text-xs uppercase tracking-wider text-slate-500">
                            <tr>
                                <th class="pb-2">Data</th>
                                <th class="pb-2">Paziente</th>
                                <th class="pb-2">Tipo</th>
                                <th class="pb-2">Messaggio</th>
                                <th class="pb-2">Stato</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($recentAlerts as $alert)
                                <tr>
                                    <td class="py-2">{{ $alert->triggered_at?->format('d/m H:i') }}</td>
                                    <td class="py-2">{{ $alert->patient->name ?? '-' }}</td>
                                    <td class="py-2">{{ $alert->type }}</td>
                                    <td class="py-2">{{ $alert->message }}</td>
                                    <td class="py-2">
                                        @if ($alert->resolved_at)
                                            <span class="rounded-full bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-700">Risolto</span>
                                        @else
                                            <span class="rounded-full bg-rose-100 px-2 py-1 text-xs font-semibold text-rose-700">Aperto</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </section>
@endsection

