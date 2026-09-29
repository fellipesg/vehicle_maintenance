{{--
    Passo condicional do assistente, Procuração (consignação): o veículo, o motivo (conta sem CNPJ ×
    CRLV-e de outra pessoa, com textos diferentes), como a análise funciona, o envio do PDF e
    "Cancelar", que descarta a leitura e volta ao passo Documento. Depois do envio, o status aparece
    no estoque ("Procuração em análise", aprovada ou recusada).
--}}
@extends('vehicles.entry.layout')

@php
    $entryStep = \App\Support\Vehicle\VehicleEntryFlow::STEP_POWER_OF_ATTORNEY;
    $entryTitle = 'Enviar procuração';
    $entryEyebrow = $flow->powerOfAttorneyEyebrow();
    $entryDescription = 'Para ver o histórico de um veículo que não está no seu CPF/CNPJ, envie a procuração do proprietário que aparece no CRLV-e. A equipe RevisaLog confere o documento.';
    $entryBreadcrumbs = [
        [$flow->listLabel(), $flow->listUrl()],
        [$flow->title(), $flow->url('create')],
        ['Enviar procuração'],
    ];
    $entrySteps = $flow->steps(\App\Support\Vehicle\VehicleEntryFlow::STEP_POWER_OF_ATTORNEY, consignment: true);

    $poaVehicleData = $vehicleData ?? [];
    $poaPreview = $preview ?? [];
    $poaPlate = $vehicle?->license_plate ?? ($poaVehicleData['license_plate'] ?? ($poaPreview['license_plate'] ?? null));
    $poaName = trim(($vehicle?->brand ?? ($poaVehicleData['brand'] ?? ($poaPreview['brand'] ?? ''))).' '.($vehicle?->model ?? ($poaVehicleData['model'] ?? ($poaPreview['model'] ?? ''))));
    $poaYear = $vehicle?->year ?? ($poaVehicleData['year'] ?? ($poaPreview['year'] ?? null));
    $poaOwnerName = filled($poaPreview['owner_name'] ?? null) ? (string) $poaPreview['owner_name'] : null;
    $poaOwnerDocument = \App\Support\DocumentMask::cpfOrCnpj($poaPreview['owner_document'] ?? null);
    $poaHasVehicle = $vehicle !== null || $poaVehicleData !== [] || $poaPreview !== [];
    $poaMissingAccountDocument = $flow->isDealer() && $reason === \App\Support\Vehicle\VehicleEntryFlow::OWNERSHIP_MISSING_ACCOUNT_DOCUMENT;
    $poaCancelFormId = 'cancelar-procuracao';
@endphp

