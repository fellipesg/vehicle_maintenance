<div>
    <label for="vehicle_id" class="form-label">Veículo *</label>
    <select name="vehicle_id" id="vehicle_id" required class="form-select">
        <option value="">Selecione o veículo</option>
        @foreach($vehicles as $vehicle)
            <option value="{{ $vehicle->id }}" @selected(old('vehicle_id', request('vehicle_id')) == $vehicle->id)>
                {{ $vehicle->brand }} {{ $vehicle->model }} — {{ $vehicle->license_plate }}
            </option>
        @endforeach
    </select>
    @error('vehicle_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
</div>

<div>
    <label for="workshop_id" class="form-label">Oficina</label>
    <select name="workshop_id" id="workshop_id" class="form-select">
        <option value="">Selecione ou informe abaixo</option>
        @foreach($workshops as $workshop)
            <option value="{{ $workshop->id }}" @selected(old('workshop_id') == $workshop->id)>{{ $workshop->name }} — {{ $workshop->city }}</option>
        @endforeach
    </select>
</div>

<div>
    <label for="maintenance_type" class="form-label">Tipo de manutenção *</label>
    <input type="text" name="maintenance_type" id="maintenance_type" value="{{ old('maintenance_type') }}" required
           class="form-input" placeholder="Ex: Revisão dos 10.000 km">
    @error('maintenance_type')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
</div>

<div>
    <label for="service_category" class="form-label">Categoria *</label>
    <select name="service_category" id="service_category" required class="form-select">
        <option value="mechanical" @selected(old('service_category') === 'mechanical')>Mecânica</option>
        <option value="electrical" @selected(old('service_category') === 'electrical')>Elétrica</option>
        <option value="suspension" @selected(old('service_category') === 'suspension')>Suspensão</option>
        <option value="painting" @selected(old('service_category') === 'painting')>Pintura</option>
        <option value="finishing" @selected(old('service_category') === 'finishing')>Acabamento</option>
        <option value="interior" @selected(old('service_category') === 'interior')>Interior</option>
        <option value="other" @selected(old('service_category', 'other') === 'other')>Outros</option>
    </select>
</div>

<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <label for="maintenance_date" class="form-label">Data *</label>
        <input type="date" name="maintenance_date" id="maintenance_date" value="{{ old('maintenance_date', date('Y-m-d')) }}" required class="form-input">
        @error('maintenance_date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="kilometers" class="form-label">Quilometragem</label>
        <input type="number" name="kilometers" id="kilometers" value="{{ old('kilometers') }}" class="form-input" min="0">
    </div>
</div>

<div>
    <label for="workshop_name" class="form-label">Nome da oficina (se não selecionada acima)</label>
    <input type="text" name="workshop_name" id="workshop_name" value="{{ old('workshop_name') }}" class="form-input">
</div>

<div>
    <label for="description" class="form-label">Descrição</label>
    <textarea name="description" id="description" rows="4" class="form-input">{{ old('description') }}</textarea>
</div>

<label class="flex items-center gap-2 text-sm">
    <input type="checkbox" name="is_manufacturer_required" value="1" @checked(old('is_manufacturer_required')) class="rounded">
    Revisão obrigatória do fabricante
</label>

<div>
    <label for="invoices" class="form-label">Notas fiscais (PDF ou XML)</label>
    <input type="file" name="invoices[]" id="invoices" accept="application/pdf,.pdf,application/xml,.xml,text/xml" multiple class="form-input file:mr-3 file:rounded-lg file:border-0 file:bg-wrench-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-wrench-700">
    <p class="mt-1 text-xs text-automotive-500">Opcional. Envie PDF (DANFE) ou XML da NF-e (máx. 10 MB cada). O XML importa os itens com mais precisão.</p>
    @error('invoices')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    @error('invoices.*')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
</div>
