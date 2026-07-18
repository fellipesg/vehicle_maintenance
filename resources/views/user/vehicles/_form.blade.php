@php
    $catalog = $catalog ?? [];
    $selectedBrand = old('brand', $vehicle->brand ?? '');
    $selectedModel = old('model', $vehicle->model ?? '');
    $brandOptions = array_keys($catalog);
    if ($selectedBrand && ! in_array($selectedBrand, $brandOptions, true)) {
        $brandOptions[] = $selectedBrand;
        sort($brandOptions);
    }
@endphp

<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <label for="license_plate" class="form-label">Placa *</label>
        <input type="text" name="license_plate" id="license_plate"
               value="{{ old('license_plate', $vehicle->license_plate ?? '') }}" required
               class="form-input uppercase" placeholder="ABC1D23">
        @error('license_plate')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="renavam" class="form-label">RENAVAM *</label>
        <input type="text" name="renavam" id="renavam"
               value="{{ old('renavam', $vehicle->renavam ?? '') }}" required class="form-input">
        @error('renavam')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="crv_number" class="form-label">Número do CRV *</label>
        <input type="text" name="crv_number" id="crv_number"
               value="{{ old('crv_number', $vehicle->crv_number ?? '') }}" required class="form-input"
               placeholder="Conforme o CRLV-e">
        @error('crv_number')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="brand" class="form-label">Marca *</label>
        <select name="brand" id="brand" required class="form-select">
            <option value="">Selecione a marca</option>
            @foreach($brandOptions as $brand)
                <option value="{{ $brand }}" @selected($selectedBrand === $brand)>{{ $brand }}</option>
            @endforeach
        </select>
        @error('brand')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="model" class="form-label">Modelo *</label>
        <select name="model" id="model" required class="form-select" @disabled(! $selectedBrand)>
            <option value="">{{ $selectedBrand ? 'Selecione o modelo' : 'Selecione a marca primeiro' }}</option>
        </select>
        @error('model')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="year" class="form-label">Ano do modelo *</label>
        <input type="number" name="year" id="year"
               value="{{ old('year', $vehicle->year ?? date('Y')) }}" required class="form-input"
               min="1980" max="{{ date('Y') + 1 }}">
        @error('year')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="color" class="form-label">Cor</label>
        <input type="text" name="color" id="color"
               value="{{ old('color', $vehicle->color ?? '') }}" class="form-input" placeholder="Ex: PRETA">
    </div>
    <div>
        <label for="chassis" class="form-label">Chassi</label>
        <input type="text" name="chassis" id="chassis"
               value="{{ old('chassis', $vehicle->chassis ?? '') }}" class="form-input uppercase">
    </div>
    <div>
        <label for="motorization" class="form-label">Motorização</label>
        <input type="text" name="motorization" id="motorization"
               value="{{ old('motorization', $vehicle->motorization ?? '') }}" class="form-input"
               placeholder="Ex: 1.6 Turbo">
        @error('motorization')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
    <div class="sm:col-span-2">
        <label for="engine" class="form-label">Código do motor (CRLV)</label>
        <input type="text" name="engine" id="engine"
               value="{{ old('engine', $vehicle->engine ?? '') }}" class="form-input"
               placeholder="Número do motor conforme o CRLV">
        @error('engine')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>
</div>

@push('scripts')
<script>
(() => {
    const catalog = @json($catalog);
    const brandSelect = document.getElementById('brand');
    const modelSelect = document.getElementById('model');
    const selectedModel = @json($selectedModel);

    function fillModels(brand, keepSelection = true) {
        modelSelect.innerHTML = '';
        modelSelect.disabled = !brand;

        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = brand ? 'Selecione o modelo' : 'Selecione a marca primeiro';
        modelSelect.appendChild(placeholder);

        if (!brand) {
            return;
        }

        const models = catalog[brand] ? [...catalog[brand]] : [];

        if (keepSelection && selectedModel && !models.includes(selectedModel)) {
            models.unshift(selectedModel);
        }

        models.sort((a, b) => a.localeCompare(b, 'pt-BR'));

        models.forEach((model) => {
            const option = document.createElement('option');
            option.value = model;
            option.textContent = model;
            if (keepSelection && model === selectedModel) {
                option.selected = true;
            }
            modelSelect.appendChild(option);
        });
    }

    brandSelect?.addEventListener('change', () => fillModels(brandSelect.value, false));
    fillModels(brandSelect?.value || '', true);
})();
</script>
@endpush
