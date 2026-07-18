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
    <span class="badge badge-green mb-2">🏪 Garagem</span>
    <h1 class="text-3xl font-bold">{{ $vehicle->brand }} {{ $vehicle->model }}</h1>
    <p class="text-automotive-600">{{ $vehicle->year }} · {{ $vehicle->license_plate }}</p>

    <div class="mt-4">
        <a href="{{ route('garage.maintenances.create') }}?vehicle_id={{ $vehicle->id }}" class="btn-primary">+ Registrar revisão pré-venda</a>
    </div>

    <h2 class="mb-4 mt-8 text-xl font-semibold">Revisões Documentadas</h2>
    @forelse($vehicle->maintenances->sortByDesc('maintenance_date') as $maintenance)
        <div class="card mb-3">
            <span class="badge badge-orange">{{ $categories[$maintenance->service_category] ?? '' }}</span>
            <p class="mt-1 font-semibold">{{ $maintenance->maintenance_type }}</p>
            <p class="text-sm text-automotive-600">{{ $maintenance->maintenance_date->format('d/m/Y') }}</p>
        </div>
    @empty
        <div class="card text-center text-automotive-500">Nenhuma revisão documentada.</div>
    @endforelse
</div>
@endsection
