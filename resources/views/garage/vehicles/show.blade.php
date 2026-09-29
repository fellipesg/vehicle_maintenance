{{--
    Ficha do veículo no Lojista: a mesma <x-vehicle.detail> dos outros portais (capa inteira, Km
    atual, Próxima revisão, Procedência, linha do tempo com os pontos e o filtro abaixo, histórico
    e documentos). Ações: "Editar veículo e capas" (só o dono atual) e "Registrar manutenção" (o
    primário, por último). Em consignação o aviso explica por que as ações não aparecem.
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

            @unless ($canAddMaintenance)
                <x-slot:notice>
                    <x-ui.alert variant="info" :title="$consignmentGrant !== null ? 'Veículo em consignação' : 'Somente consulta'" data-add-maintenance-denied>
                        <p>
                            {{ $consignmentGrant !== null
                                ? 'Veículo em consignação: só o proprietário registra manutenções.'
                                : 'Só o dono atual registra manutenções neste veículo.' }}
                            O histórico abaixo é o que o proprietário e as oficinas registraram.
                        </p>
                        @if ($consignmentGrant !== null)
                            <div class="mt-3">
                                @include('garage.vehicles._consignment-status', ['grant' => $consignmentGrant, 'compact' => false])
                            </div>
                        @endif
                    </x-ui.alert>
                </x-slot:notice>
            @endunless

            @if ($canAddMaintenance)
                <x-slot:empty-actions>
                    <x-ui.button icon="plus" :href="$registerMaintenanceUrl">Registrar manutenção</x-ui.button>
                </x-slot:empty-actions>
            @endif
        </x-vehicle.detail>
    </x-ui.container>
@endsection
