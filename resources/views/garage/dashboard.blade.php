@extends('layouts.app')

@section('title', 'Dashboard Garagem')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-6">
    <div class="mb-6">
        <span class="badge badge-blue mb-2">Portal da Garagem</span>
        <h1 class="text-2xl font-bold text-automotive-900">Olá, {{ auth()->user()->name }}</h1>
        <p class="mt-1 text-sm text-automotive-600">Gerencie seu estoque e documente revisões pré-venda</p>
    </div>

    <div class="mb-6 grid gap-3 sm:grid-cols-3">
        <div class="stat-card !p-4">
            <p class="text-sm text-automotive-600">Veículos em Estoque</p>
            <p class="text-2xl font-bold text-automotive-900">{{ $vehicles->count() }}</p>
        </div>
        <div class="stat-card !p-4">
            <p class="text-sm text-automotive-600">Manutenções Registradas</p>
            <p class="text-2xl font-bold text-automotive-900">{{ $recentMaintenances->count() }}</p>
        </div>
        <div class="stat-card !p-4">
            <p class="text-sm text-automotive-600">Ações</p>
            <div class="mt-2 flex flex-wrap gap-2">
                <a href="{{ route('garage.vehicles.create') }}" class="btn-primary !py-1.5 !text-xs">+ Estoque</a>
                <a href="{{ route('garage.maintenances.create') }}" class="btn-secondary !py-1.5 !text-xs">+ Revisão</a>
            </div>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <div>
            <h2 class="mb-3 text-lg font-semibold text-automotive-900">Estoque</h2>
            @forelse($vehicles as $vehicle)
                <a href="{{ route('garage.vehicles.show', $vehicle) }}" class="card mb-2 block !p-4 transition hover:border-wrench-300 hover:shadow-sm">
                    <p class="font-semibold text-automotive-900">{{ $vehicle->brand }} {{ $vehicle->model }}</p>
                    <p class="text-sm text-automotive-600">{{ $vehicle->year }} · {{ $vehicle->license_plate }} · {{ $vehicle->maintenances_count }} manutenções</p>
                </a>
            @empty
                <div class="card !p-8 text-center">
                    <p class="text-automotive-600">Estoque vazio.</p>
                    <p class="mt-1 text-sm text-automotive-500">Adicione veículos para documentar revisões pré-venda.</p>
                    <a href="{{ route('garage.vehicles.create') }}" class="btn-primary mt-4">Adicionar veículo</a>
                </div>
            @endforelse
        </div>
        <div>
            <h2 class="mb-3 text-lg font-semibold text-automotive-900">Revisões Recentes</h2>
            @forelse($recentMaintenances as $maintenance)
                <div class="card mb-2 !p-4">
                    <p class="font-semibold text-automotive-900">{{ $maintenance->maintenance_type }}</p>
                    <p class="text-sm text-automotive-600">{{ $maintenance->vehicle->brand }} {{ $maintenance->vehicle->model }} · {{ $maintenance->maintenance_date->format('d/m/Y') }}</p>
                </div>
            @empty
                <div class="card !p-8 text-center">
                    <p class="text-automotive-600">Nenhuma revisão registrada.</p>
                    <p class="mt-1 text-sm text-automotive-500">Documente revisões para valorizar o estoque.</p>
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection
