@extends('layouts.app')

@section('title', $vehicle->brand . ' ' . $vehicle->model)

@php
    $categories = [
        'mechanical' => 'Mecânica', 'electrical' => 'Elétrica', 'suspension' => 'Suspensão',
        'painting' => 'Pintura', 'finishing' => 'Acabamento', 'interior' => 'Interior', 'other' => 'Outros',
    ];
@endphp

@section('content')
<div class="mx-auto max-w-7xl px-4 py-8">
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold">{{ $vehicle->brand }} {{ $vehicle->model }}</h1>
            <p class="text-automotive-600">{{ $vehicle->year }} · {{ $vehicle->color ?? '—' }} · {{ $vehicle->license_plate }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('user.vehicles.export-pdf', $vehicle) }}" class="btn-secondary">📄 Exportar PDF</a>
            <a href="{{ route('user.vehicles.edit', $vehicle) }}" class="btn-secondary">Editar</a>
            <a href="{{ route('user.maintenances.create') }}?vehicle_id={{ $vehicle->id }}" class="btn-primary">+ Manutenção</a>
        </div>
    </div>

    <div class="mb-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-6">
        <div class="stat-card"><p class="text-sm text-automotive-600">RENAVAM</p><p class="font-semibold">{{ $vehicle->renavam }}</p></div>
        <div class="stat-card"><p class="text-sm text-automotive-600">CRV</p><p class="font-semibold text-sm">{{ $vehicle->crv_number ?? '—' }}</p></div>
        <div class="stat-card"><p class="text-sm text-automotive-600">Chassi</p><p class="font-semibold text-sm">{{ $vehicle->chassis ?? '—' }}</p></div>
        <div class="stat-card"><p class="text-sm text-automotive-600">Motorização</p><p class="font-semibold text-sm">{{ $vehicle->motorization ?? '—' }}</p></div>
        <div class="stat-card"><p class="text-sm text-automotive-600">Código do motor</p><p class="font-semibold text-sm">{{ $vehicle->engine ?? '—' }}</p></div>
        <div class="stat-card"><p class="text-sm text-automotive-600">Manutenções</p><p class="text-2xl font-bold">{{ $vehicle->maintenances->count() }}</p></div>
    </div>

    <h2 class="mb-4 text-xl font-semibold">🔧 Histórico de Manutenções</h2>
    @include('partials.vehicle-maintenance-history', [
        'vehicle' => $vehicle,
        'categories' => $categories,
        'canViewMaintenances' => $canViewMaintenances,
    ])
</div>
@endsection
