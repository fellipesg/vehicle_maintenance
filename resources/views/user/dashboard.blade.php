@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-6" data-api-page="dashboard">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-automotive-900">Olá, {{ auth()->user()->name }}</h1>
        <p class="mt-1 text-sm text-automotive-600">Gerencie seus veículos e manutenções</p>
    </div>

    <div class="mb-6 grid gap-3 sm:grid-cols-3">
        <div class="stat-card !p-4">
            <p class="text-sm text-automotive-600">Meus Veículos</p>
            <p class="text-2xl font-bold text-automotive-900" data-vehicle-count>—</p>
        </div>
        <div class="stat-card !p-4">
            <p class="text-sm text-automotive-600">Manutenções Recentes</p>
            <p class="text-2xl font-bold text-automotive-900" data-maintenance-count>—</p>
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
            <div data-dashboard-vehicles>
                <div class="card !p-8 text-center text-automotive-500">Carregando veículos...</div>
            </div>
        </div>

        <div>
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-automotive-900">Manutenções Recentes</h2>
                <a href="{{ route('user.maintenances.index') }}" class="text-sm text-wrench-600 hover:underline">Ver todas</a>
            </div>
            <div data-dashboard-maintenances>
                <div class="card !p-8 text-center text-automotive-500">Carregando manutenções...</div>
            </div>
        </div>
    </div>
</div>
@endsection
