@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-6">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-automotive-900">Olá, {{ auth()->user()->name }}</h1>
        <p class="mt-1 text-sm text-automotive-600">Gerencie seus veículos e manutenções</p>
    </div>

    <div class="mb-6 grid gap-3 sm:grid-cols-3">
        <div class="stat-card !p-4">
            <p class="text-sm text-automotive-600">Meus Veículos</p>
            <p class="text-2xl font-bold text-automotive-900">{{ $vehicles->count() }}</p>
        </div>
        <div class="stat-card !p-4">
            <p class="text-sm text-automotive-600">Manutenções Recentes</p>
            <p class="text-2xl font-bold text-automotive-900">{{ $recentMaintenances->count() }}</p>
        </div>
        <div class="stat-card !p-4">
            <p class="text-sm text-automotive-600">Ações Rápidas</p>
            <div class="mt-2 flex flex-wrap gap-2">
                <a href="{{ route('user.vehicles.create') }}" class="btn-primary !py-1.5 !text-xs">+ Veículo</a>
                <a href="{{ route('user.maintenances.create') }}" class="btn-secondary !py-1.5 !text-xs">+ Manutenção</a>
            </div>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <div>
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-automotive-900">Meus Veículos</h2>
                <a href="{{ route('user.vehicles.index') }}" class="text-sm text-wrench-600 hover:underline">Ver todos</a>
            </div>
            @forelse($vehicles as $vehicle)
                <a href="{{ route('user.vehicles.show', $vehicle) }}" class="card mb-2 block !p-4 transition hover:border-wrench-300 hover:shadow-sm">
                    <div class="flex justify-between gap-3">
                        <div>
                            <p class="font-semibold text-automotive-900">{{ $vehicle->brand }} {{ $vehicle->model }}</p>
                            <p class="text-sm text-automotive-600">{{ $vehicle->year }} · {{ $vehicle->license_plate }}</p>
                        </div>
                        <span class="badge badge-blue shrink-0">{{ $vehicle->maintenances_count }} manutenções</span>
                    </div>
                </a>
            @empty
                <div class="card !p-8 text-center">
                    <p class="text-automotive-600">Nenhum veículo cadastrado ainda.</p>
                    <p class="mt-1 text-sm text-automotive-500">Adicione seu primeiro veículo para começar o histórico.</p>
                    <a href="{{ route('user.vehicles.create') }}" class="btn-primary mt-4">Cadastrar veículo</a>
                </div>
            @endforelse
        </div>

        <div>
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-automotive-900">Manutenções Recentes</h2>
                <a href="{{ route('user.maintenances.index') }}" class="text-sm text-wrench-600 hover:underline">Ver todas</a>
            </div>
            @forelse($recentMaintenances as $maintenance)
                <a href="{{ route('user.maintenances.show', $maintenance) }}" class="card mb-2 block !p-4 transition hover:border-wrench-300">
                    <p class="font-semibold text-automotive-900">{{ $maintenance->maintenance_type }}</p>
                    <p class="text-sm text-automotive-600">{{ $maintenance->vehicle->brand }} {{ $maintenance->vehicle->model }} · {{ $maintenance->maintenance_date->format('d/m/Y') }}</p>
                </a>
            @empty
                <div class="card !p-8 text-center">
                    <p class="text-automotive-600">Nenhuma manutenção registrada.</p>
                    <p class="mt-1 text-sm text-automotive-500">Registre serviços para acompanhar o histórico do veículo.</p>
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection
