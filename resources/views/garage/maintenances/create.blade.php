{{--
    "Registrar manutenção" do Lojista: seções numeradas em <x-ui.form-section> (1. Veículo e data,
    2. Serviço, 3. Oficina, 4. Notas fiscais), resumo de erros no topo e barra de ações no rodapé,
    com os mesmos rótulos e controles do Proprietário e da Nova OS ("Serviço realizado", Categoria em
    select, "Revisão obrigatória do fabricante" em checkbox). O aviso de procedência
    (maintenances._declared-notice, marcador LJ) diz, antes do envio, que o registro aparece como
    "Declarada pelo lojista": o Selo da oficina só vem quando a oficina da rede registra o serviço no
    portal dela (.ai/rules/theme.md).

    Vindo da ficha (?vehicle_id=), o veículo já vem escolhido e Salvar e Cancelar voltam à ficha
    (return_to=vehicle), com a manutenção nova em destaque.

    Quilometragem e oficina: resources/js/garage-maintenance-form.js (faixa aceita pela data, sem
    preencher o campo com o hodômetro de hoje; oficina da rede escolhida na lista vira workshop_id).
--}}
@extends('layouts.app')

@section('title', 'Registrar manutenção')

@php
    use App\Enums\ServiceCategory;
    use App\Http\Controllers\Web\Garage\MaintenanceController;

    $returnsToVehicle = $selectedVehicle !== null || old('return_to') === 'vehicle';
    $selectedVehicleName = $selectedVehicle ? trim($selectedVehicle->brand.' '.$selectedVehicle->model) : null;
    $cancelUrl = $selectedVehicle ? route('garage.vehicles.show', $selectedVehicle) : route('garage.maintenances.index');
    $breadcrumbs = $selectedVehicle
        ? [['Estoque', route('garage.vehicles.index')], [$selectedVehicleName, route('garage.vehicles.show', $selectedVehicle)], ['Registrar manutenção']]
        : [['Manutenções', route('garage.maintenances.index')], ['Registrar manutenção']];
    $vehicleOptions = $vehicles->mapWithKeys(fn ($vehicle): array => [
        (string) $vehicle->id => trim($vehicle->brand.' '.$vehicle->model).(filled($vehicle->license_plate) ? ' · '.$vehicle->license_plate : ''),
    ])->all();
    $workshopLabel = fn ($workshop): string => filled($workshop->city)
        ? $workshop->name.MaintenanceController::WORKSHOP_LABEL_SEPARATOR.$workshop->city
        : $workshop->name;
    $consignmentNote = $consignmentVehicles->isEmpty() ? null : ($consignmentVehicles->count() === 1
        ? 'O veículo em consignação não aparece na lista: só o proprietário registra manutenções nele.'
        : 'Os '.$consignmentVehicles->count().' veículos em consignação não aparecem na lista: só o proprietário registra manutenções neles.');
@endphp

