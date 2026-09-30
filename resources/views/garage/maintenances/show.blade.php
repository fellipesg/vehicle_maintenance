{{--
    Detalhe de uma manutenção no Lojista: o mesmo corpo do Proprietário e da Oficina
    (maintenances._detail: selo ou declaração, detalhes, garantia, peças e serviços, notas fiscais e
    fotos), com a trilha Estoque › veículo › serviço.
--}}
@extends('layouts.app')

@php
    $vehicleName = trim($vehicle->brand.' '.$vehicle->model);
    $maintenanceDescription = collect([
        $vehicleName,
        $vehicle->license_plate,
        $maintenance->maintenance_date?->format('d/m/Y'),
    ])->filter(fn (mixed $part): bool => filled($part))->implode(' · ');
@endphp

@section('title', $maintenance->maintenance_type)

@section('content')
    <x-ui.container size="lg" padded>
        <x-ui.page-header
            :title="$maintenance->maintenance_type"
            :description="$maintenanceDescription"
            :breadcrumbs="[['Estoque', route('garage.vehicles.index')], [$vehicleName, route('garage.vehicles.show', $vehicle)], [$maintenance->maintenance_type]]"
        >
            @if ($canAddMaintenance)
                <x-slot:actions>
                    <x-ui.button icon="plus" :href="route('garage.maintenances.create', ['vehicle_id' => $vehicle->id])">Registrar manutenção</x-ui.button>
                </x-slot:actions>
            @endif
        </x-ui.page-header>

        @include('maintenances._detail', ['maintenance' => $maintenance, 'portal' => \App\Enums\Portal::Dealer])
    </x-ui.container>
@endsection
