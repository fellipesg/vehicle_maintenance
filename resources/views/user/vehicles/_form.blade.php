{{--
    Campos do veículo (identificação, modelo e quilometragem) com <x-ui.field>. Usado no assistente
    de entrada (passo Documento, formulário manual, e passo Conferir, preenchido pelo CRLV-e) e na
    edição do veículo.

    Variáveis:
    - vehicle: o veículo (edição), um objeto com os dados lidos do CRLV-e ou um Vehicle vazio. Sem id,
      a quilometragem atual é obrigatória.
    - catalog: marcas => modelos (VehicleCatalogService::all()).

    As dicas com data-field-hint são trocadas por resources/js/form-ux.js enquanto a pessoa digita
    (máscaras de chassi, placa, RENAVAM, CRV e ano). O select de modelo é montado pelo script abaixo a
    partir da marca escolhida.
--}}
@php
    $catalog = $catalog ?? [];
    $selectedBrand = old('brand', $vehicle->brand ?? '');
    $selectedModel = old('model', $vehicle->model ?? '');
    $brandOptions = array_keys($catalog);
    if ($selectedBrand && ! in_array($selectedBrand, $brandOptions, true)) {
        $brandOptions[] = $selectedBrand;
        sort($brandOptions);
    }
    $minYear = 1900;
    $maxYear = (int) date('Y') + 1;
    $isNewVehicle = empty($vehicle?->id);
@endphp

<div class="grid items-start gap-4 sm:grid-cols-2" data-vehicle-fields>
    <x-ui.field name="chassis" label="Chassi" required class="sm:col-span-2">
        <x-ui.input :value="$vehicle->chassis ?? ''" required class="font-mono uppercase placeholder:normal-case" maxlength="17" data-mask="chassis" autocomplete="off" spellcheck="false" placeholder="17 caracteres" aria-describedby="chassis-hint" />
        <p id="chassis-hint" class="text-sm text-muted-foreground" data-field-hint data-default-hint="O chassi identifica o veículo para sempre. A placa pode mudar." data-idle-class="text-sm text-muted-foreground" data-ok-class="text-sm text-success" data-error-class="text-sm text-danger">O chassi identifica o veículo para sempre. A placa pode mudar.</p>
    </x-ui.field>

    <x-ui.field name="license_plate" label="Placa atual" required>
        <x-ui.input :value="$vehicle->license_plate ?? ''" required class="font-mono uppercase" placeholder="ABC1D23" data-mask="plate" maxlength="7" autocomplete="off" spellcheck="false" aria-describedby="license_plate-hint" />
        <p id="license_plate-hint" class="text-sm text-muted-foreground" data-field-hint data-default-hint="Formato ABC1D23 ou ABC1234" data-idle-class="text-sm text-muted-foreground" data-ok-class="text-sm text-success" data-error-class="text-sm text-danger">Formato ABC1D23 ou ABC1234</p>
    </x-ui.field>

    <x-ui.field name="renavam" label="RENAVAM" required>
        <x-ui.input :value="$vehicle->renavam ?? ''" required class="font-mono" data-mask="digits" data-min-digits="11" data-max-digits="11" maxlength="11" inputmode="numeric" autocomplete="off" aria-describedby="renavam-hint" />
        <p id="renavam-hint" class="text-sm text-muted-foreground" data-field-hint data-default-hint="11 dígitos" data-idle-class="text-sm text-muted-foreground" data-ok-class="text-sm text-success" data-error-class="text-sm text-danger">11 dígitos</p>
    </x-ui.field>

    <x-ui.field name="crv_number" label="Número do CRV" required>
        <x-ui.input :value="$vehicle->crv_number ?? ''" required class="font-mono" data-mask="digits" data-min-digits="10" data-max-digits="12" maxlength="12" inputmode="numeric" autocomplete="off" aria-describedby="crv_number-hint" />
        <p id="crv_number-hint" class="text-sm text-muted-foreground" data-field-hint data-default-hint="10 a 12 dígitos, como no CRLV-e" data-idle-class="text-sm text-muted-foreground" data-ok-class="text-sm text-success" data-error-class="text-sm text-danger">10 a 12 dígitos, como no CRLV-e</p>
    </x-ui.field>

    <x-ui.field name="brand" label="Marca" required>
        <x-ui.select :options="array_combine($brandOptions, $brandOptions) ?: []" :value="$selectedBrand" placeholder="Selecione a marca" required />
    </x-ui.field>

    <x-ui.field name="model" label="Modelo" required>
        <x-ui.select required :disabled="! $selectedBrand">
            <option value="">{{ $selectedBrand ? 'Selecione o modelo' : 'Selecione a marca primeiro' }}</option>
        </x-ui.select>
    </x-ui.field>

    <x-ui.field name="year" label="Ano do modelo" required>
        <x-ui.input type="number" :value="$vehicle->year ?? date('Y')" required min="{{ $minYear }}" max="{{ $maxYear }}" inputmode="numeric" data-mask="year" aria-describedby="year-hint" />
        <p id="year-hint" class="text-sm text-muted-foreground" data-field-hint data-default-hint="Entre {{ $minYear }} e {{ $maxYear }}" data-ok-hint="Ano válido" data-idle-class="text-sm text-muted-foreground" data-ok-class="text-sm text-success" data-error-class="text-sm text-danger">Entre {{ $minYear }} e {{ $maxYear }}</p>
    </x-ui.field>

    <x-ui.field name="color" label="Cor" optional>
        <x-ui.input :value="$vehicle->color ?? ''" placeholder="Ex.: PRETA" autocomplete="off" />
    </x-ui.field>

    <x-ui.field name="motorization" label="Motorização" optional>
        <x-ui.input :value="$vehicle->motorization ?? ''" placeholder="Ex.: 1.6 Turbo" autocomplete="off" />
    </x-ui.field>

    <x-ui.field name="engine" label="Código do motor" hint="O número do motor, como no CRLV-e." optional>
        <x-ui.input :value="$vehicle->engine ?? ''" class="font-mono" autocomplete="off" spellcheck="false" />
    </x-ui.field>

    @if($isNewVehicle)
        <x-ui.field name="current_kilometers" label="Quilometragem atual" hint="O número que o hodômetro mostra hoje." required class="sm:col-span-2">
            <x-ui.input type="number" required min="0" max="9999999" inputmode="numeric" placeholder="Ex.: 85000" />
        </x-ui.field>
    @else
        <x-ui.field name="current_kilometers" label="Quilometragem atual" optional class="sm:col-span-2">
            <x-ui.input type="number" :value="$vehicle->current_kilometers" min="0" max="9999999" inputmode="numeric" />
        </x-ui.field>
    @endif
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

    if (!brandSelect || !modelSelect) {
        return;
    }

    brandSelect.addEventListener('change', () => fillModels(brandSelect.value, false));
    fillModels(brandSelect.value || '', true);
})();
</script>
@endpush
