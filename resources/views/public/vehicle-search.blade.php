@extends('layouts.app')

@section('title', 'Buscar histórico de veículo')

@section('content')
@php
    $searchHasQuery = $identifier !== '';
    $searchMasked = $vehicle !== null && ! $viewerOwnsVehicle;
    $searchIsWorkshop = $viewerPortal === \App\Enums\Portal::Workshop;
@endphp
<x-ui.container padded>
    <x-ui.page-header
        title="Buscar histórico de veículo"
        description="Busca por placa, chassi ou RENAVAM. O resultado mostra as manutenções do veículo, cada uma com o Selo da oficina ou declarada por quem registrou."
    />

    {{-- No lg: a busca fica fixa à esquerda e o resultado ocupa a coluna de leitura à direita. --}}
    <div class="grid gap-6 lg:grid-cols-[20rem_minmax(0,1fr)] lg:items-start lg:gap-8">
        <div class="space-y-4 lg:sticky lg:top-24" data-slot="vehicle-search-panel">
            <x-ui.card>
                <form method="GET" action="{{ route('vehicle.search') }}" role="search" aria-label="Buscar veículo" class="space-y-4" data-vehicle-search-form>
                    <x-ui.field name="identifier" label="Placa, chassi ou RENAVAM" hint="Ex.: ABC1D23 · chassi com 17 caracteres · RENAVAM com 11 dígitos">
                        <x-ui.input
                            id="vehicle-search-identifier"
                            leading-icon="magnifying-glass"
                            :value="$identifier"
                            required
                            maxlength="40"
                            autocomplete="off"
                            autocapitalize="characters"
                            spellcheck="false"
                            enterkeyhint="search"
                            :autofocus="! $searchHasQuery"
                        />
                    </x-ui.field>
                    <x-ui.button type="submit" icon="magnifying-glass" full loading-label="Buscando…">Buscar</x-ui.button>
                </form>
            </x-ui.card>
            {{-- Com resultado parcial, o aviso "Dados parciais" ao lado do resultado já diz isso. --}}
            @unless ($searchMasked)
                <p class="flex items-start gap-2 px-1 text-xs text-muted-foreground">
                    <x-ui.icon name="lock-closed" class="mt-px size-4 shrink-0" />
                    <span>Para proteger o proprietário, chassi e RENAVAM aparecem parciais para quem não é dono do veículo.</span>
                </p>
            @endunless
        </div>

        <div class="min-w-0 space-y-4" id="resultado" data-slot="vehicle-search-result">
            @if (! $searchHasQuery)
                <x-ui.empty-state
                    icon="magnifying-glass"
                    heading-level="h2"
                    title="Digite a placa, o chassi ou o RENAVAM"
                    description="Placas antigas também são encontradas. O resultado traz a linha do tempo de manutenções do veículo, com a procedência de cada uma."
                >
                    <x-provenance-legend class="mx-auto w-fit text-left" />
                </x-ui.empty-state>
            @elseif ($vehicle)
                <div class="flex flex-wrap items-center gap-x-3 gap-y-2 text-sm text-muted-foreground" data-slot="vehicle-search-match">
                    <p>
                        Resultado para <span class="font-mono font-semibold tracking-wider text-foreground">{{ $identifier }}</span>@if ($matchLabel): encontrado {{ $matchLabel }}@endif.
                    </p>
                    @if ($searchMasked)
                        <x-ui.badge icon="lock-closed">Dados parciais para proteger o proprietário</x-ui.badge>
                    @elseif ($viewerOwnsVehicle)
                        <x-ui.badge variant="success" icon="check-circle">Veículo da sua conta</x-ui.badge>
                    @endif
                </div>

                @unless ($vehicle->hasVerifiedOwnership())
                    <x-ui.alert variant="warning" title="Propriedade não confirmada" data-ownership-unverified>
                        Quem cadastrou este veículo não enviou o CRLV-e, então ninguém confirmou que ele é dele.
                        As manutenções com Selo da oficina continuam valendo — elas são confirmadas por quem
                        prestou o serviço. As declaradas, não.
                    </x-ui.alert>
                @endunless

                @if ($matchedBy === \App\Support\Vehicle\VehicleLookupResult::MATCH_PREVIOUS_PLATE && $previousPlateEndedAt)
                    <x-ui.alert variant="info">
                        A placa {{ \App\Support\VehiclePlateSearch::normalize($identifier) }} pertenceu a este veículo até {{ $previousPlateEndedAt->format('d/m/Y') }}.
                        @if(filled($vehicle->license_plate))Placa atual: {{ $vehicle->license_plate }}.@endif
                    </x-ui.alert>
                @endif

                <x-vehicle.detail
                    :vehicle="$vehicle"
                    :portal="$detailPortal"
                    heading-level="h2"
                    :masked="$searchMasked"
                >
                    @if ($vehiclePortalUrl || $adminVehicleUrl || ($searchIsWorkshop && filled($vehicle->license_plate)))
                        <x-slot:actions>
                            @if ($adminVehicleUrl)
                                <x-ui.button variant="secondary" :href="$adminVehicleUrl">Abrir no painel admin</x-ui.button>
                            @endif
                            @if ($vehiclePortalUrl)
                                <x-ui.button icon="truck" :href="$vehiclePortalUrl">Abrir ficha do veículo</x-ui.button>
                            @endif
                            {{-- A oficina achou o carro pela busca: abre a OS com a placa já preenchida. --}}
                            @if ($searchIsWorkshop && filled($vehicle->license_plate))
                                <x-ui.button icon="plus" :href="route('workshop.maintenances.create', ['license_plate' => $vehicle->license_plate])">Abrir OS para este veículo</x-ui.button>
                            @endif
                        </x-slot:actions>
                    @endif
                </x-vehicle.detail>
            @else
                <x-ui.empty-state
                    icon="magnifying-glass"
                    heading-level="h2"
                    :title="'Nenhum veículo com “'.$identifier.'”'"
                    description="Não encontramos essa placa, chassi ou RENAVAM no RevisaLog."
                    data-vehicle-search-empty
                >
                    <ul role="list" class="list-disc space-y-1 pl-5 text-left">
                        <li>Confira letras e números: O e 0, I e 1 se confundem.</li>
                        <li>Placas antigas do veículo também são pesquisadas.</li>
                        <li>O chassi tem 17 caracteres e o RENAVAM, 11 dígitos.</li>
                        <li>Se o veículo ainda não está no RevisaLog, o histórico começa quando o dono o cadastrar.</li>
                    </ul>
                    <x-slot:actions>
                        @if ($addVehicleAction)
                            <x-ui.button :icon="$addVehicleAction['icon']" :href="route($addVehicleAction['route'])">{{ $addVehicleAction['label'] }}</x-ui.button>
                        @endif
                        <x-ui.button variant="secondary" icon="arrow-path" href="#vehicle-search-identifier">Tentar outra busca</x-ui.button>
                    </x-slot:actions>
                </x-ui.empty-state>
            @endif
        </div>
    </div>
</x-ui.container>
@endsection
