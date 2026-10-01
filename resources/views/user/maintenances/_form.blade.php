{{--
    Campos da manutenção do proprietário (Registrar manutenção e Editar manutenção), em seções
    numeradas <x-ui.form-section> (1. Veículo e data, 2. Serviço, 3. Oficina e nota fiscal), com os
    mesmos rótulos e controles do Lojista e da Nova OS ("Serviço realizado", Categoria em select,
    "Revisão obrigatória do fabricante" em checkbox) e o aviso de procedência compartilhado
    (maintenances._declared-notice, marcador PR); a barra de ações fica no rodapé (fixa no celular).
    resources/js/owner-maintenance-form.js completa com:

    - a faixa de quilometragem aceita para o veículo e a data, a mesma que o VehicleMileageService
      valida no servidor ("Entre 50.000 km (hodômetro no cadastro) e 60.000 km (registro de
      12/06/2025)"), sem travar o envio nem preencher o campo sozinho;
    - a oficina: com uma oficina da rede escolhida, o nome digitado some e a nota fiscal passa a
      ser obrigatória (aviso em aria-live).

    Variáveis:
    - maintenance: a manutenção (edição) ou null (cadastro).
    - vehicles: veículos do dono (cadastro); selectedVehicle: o de ?vehicle_id.
    - workshops: oficinas da rede; selectedWorkshopId: a de ?workshop_id ("Registrar manutenção aqui").
    - mileage: dados de quilometragem por veículo (MaintenanceController::mileageContext).
    - submitLabel, loadingLabel e cancelUrl: a barra de ações.
--}}
@php
    use App\Enums\ServiceCategory;

    $formMaintenance = $maintenance ?? null;
    $formIsEdit = $formMaintenance !== null && $formMaintenance->exists;
    $formVehicles = $vehicles ?? collect();
    $formSelectedVehicle = $selectedVehicle ?? null;
    $formVehicleOptions = $formVehicles->mapWithKeys(fn ($vehicle): array => [
        $vehicle->id => trim($vehicle->brand.' '.$vehicle->model).(filled($vehicle->license_plate) ? ' · '.$vehicle->license_plate : ''),
    ])->all();
    $formWorkshopOptions = $workshops->mapWithKeys(fn ($workshop): array => [
        $workshop->id => $workshop->name.(filled($workshop->city) ? ' · '.collect([$workshop->neighborhood, $workshop->city.(filled($workshop->state) ? '/'.$workshop->state : '')])->filter()->implode(', ') : ''),
    ])->all();
    $formWorkshopId = $formIsEdit ? $formMaintenance->workshop_id : ($selectedWorkshopId ?? null);
    $formExistingInvoices = $formIsEdit ? $formMaintenance->invoices : collect();
    $formFixedVehicle = $formIsEdit ? $formMaintenance->vehicle : null;
    $formReturnToVehicle = ! $formIsEdit && old('return_to', $formSelectedVehicle !== null ? 'vehicle' : null) === 'vehicle';
    $formServiceSuggestions = [
        'Troca de óleo e filtro',
        'Revisão',
        'Alinhamento e balanceamento',
        'Troca de pneus',
        'Pastilhas de freio',
        'Bateria',
        'Correia dentada',
        'Ar-condicionado',
        'Suspensão',
    ];
@endphp

<div
    class="space-y-6"
    data-owner-maintenance-form
    data-mileage="{{ json_encode($mileage ?? [], JSON_UNESCAPED_UNICODE) }}"
    @if ($formFixedVehicle !== null) data-vehicle-id="{{ $formFixedVehicle->id }}" @endif
    data-existing-invoices="{{ $formExistingInvoices->count() }}"