@section('entry')
    @if($poaMissingAccountDocument)
        <x-ui.alert variant="warning" role="status" title="Sua conta não tem o CNPJ da loja" data-consignment-reason="{{ $reason }}">
            Por isso não conseguimos confirmar que o veículo é da loja. Se ele for da loja, cancele, <a href="{{ route('contact.show') }}">fale com a equipe</a> para incluir o CNPJ e envie o CRLV-e de novo. Se ele estiver em consignação, siga com a procuração.
        </x-ui.alert>
    @else
        <x-ui.alert variant="info" title="CRLV-e em nome de outra pessoa" data-consignment-reason="{{ \App\Support\Vehicle\VehicleEntryFlow::OWNERSHIP_OTHER_OWNER }}">
            @if($poaOwnerName !== null)
                O proprietário no CRLV-e é {{ $poaOwnerName }}@if($poaOwnerDocument !== null) ({{ $poaOwnerDocument }})@endif, com CPF/CNPJ diferente do da sua conta. A procuração precisa ser assinada por essa pessoa.
            @else
                O CPF/CNPJ do proprietário no CRLV-e não é o da sua conta. A procuração precisa ser assinada pelo proprietário.
            @endif
        </x-ui.alert>
    @endif

    <x-ui.card as="section" heading-level="h2" title="Veículo" id="veiculo-procuracao">
        @if($poaHasVehicle)
            <dl class="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2" data-consignment-vehicle>
                @if($poaName !== '')
                    <div class="min-w-0">
                        <dt class="text-muted-foreground">Marca e modelo</dt>
                        <dd class="mt-0.5 font-medium text-foreground">{{ $poaName }}@if($poaYear) · {{ $poaYear }}@endif</dd>
                    </div>
                @endif
                @if(filled($poaPlate))
                    <div class="min-w-0">
                        <dt class="text-muted-foreground">Placa</dt>
                        <dd class="mt-0.5 font-mono font-semibold tracking-wider text-foreground">{{ $poaPlate }}</dd>
                    </div>
                @endif
                @if($poaOwnerName !== null)
                    <div class="min-w-0">
                        <dt class="text-muted-foreground">Proprietário no CRLV-e</dt>
                        <dd class="mt-0.5 font-medium break-words text-foreground">{{ $poaOwnerName }}@if($poaOwnerDocument !== null) · {{ $poaOwnerDocument }}@endif</dd>
                    </div>
                @endif
                <div class="min-w-0">
                    <dt class="text-muted-foreground">Na RevisaLog</dt>
                    <dd class="mt-0.5 font-medium text-foreground">{{ $vehicle !== null ? 'Já cadastrado, com o histórico no chassi' : 'Novo: entra junto com a procuração' }}</dd>
                </div>
            </dl>
        @else
            <p class="text-sm text-muted-foreground">Os dados do veículo não estão mais disponíveis. Se precisar conferir, cancele e envie o CRLV-e de novo.</p>
        @endif
    </x-ui.card>

    <x-ui.card as="section" heading-level="h2" title="Como funciona" id="como-funciona">
        <ol class="space-y-3 text-sm text-muted-foreground" data-consignment-steps>
            <li class="flex gap-3">
                <span aria-hidden="true" class="flex size-6 shrink-0 items-center justify-center rounded-full border-2 border-ring bg-accent text-xs font-semibold text-accent-foreground">1</span>
                <span><strong class="font-semibold text-foreground">Envio:</strong> você anexa o PDF da procuração, nesta tela.</span>
            </li>
            <li class="flex gap-3">
                <span aria-hidden="true" class="flex size-6 shrink-0 items-center justify-center rounded-full border border-input bg-surface text-xs font-semibold text-muted-foreground">2</span>
                <span><strong class="font-semibold text-foreground">Análise:</strong> a equipe RevisaLog confere o documento. @if($flow->isDealer())Enquanto isso, o veículo aparece no estoque como "Procuração em análise".@else Enquanto isso, o histórico fica fechado.@endif</span>
            </li>
            <li class="flex gap-3">
                <span aria-hidden="true" class="flex size-6 shrink-0 items-center justify-center rounded-full border border-input bg-surface text-xs font-semibold text-muted-foreground">3</span>
                <span><strong class="font-semibold text-foreground">Histórico liberado:</strong> {{ $flow->approvedPlacement() }}</span>
            </li>
        </ol>
    </x-ui.card>

    <x-ui.card as="section" heading-level="h2" title="Procuração do proprietário" id="procuracao">
        <form method="POST" action="{{ $flow->url('consignment.store') }}" enctype="multipart/form-data" class="space-y-6" data-vehicle-entry-form="power-of-attorney">
            @csrf

            <x-ui.field name="power_of_attorney" label="PDF da procuração" hint="Assinada pelo proprietário que aparece no CRLV-e." required>
                <x-ui.file-input accept="application/pdf,.pdf" :max-mb="\App\Support\Vehicle\VehicleEntryFlow::DOCUMENT_MAX_KILOBYTES / 1024" required />
            </x-ui.field>

            <div class="sticky bottom-0 z-10 -mx-4 flex flex-wrap items-center justify-end gap-2 border-t border-border bg-surface/95 px-4 py-3 backdrop-blur-sm max-sm:*:grow sm:static sm:mx-0 sm:border-0 sm:bg-transparent sm:p-0 sm:backdrop-blur-none" data-slot="form-actions">
                <x-ui.button variant="secondary" type="submit" :form="$poaCancelFormId" loading-label="Cancelando…" data-consignment-cancel>Cancelar</x-ui.button>
                <x-ui.button type="submit" icon="arrow-up-tray" loading-label="Enviando procuração…">Enviar procuração</x-ui.button>
            </div>
        </form>

        <form id="{{ $poaCancelFormId }}" method="POST" action="{{ $flow->url('consignment.cancel') }}" hidden>
            @csrf
            @method('DELETE')
        </form>
    </x-ui.card>
@endsection
