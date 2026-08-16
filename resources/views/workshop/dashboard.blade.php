@extends('layouts.app')

@section('title', 'Dashboard Oficina')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-6">
    <div class="mb-6">
        <span class="badge badge-blue mb-2">Portal da Oficina</span>
        <h1 class="text-2xl font-bold text-automotive-900">Olá, {{ auth()->user()->name }}</h1>
        <p class="mt-1 text-sm text-automotive-600">Gerencie sua oficina e acompanhe os serviços realizados</p>
    </div>

    @if(!$workshop)
        <div class="card !p-8 text-center">
            <p class="text-lg font-semibold text-automotive-900">Complete o cadastro da sua oficina</p>
            <p class="mt-2 text-sm text-automotive-600">Para aparecer no diretório e vincular serviços, cadastre os dados da oficina.</p>
            <a href="{{ route('workshop.profile.create') }}" class="btn-primary mt-4">Cadastrar oficina</a>
        </div>
    @else
        <div class="mb-6 grid gap-3 sm:grid-cols-3">
            <div class="stat-card !p-4">
                <p class="text-sm text-automotive-600">Serviços Realizados</p>
                <p class="text-2xl font-bold text-automotive-900">{{ $maintenancesCount }}</p>
            </div>
            <div class="stat-card !p-4 sm:col-span-2">
                <p class="text-sm text-automotive-600">Sua Oficina</p>
                <p class="text-xl font-bold text-automotive-900">{{ $workshop->name }}</p>
                <p class="text-sm text-automotive-600">{{ $workshop->city }}/{{ $workshop->state }}</p>
            </div>
        </div>

        <h2 class="mb-3 text-lg font-semibold text-automotive-900">Serviços Recentes</h2>
        @forelse($recentMaintenances as $maintenance)
            <div class="card mb-2 !p-4">
                <p class="font-semibold text-automotive-900">{{ $maintenance->maintenance_type }}</p>
                <p class="text-sm text-automotive-600">{{ $maintenance->vehicle->brand }} {{ $maintenance->vehicle->model }} · {{ $maintenance->maintenance_date->format('d/m/Y') }}</p>
            </div>
        @empty
            <div class="card !p-8 text-center">
                <p class="text-automotive-600">Nenhum serviço vinculado à sua oficina ainda.</p>
                <p class="mt-1 text-sm text-automotive-500">Os serviços registrados com sua oficina aparecerão aqui.</p>
            </div>
        @endforelse
    @endif
</div>
@endsection