>
    <x-ui.form-errors id="manutencao-erros" :ids="['invoices.*' => 'invoices', 'return_to' => null]" />

    @include('maintenances._declared-notice', [
        'declaredBy' => 'owner',
        'title' => $formIsEdit ? 'Continua como Declarada pelo proprietário' : null,
    ])

    @if (! $formIsEdit && $formReturnToVehicle)
        <input type="hidden" name="return_to" value="vehicle">
    @endif

    <x-ui.form-section id="secao-veiculo" :number="1" title="Veículo e data">
        <div class="grid items-start gap-4 sm:grid-cols-2">
            @if ($formIsEdit)
                <div class="sm:col-span-2">
                    <p class="text-sm font-medium text-foreground">Veículo</p>
                    <p class="mt-1 flex flex-wrap items-center gap-2 text-sm text-foreground">
                        <span>{{ trim(($formFixedVehicle?->brand ?? '').' '.($formFixedVehicle?->model ?? '')) }}</span>
                        @if (filled($formFixedVehicle?->license_plate))
                            <span class="rounded-md border border-border-strong px-1.5 py-px font-mono text-xs font-semibold tracking-wider"><span class="sr-only">Placa </span>{{ $formFixedVehicle->license_plate }}</span>
                        @endif
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">O veículo de uma manutenção não muda. Se ela foi registrada no carro errado, exclua e registre de novo.</p>
                </div>
            @else
                <x-ui.field name="vehicle_id" label="Veículo" required class="sm:col-span-2">
                    <x-ui.select :options="$formVehicleOptions" :value="$formSelectedVehicle?->id" placeholder="Selecione o veículo" required data-maintenance-vehicle />
                </x-ui.field>
            @endif

            <x-ui.field name="maintenance_date" label="Data do serviço" required>
                <x-ui.input type="date" :value="$formIsEdit ? $formMaintenance->maintenance_date?->format('Y-m-d') : now()->format('Y-m-d')" required data-maintenance-date />
            </x-ui.field>

            <x-ui.field name="kilometers" label="Quilometragem (km)" hint="O que o hodômetro mostrava no dia do serviço." required>
                <x-ui.input-group>
                    <x-ui.input type="number" :value="$formIsEdit ? $formMaintenance->kilometers : null" min="0" max="9999999" step="1" inputmode="numeric" required aria-describedby="kilometers-range" data-maintenance-km />
                    <x-slot:trailing>km</x-slot:trailing>
                </x-ui.input-group>
                <p id="kilometers-range" class="text-sm text-muted-foreground" aria-live="polite" data-maintenance-km-range hidden></p>
            </x-ui.field>
        </div>
    </x-ui.form-section>

    <x-ui.form-section id="secao-servico" :number="2" title="Serviço">
        <div class="grid items-start gap-4 sm:grid-cols-2">
            <x-ui.field name="maintenance_type" label="Serviço realizado" hint="Ex.: Troca de óleo e filtro, Revisão dos 30.000 km." required class="sm:col-span-2">
                <x-ui.input :value="$formIsEdit ? $formMaintenance->maintenance_type : null" maxlength="100" required autocomplete="off" list="sugestoes-servico" />
            </x-ui.field>
            <datalist id="sugestoes-servico">
                @foreach ($formServiceSuggestions as $suggestion)
                    <option value="{{ $suggestion }}"></option>
                @endforeach
            </datalist>

            <x-ui.field name="service_category" label="Categoria" required>
                <x-ui.select :options="ServiceCategory::options()" :value="$formIsEdit ? $formMaintenance->service_category : null" placeholder="Selecione a categoria" required />
            </x-ui.field>

            <div class="sm:self-end">
                <x-ui.checkbox
                    name="is_manufacturer_required"
                    label="Revisão obrigatória do fabricante"
                    description="Do plano de revisões do manual, que mantém a garantia de fábrica."
                    :checked="$formIsEdit ? (bool) $formMaintenance->is_manufacturer_required : null"
                />
            </div>

            <x-ui.field name="description" label="Descrição" hint="Peças trocadas, recomendações da oficina, o que mais quiser lembrar." optional class="sm:col-span-2">
                <x-ui.textarea :value="$formIsEdit ? $formMaintenance->description : null" rows="4" autosize />
            </x-ui.field>
        </div>
    </x-ui.form-section>

    <x-ui.form-section id="secao-oficina" :number="3" title="Oficina e nota fiscal">
        <div class="grid items-start gap-4">
            <x-ui.field name="workshop_id" label="Oficina da rede" hint="Oficinas cadastradas no RevisaLog. Ao escolher uma, anexe a nota fiscal do serviço." optional>
                <x-ui.select :options="$formWorkshopOptions" :value="$formWorkshopId" placeholder="Nenhuma: a oficina não está na lista" data-maintenance-workshop />
            </x-ui.field>

            <x-ui.field name="workshop_name" label="Nome da oficina" hint="Se a oficina não está na lista acima." optional data-workshop-name-field>
                <x-ui.input :value="$formIsEdit && $formMaintenance->workshop_id === null ? $formMaintenance->workshop_name : null" maxlength="255" autocomplete="organization" />
            </x-ui.field>

            <x-ui.field name="invoices" label="Notas fiscais (PDF ou XML)">
                <x-ui.file-input name="invoices[]" accept="application/pdf,.pdf,application/xml,.xml,text/xml" multiple :max-mb="10" aria-describedby="invoices-rules invoices-requirement" />
                <p id="invoices-requirement" class="text-sm text-muted-foreground" aria-live="polite" data-invoice-requirement>
                    @if ($formExistingInvoices->isNotEmpty())
                        {{ $formExistingInvoices->count() === 1 ? 'Já há 1 nota fiscal anexada.' : 'Já há '.$formExistingInvoices->count().' notas fiscais anexadas.' }} Envie outra só se quiser acrescentar.
                    @else
                        Opcional sem oficina da rede; obrigatória ao escolher uma. O XML da NF-e importa as peças com mais precisão.
                    @endif
                </p>
            </x-ui.field>

            @if ($formExistingInvoices->isNotEmpty())
                <div>
                    <p class="text-sm font-medium text-foreground">Já anexadas</p>
                    <ul role="list" class="mt-2 divide-y divide-border rounded-control border border-border text-sm">
                        @foreach ($formExistingInvoices as $invoice)
                            <li class="flex items-center gap-2 px-3 py-2">
                                <x-ui.icon name="document-text" class="size-5 text-muted-foreground" />
                                <span class="min-w-0 truncate text-foreground">{{ $invoice->file_name }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </x-ui.form-section>

    <div class="sticky bottom-0 z-10 -mx-4 flex flex-wrap items-center justify-end gap-2 border-t border-border bg-surface/95 px-4 py-3 backdrop-blur-sm max-sm:*:grow sm:static sm:mx-0 sm:border-0 sm:bg-transparent sm:p-0 sm:backdrop-blur-none" data-slot="form-actions">
        <x-ui.button variant="secondary" :href="$cancelUrl">Cancelar</x-ui.button>
        <x-ui.button type="submit" icon="check" :loading-label="$loadingLabel ?? 'Salvando…'">{{ $submitLabel }}</x-ui.button>
    </div>
</div>
