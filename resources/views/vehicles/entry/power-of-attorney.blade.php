{{--
    Passo condicional do assistente, Consignação: o veículo, o motivo (conta sem CNPJ × CRLV-e de
    outra pessoa, com textos diferentes), o contato do proprietário, a declaração de autorização, a
    procuração (opcional) e "Cancelar", que descarta a leitura e volta ao passo Documento.

    A declaração já libera o registro de manutenções, porque o lojista informa um serviço que ele
    mesmo pagou e o proprietário é avisado de cada um. O histórico que o veículo já tinha é do dono:
    abre quando ele libera pelo e-mail, ou quando a equipe aprova a procuração.

    $contact: contexto vindo de HandlesVehicleConsignment::consignmentContext — quando o
    proprietário já tem conta, o contato dele aparece só mascarado e não vai para o formulário.
--}}
@extends('vehicles.entry.layout')

@php
    $entryStep = \App\Support\Vehicle\VehicleEntryFlow::STEP_POWER_OF_ATTORNEY;
    $entryTitle = 'Veículo em consignação';
    $entryEyebrow = $flow->powerOfAttorneyEyebrow();
    $entryDescription = 'O CRLV-e não está no seu CPF/CNPJ, então este veículo entra como consignação. Informe o proprietário para que possamos avisá-lo das manutenções que você registrar.';
    $entryBreadcrumbs = [
        [$flow->listLabel(), $flow->listUrl()],
        [$flow->title(), $flow->url('create')],
        ['Consignação'],
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
    $poaCancelFormId = 'cancelar-consignacao';
    $poaContactLocked = (bool) ($contact['contact_locked'] ?? false);
    $poaMaskedContact = collect([$contact['masked_email'] ?? null, $contact['masked_phone'] ?? null])
        ->filter()
        ->implode(' · ');
@endphp

@section('entry')
    @if($poaMissingAccountDocument)
        <x-ui.alert variant="warning" role="status" title="Sua conta não tem o CNPJ da loja" data-consignment-reason="{{ $reason }}">
            Por isso não conseguimos confirmar que o veículo é da loja. Se ele for da loja, cancele, <a href="{{ route('contact.show') }}">fale com a equipe</a> para incluir o CNPJ e envie o CRLV-e de novo. Se ele estiver em consignação, siga com a declaração abaixo.
        </x-ui.alert>
    @else
        <x-ui.alert variant="info" title="CRLV-e em nome de outra pessoa" data-consignment-reason="{{ \App\Support\Vehicle\VehicleEntryFlow::OWNERSHIP_OTHER_OWNER }}">
            @if($poaOwnerName !== null)
                O proprietário no CRLV-e é {{ $poaOwnerName }}@if($poaOwnerDocument !== null) ({{ $poaOwnerDocument }})@endif, com CPF/CNPJ diferente do da sua conta. É ela que vamos avisar das manutenções que você registrar.
            @else
                O CPF/CNPJ do proprietário no CRLV-e não é o da sua conta. É o proprietário que vamos avisar das manutenções que você registrar.
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
                    <dd class="mt-0.5 font-medium text-foreground">{{ $vehicle !== null ? 'Já cadastrado, com o histórico no chassi' : 'Novo: entra junto com a consignação' }}</dd>
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
                <span><strong class="font-semibold text-foreground">Agora:</strong> você declara a consignação e já pode registrar as manutenções que fizer no veículo.</span>
            </li>
            <li class="flex gap-3">
                <span aria-hidden="true" class="flex size-6 shrink-0 items-center justify-center rounded-full border border-input bg-surface text-xs font-semibold text-muted-foreground">2</span>
                <span><strong class="font-semibold text-foreground">Aviso ao proprietário:</strong> ele recebe uma mensagem agora e outra a cada manutenção registrada.</span>
            </li>
            <li class="flex gap-3">
                <span aria-hidden="true" class="flex size-6 shrink-0 items-center justify-center rounded-full border border-input bg-surface text-xs font-semibold text-muted-foreground">3</span>
                <span><strong class="font-semibold text-foreground">Histórico anterior:</strong> é do proprietário. Ele libera com um clique pelo aviso que recebeu, ou a equipe RevisaLog libera ao aprovar a procuração. {{ $flow->approvedPlacement() }}</span>
            </li>
        </ol>
    </x-ui.card>

    <x-ui.card as="section" heading-level="h2" title="Proprietário do veículo" id="proprietario">
        <form method="POST" action="{{ $flow->url('consignment.store') }}" enctype="multipart/form-data" class="space-y-6" data-vehicle-entry-form="consignment">
            @csrf

            <x-ui.field name="consignment_owner_name" label="Nome do proprietário" hint="Pré-preenchido com o nome que consta no CRLV-e." required>
                <x-ui.input :value="old('consignment_owner_name', $contact['owner_name'] ?? $poaOwnerName)" maxlength="255" required />
            </x-ui.field>

            @if($poaContactLocked)
                <x-ui.alert variant="info" title="Este proprietário já tem conta na RevisaLog" data-consignment-contact="locked">
                    Vamos avisá-lo pela conta dele{{ $poaMaskedContact !== '' ? " ({$poaMaskedContact})" : '' }}. Por privacidade, não mostramos o contato completo.
                </x-ui.alert>
            @else
                <div class="grid gap-6 sm:grid-cols-2" data-consignment-contact="open">
                    <x-ui.field name="consignment_owner_email" label="E-mail do proprietário" hint="E-mail ou telefone: precisamos de ao menos um.">
                        <x-ui.input type="email" :value="old('consignment_owner_email')" maxlength="255" autocomplete="off" />
                    </x-ui.field>
                    <x-ui.field name="consignment_owner_phone" label="Telefone do proprietário" optional>
                        <x-ui.input type="tel" :value="old('consignment_owner_phone')" maxlength="30" autocomplete="off" />
                    </x-ui.field>
                </div>
            @endif

            <x-ui.checkbox
                name="consignment_declaration"
                :checked="old('consignment_declaration')"
                label="Declaro que tenho autorização do proprietário para registrar manutenções neste veículo."
                description="Guardamos a data, o horário e o IP desta declaração. O proprietário é avisado e pode contestar."
                required
            />

            <x-ui.field name="power_of_attorney" label="Procuração do proprietário (PDF)" hint="Opcional. Anexe para pedir acesso ao histórico anterior do veículo sem depender da resposta do proprietário." optional>
                <x-ui.file-input accept="application/pdf,.pdf" :max-mb="\App\Support\Vehicle\VehicleEntryFlow::DOCUMENT_MAX_KILOBYTES / 1024" />
            </x-ui.field>

            <div class="sticky bottom-0 z-10 -mx-4 flex flex-wrap items-center justify-end gap-2 border-t border-border bg-surface/95 px-4 py-3 backdrop-blur-sm max-sm:*:grow sm:static sm:mx-0 sm:border-0 sm:bg-transparent sm:p-0 sm:backdrop-blur-none" data-slot="form-actions">
                <x-ui.button variant="secondary" type="submit" :form="$poaCancelFormId" loading-label="Cancelando…" data-consignment-cancel>Cancelar</x-ui.button>
                <x-ui.button type="submit" icon="check" loading-label="Salvando…">Confirmar consignação</x-ui.button>
            </div>
        </form>

        <form id="{{ $poaCancelFormId }}" method="POST" action="{{ $flow->url('consignment.cancel') }}" hidden>
            @csrf
            @method('DELETE')
        </form>
    </x-ui.card>
@endsection
