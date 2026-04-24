@extends('layouts.app')

@section('content')
    <section class="panel">
        <div class="panel-header flex items-center justify-between">
            <span>{{ $dispenser->name }}</span>
            <a href="{{ route('dispensers.edit', $dispenser) }}" class="btn-secondary">Modifica</a>
        </div>
        <div class="panel-body grid grid-cols-1 gap-6 lg:grid-cols-3">

            {{-- Identità dispositivo --}}
            <article class="rounded-xl border border-slate-200 p-4 text-sm">
                <h3 class="font-semibold text-slate-700">Identita dispositivo</h3>
                <p class="mt-2"><strong>UID:</strong> {{ $dispenser->device_uid }}</p>
                <p><strong>Paziente:</strong> {{ $dispenser->patient->name ?? '-' }}</p>
                <p><strong>Token:</strong> <span class="font-mono text-xs">{{ $dispenser->api_token }}</span></p>
                <p><strong>Topic base:</strong> {{ $dispenser->mqtt_base_topic ?: '-' }}</p>
                <p><strong>Ultimo segnale:</strong> {{ $dispenser->last_seen_at?->format('d/m/Y H:i') ?: '-' }}</p>
            </article>

            {{-- Pannello comandi MQTT dedicati --}}
            <article class="rounded-xl border border-slate-200 p-4 text-sm lg:col-span-2">
                <h3 class="font-semibold text-slate-700 mb-1">Pannello Comandi MQTT</h3>
                <p class="text-xs text-slate-500 mb-4">
                    Topic: <span class="font-mono">{{ $mqttCommandTopicBase }}/commands</span>
                </p>

                @if(session('status'))
                    <div class="mb-4 rounded-lg border border-teal-200 bg-teal-50 px-3 py-2 text-xs text-teal-800">
                        {{ session('status') }}
                    </div>
                @endif

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">

                    {{-- A. Override posizione servo --}}
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-600">🎛️ Override Posizione Servo</p>
                        <p class="mt-1 text-xs text-slate-500">Dead-reckoning assoluto in ms. Range: 500–4500.</p>
                        <form action="{{ route('dispensers.mqtt-command', $dispenser) }}" method="POST" class="mt-3 space-y-3">
                            @csrf
                            <input type="hidden" name="command" value="set_position">
                            <div>
                                <label class="text-xs text-slate-600" for="position_ms">position_ms: <span id="position_ms_display">3500</span></label>
                                <input type="range" id="position_ms_range" min="500" max="4500" step="50" value="3500"
                                       class="mt-1 w-full accent-blue-600"
                                       oninput="document.getElementById('position_ms_display').textContent=this.value; document.getElementById('position_ms_val').value=this.value;">
                                <input type="hidden" id="position_ms_val" value="3500">
                                <input type="number" id="position_ms_num" min="500" max="4500" step="50" value="3500"
                                       class="form-input mt-1 text-xs"
                                       oninput="document.getElementById('position_ms_display').textContent=this.value; document.getElementById('position_ms_val').value=this.value; document.getElementById('position_ms_range').value=this.value;">
                            </div>
                            <input type="hidden" name="payload" id="set_position_payload">
                            <button type="submit" class="btn-primary text-xs w-full"
                                    onclick="document.getElementById('set_position_payload').value=JSON.stringify({position_ms:parseInt(document.getElementById('position_ms_val').value)})">
                                Invia set_position
                            </button>
                        </form>
                    </div>

                    {{-- B. Homing --}}
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-600">🏠 Homing (Reset Home)</p>
                        <p class="mt-1 text-xs text-slate-500">Riporta il servo alla posizione home (quota zero). Nessun payload richiesto.</p>
                        <form action="{{ route('dispensers.mqtt-command', $dispenser) }}" method="POST" class="mt-3">
                            @csrf
                            <input type="hidden" name="command" value="reset_home">
                            <input type="hidden" name="payload" value="{}">
                            <button type="submit" class="btn-primary text-xs w-full"
                                    onclick="return confirm('Eseguire il reset home del servo?')">
                                Invia reset_home
                            </button>
                        </form>
                    </div>

                    {{-- C. Sincronizzazione RTC --}}
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-600">🕐 Sincronizza RTC</p>
                        <p class="mt-1 text-xs text-slate-500">Forza il trigger HTTP GET interno per riallineare l'orologio del firmware.</p>
                        <form action="{{ route('dispensers.mqtt-command', $dispenser) }}" method="POST" class="mt-3">
                            @csrf
                            <input type="hidden" name="command" value="force_utc">
                            <input type="hidden" name="payload" value="{}">
                            <button type="submit" class="btn-primary text-xs w-full">
                                Invia force_utc
                            </button>
                        </form>
                    </div>

                    {{-- D. Riproduci Brano --}}
                    <div class="rounded-xl border border-purple-200 bg-purple-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-purple-700">🎵 Riproduci Brano</p>
                        <p class="mt-1 text-xs text-purple-600">Invia al dispenser il numero del brano da riprodurre.</p>
                        <form action="{{ route('dispensers.mqtt-command', $dispenser) }}" method="POST" class="mt-3 space-y-3">
                            @csrf
                            <input type="hidden" name="command" value="play_track">
                            <div>
                                <label class="text-xs text-purple-700" for="track_number">Numero brano</label>
                                <input type="number" id="track_number" min="1" max="99" value="1"
                                       class="form-input mt-1 text-xs">
                            </div>
                            <input type="hidden" name="payload" id="play_track_payload">
                            <button type="submit" class="btn-primary text-xs w-full"
                                    onclick="document.getElementById('play_track_payload').value=JSON.stringify({track:parseInt(document.getElementById('track_number').value)})">
                                Invia play_track
                            </button>
                        </form>
                    </div>

                    {{-- E. Iniezione configurazione terapeutica (schedule_response) --}}
                    <div class="rounded-xl border border-blue-200 bg-blue-50 p-4 md:col-span-2">
                        <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">📅 Iniezione Schedule Terapeutico</p>
                        <p class="mt-1 text-xs text-blue-600">
                            Topic: <span class="font-mono">esp32/schedule_response/{{ $dispenser->device_uid }}</span>
                        </p>
                        <p class="mt-1 text-xs text-blue-500">Inietta il blocco JSON con l'array <code>days</code> e <code>prescriptions</code> direttamente al firmware.</p>
                        <form action="{{ route('dispensers.mqtt-raw', $dispenser) }}" method="POST" class="mt-3 space-y-2">
                            @csrf
                            <input type="hidden" name="topic" value="esp32/schedule_response/{{ $dispenser->device_uid }}">
                            @error('payload')
                                <p class="text-xs text-red-600">{{ $message }}</p>
                            @enderror
                            <textarea name="payload" rows="8" class="form-input font-mono text-xs"
                                      placeholder='{
  "days": [
    {
      "date": "2026-04-23",
      "prescriptions": [
        {
          "therapy_plan_id": 1,
          "medicine": "Paracetamolo",
          "time": "08:00",
          "dose_amount": 1,
          "dose_unit": "compressa"
        }
      ]
    }
  ]
}'>{{ old('payload') }}</textarea>
                            <div class="flex items-center gap-2">
                                <button type="submit" class="btn-primary text-xs"
                                        onclick="return confirm('Pubblicare la configurazione schedule sul topic del firmware?')">
                                    Pubblica schedule_response
                                </button>
                                <button type="button" class="btn-secondary text-xs"
                                        onclick="this.closest('form').querySelector('textarea').value=''">
                                    Svuota
                                </button>
                            </div>
                        </form>
                    </div>

                </div>{{-- fine grid comandi --}}

                {{-- Separatore: form comando personalizzato --}}
                <div class="mt-6 border-t border-slate-200 pt-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-3">Comando personalizzato (avanzato)</p>
                    <form action="{{ route('dispensers.mqtt-command', $dispenser) }}" method="POST" class="grid grid-cols-1 gap-3 md:grid-cols-3">
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
                            <input id="mqtt-command" class="form-input" name="command" value="{{ old('command') }}" placeholder="set_position" required>
                        </div>
                        <div class="md:col-span-2">
                            <label class="text-xs uppercase tracking-wider text-slate-500" for="mqtt-payload">Payload JSON</label>
                            <textarea id="mqtt-payload" class="form-input min-h-24 font-mono text-xs" name="payload" placeholder='{"position_ms":3500}'>{{ old('payload') }}</textarea>
                        </div>
                        <div class="md:col-span-3 flex flex-wrap items-center gap-2">
                            <button type="submit" class="btn-primary">Invia al Broker</button>
                            <button type="button" id="mqtt-reset-form" class="btn-secondary">Reset</button>
                        </div>
                    </form>
                </div>

            </article>

            {{-- Pubblica tutte le terapie --}}
            @if ($dispenser->patient_id)
                <article class="rounded-xl border border-teal-200 bg-teal-50 p-4 text-sm lg:col-span-3">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h3 class="font-semibold text-teal-800">&#x21BA; Pubblica tutte le terapie del paziente</h3>
                            <p class="mt-1 text-xs text-teal-700">
                                Invia in un click tutti i piani terapeutici attivi associati a
                                <strong>{{ $dispenser->patient->name ?? 'questo paziente' }}</strong>
                                al dispenser via MQTT (<code class="font-mono">set_therapy</code>).
                                Il firmware risponderà con una richiesta di re-sincronizzazione automatica dello schedule.
                            </p>
                        </div>
                        <form action="{{ route('dispensers.publish-all-therapies', $dispenser) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn-primary whitespace-nowrap"
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
                const templateByCommand = Object.fromEntries(templates.map(t => [t.command, t]));

                const commandPresetSelect = document.getElementById('mqtt-command-template');
                const commandInput        = document.getElementById('mqtt-command');
                const payloadInput        = document.getElementById('mqtt-payload');
                const descriptionLabel    = document.getElementById('mqtt-command-description');
                const resetButton         = document.getElementById('mqtt-reset-form');

                if (!commandPresetSelect || !commandInput || !payloadInput || !descriptionLabel || !resetButton) return;

                const defaultDescription = 'Scegli un preset per compilare automaticamente comando e payload.';

                const applyPreset = command => {
                    const tpl = templateByCommand[command];
                    if (!tpl) { descriptionLabel.textContent = defaultDescription; return; }
                    commandInput.value  = tpl.command;
                    payloadInput.value  = Object.keys(tpl.payload || {}).length === 0 ? '' : JSON.stringify(tpl.payload, null, 2);
                    descriptionLabel.textContent = tpl.description || defaultDescription;
                };

                commandPresetSelect.addEventListener('change', function () { applyPreset(this.value); });
                resetButton.addEventListener('click', function () {
                    commandPresetSelect.value = '';
                    commandInput.value = '';
                    payloadInput.value = '';
                    descriptionLabel.textContent = defaultDescription;
                });

                if (commandPresetSelect.value !== '') applyPreset(commandPresetSelect.value);
            });
        </script>
    @endif
@endsection
