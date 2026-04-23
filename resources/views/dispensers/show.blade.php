@extends('layouts.app')

@section('content')
    <section class="panel">
        <div class="panel-header flex items-center justify-between">
            <span>{{ $dispenser->name }}</span>
            <a href="{{ route('dispensers.edit', $dispenser) }}" class="btn-secondary">Modifica</a>
        </div>
        <div class="panel-body grid grid-cols-1 gap-6 lg:grid-cols-3">
            <article class="rounded-xl border border-slate-200 p-4 text-sm">
                <h3 class="font-semibold text-slate-700">Identita dispositivo</h3>
                <p class="mt-2"><strong>UID:</strong> {{ $dispenser->device_uid }}</p>
                <p><strong>Paziente:</strong> {{ $dispenser->patient->name ?? '-' }}</p>
                <p><strong>Token:</strong> <span class="font-mono text-xs">{{ $dispenser->api_token }}</span></p>
                <p><strong>Topic base:</strong> {{ $dispenser->mqtt_base_topic ?: '-' }}</p>
                <p><strong>Ultimo segnale:</strong> {{ $dispenser->last_seen_at?->format('d/m/Y H:i') ?: '-' }}</p>
            </article>

            <article class="rounded-xl border border-slate-200 p-4 text-sm lg:col-span-2">
                <h3 class="font-semibold text-slate-700">Invio Comando MQTT</h3>
                <p class="mt-1 text-xs text-slate-500">
                    Topic comando: <span class="font-mono">{{ $mqttCommandTopicBase }}/commands/&lt;comando&gt;</span>
                </p>

                <form action="{{ route('dispensers.mqtt-command', $dispenser) }}" method="POST" class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-3">
                    @csrf
                    @if ($mqttCommandTemplates !== [])
                        <div class="md:col-span-3">
                            <label class="text-xs uppercase tracking-wider text-slate-500" for="mqtt-command-template">Preset comando</label>
                            <select id="mqtt-command-template" class="form-input">
                                <option value="">Personalizzato</option>
                                @foreach ($mqttCommandTemplates as $template)
                                    <option value="{{ $template['command'] }}" @selected(old('command') === $template['command'])>
                                        {{ $template['label'] }} ({{ $template['command'] }})
                                    </option>
                                @endforeach
                            </select>
                            <p id="mqtt-command-description" class="mt-1 text-xs text-slate-500">
                                Scegli un preset per compilare automaticamente comando e payload.
                            </p>
                        </div>
                    @endif

                    <div>
                        <label class="text-xs uppercase tracking-wider text-slate-500" for="mqtt-command">Comando</label>
                        <input id="mqtt-command" class="form-input" name="command" value="{{ old('command') }}" placeholder="dispense_now" required>
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-xs uppercase tracking-wider text-slate-500" for="mqtt-payload">Payload JSON (opzionale)</label>
                        <textarea id="mqtt-payload" class="form-input min-h-24 font-mono text-xs" name="payload" placeholder='{"slot":1}'>{{ old('payload') }}</textarea>
                    </div>
                    <div class="md:col-span-3 flex flex-wrap items-center gap-2">
                        <button type="submit" class="btn-primary">Invia al Broker</button>
                        <button type="button" id="mqtt-reset-form" class="btn-secondary">Reset</button>
                    </div>
                </form>

                @if ($mqttCommandTemplates !== [])
                    <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-600">Comandi disponibili</p>
                        <ul class="mt-2 grid grid-cols-1 gap-2 md:grid-cols-2">
                            @foreach ($mqttCommandTemplates as $template)
                                <li class="rounded-lg border border-slate-200 bg-white p-2">
                                    <p class="text-xs font-semibold text-slate-700">{{ $template['label'] }}</p>
                                    <p class="mt-1 font-mono text-xs text-slate-600">{{ $template['command'] }}</p>
                                    @if ($template['description'] !== '')
                                        <p class="mt-1 text-xs text-slate-500">{{ $template['description'] }}</p>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </article>

            {{-- Azione rapida: pubblica tutte le terapie attive del paziente --}}
            @if ($dispenser->patient_id)
                <article class="rounded-xl border border-teal-200 bg-teal-50 p-4 text-sm md:col-span-3">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h3 class="font-semibold text-teal-800">&#x21BA; Pubblica tutte le terapie del paziente</h3>
                            <p class="mt-1 text-xs text-teal-700">
                                Invia in un click tutti i piani terapeutici attivi associati a
                                <strong>{{ $dispenser->patient->name ?? 'questo paziente' }}</strong>
                                al dispenser via MQTT (<code class="font-mono">set_therapy</code>).
                            </p>
                        </div>
                        <form action="{{ route('dispensers.publish-all-therapies', $dispenser) }}" method="POST">
                            @csrf
                            <button type="submit"
                                    class="btn-primary whitespace-nowrap"
                                    onclick="return confirm('Pubblicare tutte le terapie attive sul dispenser?')">
                                Pubblica tutte le terapie
                            </button>
                        </form>
                    </div>
                </article>
            @endif
        </div>
    </section>

    <section class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <article class="panel">
            <div class="panel-header">Ultimi Log Sensori</div>
            <div class="panel-body">
                <ul class="space-y-2 text-sm">
                    @forelse ($dispenser->sensorLogs as $log)
                        <li class="rounded-lg border border-slate-200 px-3 py-2">
                            {{ $log->recorded_at?->format('d/m H:i') }} - {{ $log->temperature }} &deg;C / {{ $log->humidity }}%
                        </li>
                    @empty
                        <li class="text-slate-500">Nessun log.</li>
                    @endforelse
                </ul>
            </div>
        </article>

        <article class="panel">
            <div class="panel-header">Alert Collegati</div>
            <div class="panel-body">
                <ul class="space-y-2 text-sm">
                    @forelse ($dispenser->alerts as $alert)
                        <li class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-rose-700">
                            {{ $alert->triggered_at?->format('d/m H:i') }} - {{ $alert->type }}: {{ $alert->message }}
                        </li>
                    @empty
                        <li class="text-slate-500">Nessun alert.</li>
                    @endforelse
                </ul>
            </div>
        </article>
    </section>

    @if ($mqttCommandTemplates !== [])
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const templates = @json($mqttCommandTemplates);
                const templateByCommand = Object.fromEntries(templates.map(function (template) {
                    return [template.command, template];
                }));

                const commandPresetSelect = document.getElementById('mqtt-command-template');
                const commandInput = document.getElementById('mqtt-command');
                const payloadInput = document.getElementById('mqtt-payload');
                const descriptionLabel = document.getElementById('mqtt-command-description');
                const resetButton = document.getElementById('mqtt-reset-form');

                if (! commandPresetSelect || ! commandInput || ! payloadInput || ! descriptionLabel || ! resetButton) {
                    return;
                }

                const defaultDescription = 'Scegli un preset per compilare automaticamente comando e payload.';

                const applyPreset = function (command) {
                    const selectedTemplate = templateByCommand[command];
                    if (! selectedTemplate) {
                        descriptionLabel.textContent = defaultDescription;
                        return;
                    }

                    commandInput.value = selectedTemplate.command;

                    const payload = selectedTemplate.payload || {};
                    payloadInput.value = Object.keys(payload).length === 0 ? '' : JSON.stringify(payload, null, 2);

                    descriptionLabel.textContent = selectedTemplate.description || defaultDescription;
                };

                commandPresetSelect.addEventListener('change', function () {
                    applyPreset(this.value);
                });

                resetButton.addEventListener('click', function () {
                    commandPresetSelect.value = '';
                    commandInput.value = '';
                    payloadInput.value = '';
                    descriptionLabel.textContent = defaultDescription;
                });

                if (commandPresetSelect.value !== '') {
                    applyPreset(commandPresetSelect.value);
                }
            });
        </script>
    @endif
@endsection
