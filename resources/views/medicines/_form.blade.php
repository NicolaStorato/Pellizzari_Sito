<div class="grid grid-cols-1 gap-4 md:grid-cols-2">
    <div>
        <label class="text-sm font-medium text-slate-700" for="patient_id">Paziente</label>
        <select class="form-input" name="patient_id" id="patient_id" required>
            <option value="">Seleziona paziente</option>
            @foreach ($patients as $patient)
                <option value="{{ $patient->id }}" @selected(old('patient_id', $medicine->patient_id ?? '') == $patient->id)>{{ $patient->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="text-sm font-medium text-slate-700" for="name">Nome farmaco</label>
        <input class="form-input" type="text" name="name" id="name" value="{{ old('name', $medicine->name ?? '') }}" required>
    </div>
    <div class="md:col-span-2">
        <label class="text-sm font-medium text-slate-700" for="description">Descrizione</label>
        <input class="form-input" type="text" name="description" id="description" value="{{ old('description', $medicine->description ?? '') }}">
    </div>
    <div>
        <label class="text-sm font-medium text-slate-700" for="image_url">URL immagine pillola</label>
        <input class="form-input" type="url" name="image_url" id="image_url" value="{{ old('image_url', $medicine->image_url ?? '') }}">
    </div>
    <div>
        <label class="text-sm font-medium text-slate-700" for="remaining_quantity">Quantita residua</label>
        <input class="form-input" type="number" min="0" name="remaining_quantity" id="remaining_quantity" value="{{ old('remaining_quantity', $medicine->remaining_quantity ?? 0) }}" required>
    </div>
    <div>
        <label class="text-sm font-medium text-slate-700" for="reorder_threshold">Soglia riordino</label>
        <input class="form-input" type="number" min="0" name="reorder_threshold" id="reorder_threshold" value="{{ old('reorder_threshold', $medicine->reorder_threshold ?? 10) }}" required>
    </div>

    <div>
        <label class="text-sm font-medium text-slate-700" for="minimum_temperature">Temp. minima (&deg;C)</label>
        <input class="form-input" type="number" step="0.1" name="minimum_temperature" id="minimum_temperature" value="{{ old('minimum_temperature', $medicine->minimum_temperature ?? '') }}">
    </div>
    <div>
        <label class="text-sm font-medium text-slate-700" for="maximum_temperature">Temp. massima (&deg;C)</label>
        <input class="form-input" type="number" step="0.1" name="maximum_temperature" id="maximum_temperature" value="{{ old('maximum_temperature', $medicine->maximum_temperature ?? '') }}">
    </div>
    <div>
        <label class="text-sm font-medium text-slate-700" for="minimum_humidity">Umidita minima (%)</label>
        <input class="form-input" type="number" step="0.1" name="minimum_humidity" id="minimum_humidity" value="{{ old('minimum_humidity', $medicine->minimum_humidity ?? '') }}">
    </div>
    <div>
        <label class="text-sm font-medium text-slate-700" for="maximum_humidity">Umidita massima (%)</label>
        <input class="form-input" type="number" step="0.1" name="maximum_humidity" id="maximum_humidity" value="{{ old('maximum_humidity', $medicine->maximum_humidity ?? '') }}">
    </div>
</div>
