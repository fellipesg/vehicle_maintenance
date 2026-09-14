<div>
    <label for="license_plate" class="form-label">Placa do veículo *</label>
    <div class="flex gap-2">
        <input type="text" name="license_plate" id="license_plate"
               value="{{ old('license_plate', $licensePlate ?? $maintenance?->vehicle?->license_plate ?? '') }}"
               required class="form-input flex-1 uppercase" placeholder="ABC1D23" maxlength="10"
               @if(isset($maintenance)) readonly @endif>
        @unless(isset($maintenance))
            <a href="{{ route('workshop.maintenances.create', ['license_plate' => old('license_plate', $licensePlate ?? '')]) }}"
               class="btn-secondary whitespace-nowrap">Buscar</a>
        @endunless
    </div>
    @error('license_plate')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    @if(isset($vehicle) && $vehicle)
        <p class="mt-2 text-sm text-green-700">Veículo encontrado: {{ $vehicle->brand }} {{ $vehicle->model }} ({{ $vehicle->license_plate }})</p>
    @elseif(!empty($licensePlate))
        <p class="mt-2 text-sm text-red-600">Nenhum veículo encontrado para esta placa.</p>
    @endif
</div>

<div>
    <label for="maintenance_type" class="form-label">Tipo de manutenção *</label>
    <input type="text" name="maintenance_type" id="maintenance_type"
           value="{{ old('maintenance_type', $maintenance->maintenance_type ?? '') }}" required
           class="form-input" placeholder="Ex: Revisão dos 10.000 km">
    @error('maintenance_type')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
</div>

<div>
    <label for="service_category" class="form-label">Categoria *</label>
    <select name="service_category" id="service_category" required class="form-select">
        @foreach(['mechanical' => 'Mecânica', 'electrical' => 'Elétrica', 'suspension' => 'Suspensão', 'painting' => 'Pintura', 'finishing' => 'Acabamento', 'interior' => 'Interior', 'other' => 'Outros'] as $value => $label)
            <option value="{{ $value }}" @selected(old('service_category', $maintenance->service_category ?? 'other') === $value)>{{ $label }}</option>
        @endforeach
    </select>
</div>

<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <label for="maintenance_date" class="form-label">Data *</label>
        <input type="date" name="maintenance_date" id="maintenance_date"
               value="{{ old('maintenance_date', isset($maintenance) ? $maintenance->maintenance_date->format('Y-m-d') : date('Y-m-d')) }}"
               required class="form-input">
        @error('maintenance_date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="kilometers" class="form-label">Quilometragem *</label>
        <input type="number" name="kilometers" id="kilometers"
               value="{{ old('kilometers', $maintenance->kilometers ?? ($vehicle?->current_kilometers ?? '')) }}"
               class="form-input" min="0" max="9999999" required>
        @error('kilometers')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
</div>

@if(($orderTemplates ?? collect())->isNotEmpty())
    <div>
        <label for="general_warranty_template_id" class="form-label">Garantia geral da OS</label>
        <select name="general_warranty_template_id" id="general_warranty_template_id" class="form-select">
            <option value="">Sem garantia geral</option>
            @foreach($orderTemplates as $template)
                <option value="{{ $template->id }}" @selected((string) old('general_warranty_template_id', $maintenance?->generalWarranty?->warranty_template_id) === (string) $template->id)>
                    {{ $template->name }} ({{ $template->duration_days }} dias)
                </option>
            @endforeach
        </select>
        @error('general_warranty_template_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
@endif

<div>
    <label for="description" class="form-label">Descrição</label>
    <textarea name="description" id="description" rows="4" class="form-input">{{ old('description', $maintenance->description ?? '') }}</textarea>
</div>

<label class="flex items-center gap-2 text-sm">
    <input type="checkbox" name="is_manufacturer_required" value="1"
           @checked(old('is_manufacturer_required', $maintenance->is_manufacturer_required ?? false)) class="rounded">
    Revisão obrigatória do fabricante
</label>

<div>
    <label for="invoices" class="form-label">Notas fiscais (PDF ou XML)</label>
    <input type="file" name="invoices[]" id="invoices" accept="application/pdf,.pdf,application/xml,.xml,text/xml" multiple class="form-input file:mr-3 file:rounded-lg file:border-0 file:bg-wrench-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-wrench-700">
    @error('invoices')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    @error('invoices.*')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
</div>
