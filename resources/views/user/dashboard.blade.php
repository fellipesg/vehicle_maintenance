@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold">Olá, {{ auth()->user()->name }}! 👋</h1>
        <p class="text-automotive-600">Gerencie seus veículos e manutenções</p>
    </div>

    <div class="mb-8 grid gap-4 sm:grid-cols-3">
        <div class="stat-card">
            <p class="text-sm text-automotive-600">Meus Veículos</p>
            <p class="text-3xl font-bold text-automotive-900">{{ $vehicles->count() }}</p>
        </div>
        <div class="stat-card">
            <p class="text-sm text-automotive-600">Manutenções Recentes</p>
            <p class="text-3xl font-bold text-automotive-900">{{ $recentMaintenances->count() }}</p>
        </div>
        <div class="stat-card">
            <p class="text-sm text-automotive-600">Ações Rápidas</p>
            <div class="mt-2 flex flex-wrap gap-2">
                <a href="{{ route('user.vehicles.create') }}" class="btn-primary !py-1.5 !text-xs">+ Veículo</a>
                <a href="{{ route('user.maintenances.create') }}" class="btn-secondary !py-1.5 !text-xs">+ Manutenção</a>
            </div>
        </div>
    </div>

    <div class="grid gap-8 lg:grid-cols-2">
        <div>
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-xl font-semibold">🚗 Meus Veículos</h2>
                <a href="{{ route('user.vehicles.index') }}" class="text-sm text-wrench-600 hover:underline">Ver todos</a>
            </div>
            @forelse($vehicles as $vehicle)
                <a href="{{ route('user.vehicles.show', $vehicle) }}" class="card mb-3 block transition hover:border-wrench-300 hover:shadow-md">
                    <div class="flex justify-between">
                        <div>
                            <p class="font-semibold">{{ $vehicle->brand }} {{ $vehicle->model }}</p>
                            <p class="text-sm text-automotive-600">{{ $vehicle->year }} · {{ $vehicle->license_plate }}</p>
                        </div>
                        <span class="badge badge-blue">{{ $vehicle->maintenances_count }} manutenções</span>
                    </div>
                </a>
            @empty
                <div class="card text-center text-automotive-500">
                    <p>Nenhum veículo cadastrado.</p>
                    <a href="{{ route('user.vehicles.create') }}" class="btn-primary mt-4">Cadastrar veículo</a>
                </div>
            @endforelse
        </div>

        <div>
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-xl font-semibold">🔧 Manutenções Recentes</h2>
                <a href="{{ route('user.maintenances.index') }}" class="text-sm text-wrench-600 hover:underline">Ver todas</a>
            </div>
            @forelse($recentMaintenances as $maintenance)
                <a href="{{ route('user.maintenances.show', $maintenance) }}" class="card mb-3 block transition hover:border-wrench-300">
                    <p class="font-semibold">{{ $maintenance->maintenance_type }}</p>
                    <p class="text-sm text-automotive-600">{{ $maintenance->vehicle->brand }} {{ $maintenance->vehicle->model }} · {{ $maintenance->maintenance_date->format('d/m/Y') }}</p>
                </a>
            @empty
                <div class="card text-center text-automotive-500">Nenhuma manutenção registrada.</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
