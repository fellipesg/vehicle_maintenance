{{--
    Editar manutenção (proprietário): só as declaradas (a MaintenancePolicy barra as que têm Selo
    da oficina). O veículo não muda; a quilometragem continua presa aos registros vizinhos.
--}}
@extends('layouts.app')

@section('title', 'Editar manutenção')

@php
    $editVehicle = $maintenance->vehicle;
    $editVehicleName = $editVehicle !== null ? trim($editVehicle->brand.' '.$editVehicle->model) : null;
    $editBreadcrumbs = $canViewVehicle && $editVehicle !== null
        ? [
            ['Meus veículos', route('user.vehicles.index')],
            [$editVehicleName, route('user.vehicles.show', $editVehicle)],
            [$maintenance->maintenance_type, route('user.maintenances.show', $maintenance)],
            ['Editar'],
        ]
        : [
            ['Manutenções', route('user.maintenances.index')],
            [$maintenance->maintenance_type, route('user.maintenances.show', $maintenance)],
            ['Editar'],
        ];
    $editDescription = collect([
        $maintenance->maintenance_type,
        $editVehicle?->license_plate,
        $maintenance->maintenance_date?->format('d/m/Y'),
    ])->filter(fn ($part): bool => filled($part))->implode(' · ');
@endphp

@section('content')
    <x-ui.container size="md" padded data-owner-page="maintenance-edit">
        <x-ui.page-header
            title="Editar manutenção"
            :description="$editDescription"
            :breadcrumbs="$editBreadcrumbs"
        />

        <form method="POST" action="{{ route('user.maintenances.update', $maintenance) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            @include('user.maintenances._form', [
                'maintenance' => $maintenance,
                'vehicles' => collect(),
                'selectedVehicle' => null,
                'selectedWorkshopId' => null,
                'submitLabel' => 'Salvar alterações',
                'loadingLabel' => 'Salvando…',
                'cancelUrl' => route('user.maintenances.show', $maintenance),
            ])
        </form>
    </x-ui.container>
@endsection
