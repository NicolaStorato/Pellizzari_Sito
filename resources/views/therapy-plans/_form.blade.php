@php
    $existingSchedule = old('schedules.0', $scheduleValues[0] ?? '08:00');
@endphp

<div class="grid grid-cols-1 gap-4 md:grid-cols-2">
    <div>
        <label class="text-sm font-medium text-slate-700" for="patient_id">Paziente</label>
        <select class="form-input" id="patient_id" name="patient_id" required>
            <option value="">Seleziona paziente</option>
            @foreach ($patients as $patient)
                <option value="{{ $patient->id }}" @selected(old('patient_id', $therapyPlan->patient_id ?? '') == $patient->id)>{{ $patient->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="text-sm font-medium text-slate-700" for="medicine_id">Farmaco</label>
        <select class="form-input" id="medicine_id" name="medicine_id" required>
            <option value="">Seleziona farmaco</option>
            @foreach ($medicines as $medicine)
                <option value="{{ $medicine->id }}" @selected(old('medicine_id', $therapyPlan->medicine_id ?? '') == $medicine->id)>
                    {{ $medicine->name }}
                </option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="text-sm font-medium text-slate-700" for="dose_amount">Dose</label>
        <input class="form-input" type="number" step="0.1" min="0.1" id="dose_amount" name="dose_amount" value="{{ old('dose_amount', $therapyPlan->dose_amount ?? 1) }}" required>
    </div>
    <div>
        <label class="text-sm font-medium text-slate-700" for="dose_unit">Unita</label>
        <input class="form-input" type="text" id="dose_unit" name="dose_unit" value="{{ old('dose_unit', $therapyPlan->dose_unit ?? 'compressa') }}" required>
    </div>
    <div>
        <label class="text-sm font-medium text-slate-700" for="starts_on">Inizio terapia</label>
        <input class="form-input" type="date" id="starts_on" name="starts_on" value="{{ old('starts_on', isset($therapyPlan) && $therapyPlan->starts_on ? $therapyPlan->starts_on->toDateString() : '') }}" required>
    </div>
    <div>
        <label class="text-sm font-medium text-slate-700" for="ends_on">Fine terapia</label>
        <input class="form-input" type="date" id="ends_on" name="ends_on" value="{{ old('ends_on', isset($therapyPlan) && $therapyPlan->ends_on ? $therapyPlan->ends_on->toDateString() : '') }}">
    </div>
    <div class="md:col-span-2">
        <label class="text-sm font-medium text-slate-700" for="instructions">Istruzioni</label>
        <textarea class="form-input" id="instructions" name="instructions" rows="3">{{ old('instructions', $therapyPlan->instructions ?? '') }}</textarea>
    </div>
    <div>
        <label class="text-sm font-medium text-slate-700" for="is_active">Stato</label>
        <select class="form-input" id="is_active" name="is_active">
            <option value="1" @selected(old('is_active', $therapyPlan->is_active ?? 1) == 1)>Attivo</option>
            <option value="0" @selected(old('is_active', $therapyPlan->is_active ?? 1) == 0)>Non attivo</option>
        </select>
    </div>
</div>

<div class="mt-6 rounded-xl border border-slate-200 p-4">
    <p class="text-sm font-semibold text-slate-700">Orario giornaliero (HH:MM)</p>
    <p class="mt-1 text-xs text-slate-500">Inserisci l'orario in cui il dispenser deve erogare la dose ogni giorno.</p>
    <div class="mt-3">
        <input class="form-input w-48" type="time" name="schedules[]" value="{{ $existingSchedule }}" required>
    </div>
</div>