@section('content')
    <x-ui.container size="md" padded>
        <x-ui.page-header
            title="Registrar manutenção"
            description="Revisões pré-venda e outros serviços feitos nos veículos do estoque."
            :breadcrumbs="$breadcrumbs"
        />

        @if ($consignmentNote !== null)
            <x-ui.alert variant="info" class="mb-6" data-consignment-note>
                {{ $consignmentNote }} O histórico fica na ficha do veículo quando a procuração é aprovada.
            </x-ui.alert>
        @endif

        @if ($vehicles->isEmpty())
            <x-ui.empty-state
                icon="truck"
                title="Adicione um veículo ao estoque primeiro"
                description="A manutenção entra no histórico de um veículo da loja. Adicione o veículo pelo CRLV-e e volte aqui para registrar o serviço."
                heading-level="h2"
                data-maintenance-form-empty
            >
                <x-slot:actions>
                    <x-ui.button icon="plus" :href="route('garage.vehicles.create')">Adicionar ao estoque</x-ui.button>
                </x-slot:actions>
            </x-ui.empty-state>
        @else
            <form
                method="POST"
                action="{{ route('garage.maintenances.store') }}"
                enctype="multipart/form-data"
                class="space-y-6"
                data-garage-maintenance-form
                data-mileage="{{ json_encode($mileage, JSON_UNESCAPED_UNICODE) }}"
            >
                @csrf
                @if ($returnsToVehicle)
                    <input type="hidden" name="return_to" value="vehicle">
                @endif

                <x-ui.form-errors :ids="['invoices.*' => 'invoices', 'return_to' => null]" />

                @include('maintenances._declared-notice', ['declaredBy' => 'garage', 'title' => null])

                <x-ui.form-section id="secao-veiculo" :number="1" title="Veículo e data">
                    <div class="grid items-start gap-4 sm:grid-cols-2">
                        <x-ui.field name="vehicle_id" label="Veículo" required class="sm:col-span-2">
                            <x-ui.select :options="$vehicleOptions" :value="$selectedVehicle?->id" placeholder="Selecione o veículo" required data-maintenance-vehicle />
                        </x-ui.field>

                        <x-ui.field name="maintenance_date" label="Data do serviço" required>
                            <x-ui.input type="date" :value="now()->toDateString()" max="{{ now()->toDateString() }}" required data-maintenance-date />
                        </x-ui.field>

                        <x-ui.field name="kilometers" label="Quilometragem (km)" hint="O que o hodômetro mostrava no dia do serviço." required>
                            <x-ui.input-group>
                                <x-ui.input type="number" min="0" max="9999999" step="1" inputmode="numeric" required aria-describedby="kilometers-range" data-maintenance-km />
                                <x-slot:trailing>km</x-slot:trailing>
                            </x-ui.input-group>
                            <p id="kilometers-range" class="text-sm text-muted-foreground" aria-live="polite" data-maintenance-km-range hidden></p>
                        </x-ui.field>
                    </div>
                </x-ui.form-section>

                <x-ui.form-section id="secao-servico" :number="2" title="Serviço">
                    <div class="grid items-start gap-4 sm:grid-cols-2">
                        <x-ui.field name="maintenance_type" label="Serviço realizado" hint="Ex.: Revisão pré-venda, Troca de óleo, Alinhamento." required class="sm:col-span-2">
                            <x-ui.input required maxlength="100" autocomplete="off" />
                        </x-ui.field>

                        <x-ui.field name="service_category" label="Categoria" required>
                            <x-ui.select :options="ServiceCategory::options()" placeholder="Selecione a categoria" required />
                        </x-ui.field>

                        <div class="sm:self-end">
                            <x-ui.checkbox
                                name="is_manufacturer_required"
                                label="Revisão obrigatória do fabricante"
                                description="Do plano de revisões do manual, que mantém a garantia de fábrica."
                            />
                        </div>

                        <x-ui.field name="description" label="Descrição" hint="O que foi feito, peças trocadas e observações para o comprador." optional class="sm:col-span-2">
                            <x-ui.textarea rows="4" autosize />
                        </x-ui.field>
                    </div>
                </x-ui.form-section>

                <x-ui.form-section id="secao-oficina" :number="3" title="Oficina">
                    <x-ui.field name="workshop_name" label="Oficina" hint="Comece a digitar para ver as oficinas da rede. Se ela não estiver na lista, escreva o nome." optional>
                        <x-ui.input list="oficinas-da-rede" maxlength="255" autocomplete="off" placeholder="Nome da oficina" aria-describedby="workshop_name-status" data-workshop-combobox />
                        <p id="workshop_name-status" class="text-sm text-muted-foreground empty:hidden" aria-live="polite" data-workshop-status></p>
                    </x-ui.field>
                    <input type="hidden" name="workshop_id" value="{{ old('workshop_id') }}" data-workshop-id>
                    <datalist id="oficinas-da-rede">
                        @foreach ($workshops as $workshop)
                            <option value="{{ $workshopLabel($workshop) }}" data-workshop-id="{{ $workshop->id }}"></option>
                        @endforeach
                    </datalist>
                </x-ui.form-section>

                <x-ui.form-section id="secao-notas" :number="4" title="Notas fiscais" description="PDF (DANFE) ou XML da NF-e. O XML importa as peças e os serviços com mais precisão.">
                    <x-ui.field name="invoices[]" label="Notas fiscais (NF-e)" optional>
                        <x-ui.file-input accept="application/pdf,.pdf,application/xml,text/xml,.xml" multiple :max-mb="10" />
                    </x-ui.field>
                </x-ui.form-section>

                <div class="sticky bottom-0 z-10 -mx-4 flex flex-wrap items-center justify-end gap-2 border-t border-border bg-surface/95 px-4 py-3 backdrop-blur-sm max-sm:*:grow sm:static sm:mx-0 sm:border-0 sm:bg-transparent sm:p-0 sm:backdrop-blur-none" data-slot="form-actions">
                    <x-ui.button variant="secondary" :href="$cancelUrl" data-cancel>Cancelar</x-ui.button>
                    <x-ui.button type="submit" icon="check" loading-label="Registrando…">Registrar manutenção</x-ui.button>
                </div>
            </form>
        @endif
    </x-ui.container>
@endsection
