@extends('layouts.app')

@section('content')
    <section class="panel">
        <div class="panel-header flex items-center justify-between">
            <span>{{ $medicine->name }}</span>
            <a href="{{ route('medicines.edit', $medicine) }}" class="btn-secondary">Modifica</a>
        </div>
        <div class="panel-body grid grid-cols-1 gap-6 lg:grid-cols-3">
            <article class="rounded-xl border border-slate-200 p-4">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Dettagli</h3>
                <dl class="mt-3 space-y-2 text-sm">
                    <div><dt class="font-medium">Paziente</dt><dd>{{ $medicine->patient->name ?? '-' }}</dd></div>
                    <div><dt class="font-medium">Descrizione</dt><dd>{{ $medicine->description ?: '-' }}</dd></div>
                    <div><dt class="font-medium">Scorta</dt><dd>{{ $medicine->remaining_quantity }}</dd></div>
                    <div><dt class="font-medium">Riordino sotto</dt><dd>{{ $medicine->reorder_threshold }}</dd></div>
                </dl>
            </article>
            <article class="rounded-xl border border-slate-200 p-4">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Conservazione</h3>
                <dl class="mt-3 space-y-2 text-sm">
                    <div><dt class="font-medium">Temp. minima</dt><dd>{{ $medicine->minimum_temperature ?? '-' }} &deg;C</dd></div>
                    <div><dt class="font-medium">Temp. massima</dt><dd>{{ $medicine->maximum_temperature ?? '-' }} &deg;C</dd></div>
                    <div><dt class="font-medium">Umidita minima</dt><dd>{{ $medicine->minimum_humidity ?? '-' }} %</dd></div>
                    <div><dt class="font-medium">Umidita massima</dt><dd>{{ $medicine->maximum_humidity ?? '-' }} %</dd></div>
                </dl>
            </article>
            <article class="rounded-xl border border-slate-200 p-4">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Stima Esaurimento</h3>
                <p class="mt-3 text-sm text-slate-700">Dosi giornaliere stimate: <strong>{{ number_format($dailyDose, 2, ',', '.') }}</strong></p>
                <p class="mt-2 text-sm text-slate-700">Data prevista: <strong>{{ $estimatedDepletionDate?->format('d/m/Y') ?? 'n/d' }}</strong></p>
            </article>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header">Piani terapeutici collegati</div>
        <div class="panel-body">
            <ul class="space-y-2 text-sm">
                @forelse ($medicine->therapyPlans as $plan)
                    <li class="rounded-lg border border-slate-200 px-3 py-2">
                        {{ $plan->dose_amount }} {{ $plan->dose_unit }} - da {{ $plan->starts_on?->format('d/m/Y') }}
                    </li>
                @empty
                    <li class="text-slate-500">Nessun piano collegato.</li>
                @endforelse
            </ul>
        </div>
    </section>
@endsection

