{{--
    Registrar manutenção (proprietário). Vindo da ficha (?vehicle_id=), o veículo já vem escolhido
    e "Cancelar" e o envio voltam para a ficha. Vindo de "Registrar manutenção aqui" nas Oficinas da
    rede (?workshop_id=), a oficina já vem escolhida. Sem veículo, o estado vazio leva ao assistente
    "Adicionar veículo".
--}}
@extends('layouts.app')

@section('title', 'Registrar manutenção')

@php
    $createBreadcrumbs = $selectedVehicle !== null
        ? [
            ['Meus veículos', route('user.vehicles.index')],
            [trim($selectedVehicle->brand.' '.$selectedVehicle->model), route('user.vehicles.show', $selectedVehicle)],
            ['Registrar manutenção'],
        ]
        : [['Manutenções', route('user.maintenances.index')], ['Registrar manutenção']];
@endphp

@section('content')
    <x-ui.container size="md" padded data-owner-page="maintenance-create">
        <x-ui.page-header
            title="Registrar manutenção"
            description="Um serviço feito no seu veículo, com data, quilometragem e a nota fiscal, se tiver."
            :breadcrumbs="$createBreadcrumbs"
        />

        @if ($vehicles->isEmpty())
            <x-ui.empty-state
                icon="truck"
                heading-level="h2"
                title="Adicione um veículo primeiro"
                description="A manutenção fica no histórico do veículo. Adicione o seu pelo CRLV-e e volte aqui."
            >
                <x-slot:actions>
                    <x-ui.button icon="plus" :href="route('user.vehicles.create')">Adicionar veículo</x-ui.button>
                </x-slot:actions>
            </x-ui.empty-state>
        @else
            <form method="POST" action="{{ route('user.maintenances.store') }}" enctype="multipart/form-data">
                @csrf
                @include('user.maintenances._form', [
                    'maintenance' => null,
                    'submitLabel' => 'Registrar manutenção',
                    'loadingLabel' => 'Registrando…',
                ])
            </form>
        @endif
    </x-ui.container>
@endsection
