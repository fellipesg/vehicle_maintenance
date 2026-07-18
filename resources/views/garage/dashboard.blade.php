@extends('layouts.app')

@section('title', 'Dashboard Garagem')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-8">
    <div class="mb-8">
        <span class="badge badge-green mb-2">🏪 Portal da Garagem</span>
        <h1 class="text-3xl font-bold">Olá, {{ auth()->user()->name }}!</h1>
        <p class="text-automotive-600">Gerencie seu estoque e documente revisões pré-venda</p>
    </div>

    <div class="mb-8 grid gap-4 sm:grid-cols-3">
        <div class="stat-card border-emerald-200">
            <p class="text-sm text-automotive-600">Veículos em Estoque</p>
            <p class="text-3xl font-bold text-emerald-700">{{ $vehicles->count() }}</p>
        </div>
        <div class="stat-card">
            <p class="text-sm text-automotive-600">Manutenções Registradas</p>
            <p class="text-3xl font-bold">{{ $recentMaintenances->count() }}</p>
        </div>
        <div class="stat-card">
            <p class="text-sm text-automotive-600">Ações</p>
            <div class="mt-2 flex gap-2">
                <a href="{{ route('garage.vehicles.create') }}" class="btn-primary !py-1.5 !text-xs">+ Estoque</a>
                <a href="{{ route('garage.maintenances.create') }}" class="btn-secondary !py-1.5 !text-xs">+ Revisão</a>
            </div>
        </div>
    </div>

    <div class="grid gap-8 lg:grid-cols-2">
        <div>
            <h2 class="mb-4 text-xl font-semibold">🚗 Estoque</h2>
            @forelse($vehicles as $vehicle)
                <a href="{{ route('garage.vehicles.show', $vehicle) }}" class="card mb-3 block hover:border-emerald-300">
                    <p class="font-semibold">{{ $vehicle->brand }} {{ $vehicle->model }}</p>
                    <p class="text-sm text-automotive-600">{{ $vehicle->year }} · {{ $vehicle->license_plate }} · {{ $vehicle->maintenances_count }} manutenções</p>
                </a>
            @empty
                <div class="card text-center">
                    <p class="text-automotive-500">Estoque vazio.</p>
                    <a href="{{ route('garage.vehicles.create') }}" class="btn-primary mt-4">Adicionar veículo</a>
                </div>
            @endforelse
        </div>
        <div>
            <h2 class="mb-4 text-xl font-semibold">🔧 Revisões Recentes</h2>
            @forelse($recentMaintenances as $maintenance)
                <div class="card mb-3">
                    <p class="font-semibold">{{ $maintenance->maintenance_type }}</p>
                    <p class="text-sm text-automotive-600">{{ $maintenance->vehicle->brand }} {{ $maintenance->vehicle->model }} · {{ $maintenance->maintenance_date->format('d/m/Y') }}</p>
                </div>
            @empty
                <div class="card text-center text-automotive-500">Nenhuma revisão registrada.</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
