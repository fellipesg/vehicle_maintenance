{{--
    Ficha do veículo no Lojista: a mesma <x-vehicle.detail> dos outros portais (capa inteira, Km
    atual, Próxima revisão, Procedência, linha do tempo com os pontos e o filtro abaixo, histórico
    e documentos). Ações: "Editar veículo e capas" (só o dono atual) e "Registrar manutenção" (o
    primário, por último).

    Em consignação o aviso traz o status do histórico anterior, o pedido de liberação ao proprietário
    e o encerramento da consignação. Registrar manutenção continua liberado — o que o lojista não vê
    sem liberação é o histórico que o veículo já tinha.
--}}
@extends('layouts.app')

@php
    $vehicleName = trim($vehicle->brand.' '.$vehicle->model);
    $registerMaintenanceUrl = route('garage.maintenances.create', ['vehicle_id' => $vehicle->id]);
@endphp

@section('title', $vehicleName)

@section('content')
    <x-ui.container size="lg" padded>
        <x-vehicle.detail
            :vehicle="$vehicle"
            :portal="\App\Enums\Portal::Dealer"
            :breadcrumbs="[['Estoque', route('garage.vehicles.index')], [$vehicleName]]"
            :edit-url="$canEdit ? route('garage.vehicles.edit', $vehicle) : null"
            :masked="$identifiersMasked"
        >
            @if ($canEdit || $canAddMaintenance)
                <x-slot:actions>
                    @if ($canEdit)
                        <x-ui.button variant="secondary" icon="pencil-square" :href="route('garage.vehicles.edit', $vehicle)">Editar veículo e capas</x-ui.button>
                    @endif
                    @if ($canAddMaintenance)
                        <x-ui.button icon="plus" :href="$registerMaintenanceUrl">Registrar manutenção</x-ui.button>
                    @endif
                </x-slot:actions>
            @endif

            @if ($consignment !== null)
                <x-slot:notice>
                    <x-ui.alert :variant="$consignment->isDisputed() ? 'danger' : 'info'" title="Veículo em consignação" data-consignment-notice>
                        <p>
                            O veículo é de <strong>{{ $consignment->owner_name }}</strong> e está na loja para venda desde
                            {{ $consignment->started_at->format('d/m/Y') }}. Ele é avisado de cada manutenção que você registrar.
                        </p>

                        <div class="mt-3">
                            @include('garage.vehicles._consignment-status', ['consignment' => $consignment, 'compact' => false])
                        </div>

                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            @if (! $consignment->isDisputed() && ! $consignment->grantsHistoryAccess() && ! $consignment->isHistoryReviewPending())
                                <form method="POST" action="{{ route('garage.vehicles.consignment.request-history', $vehicle) }}">
                                    @csrf
                                    <x-ui.button variant="secondary" size="sm" type="submit" loading-label="Enviando…">
                                        Pedir liberação do histórico
                                    </x-ui.button>
                                </form>
                            @endif

                            <form
                                method="POST"
                                action="{{ route('garage.vehicles.consignment.end', $vehicle) }}"
                                class="flex flex-wrap items-center gap-2"
                                data-confirm="Você perde o acesso ao veículo, mas as manutenções que registrou continuam no histórico."
                                data-confirm-title="Encerrar a consignação deste veículo?"
                                data-confirm-action-label="Encerrar consignação"
                            >
                                @csrf
                                <x-ui.select
                                    name="end_reason"
                                    class="text-sm"
                                    aria-label="Motivo do encerramento"
                                    :options="['sold' => 'Veículo vendido', 'owner_withdrew' => 'Devolvido ao proprietário']"
                                />
                                <x-ui.button variant="secondary" size="sm" type="submit" loading-label="Encerrando…">
                                    Encerrar consignação
                                </x-ui.button>
                            </form>
                        </div>

                        @error('consignment')<p class="mt-3 text-sm text-danger-foreground">{{ $message }}</p>@enderror
                        @error('end_reason')<p class="mt-3 text-sm text-danger-foreground">{{ $message }}</p>@enderror
                    </x-ui.alert>
                </x-slot:notice>
            @elseif (! $canAddMaintenance)
                <x-slot:notice>
                    <x-ui.alert variant="info" title="Somente consulta" data-add-maintenance-denied>
                        <p>
                            Só o dono atual registra manutenções neste veículo.
                            O histórico abaixo é o que o proprietário e as oficinas registraram.
                        </p>
                    </x-ui.alert>
                </x-slot:notice>
            @endif

            @if ($canAddMaintenance)
                <x-slot:empty-actions>
                    <x-ui.button icon="plus" :href="$registerMaintenanceUrl">Registrar manutenção</x-ui.button>
                </x-slot:empty-actions>
            @endif
        </x-vehicle.detail>
    </x-ui.container>
@endsection
