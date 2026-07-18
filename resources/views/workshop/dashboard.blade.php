@extends('layouts.app')

@section('title', 'Dashboard Oficina')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-8">
    <div class="mb-8">
        <span class="badge badge-orange mb-2">🔧 Portal da Oficina</span>
        <h1 class="text-3xl font-bold">Olá, {{ auth()->user()->name }}!</h1>
        <p class="text-automotive-600">Gerencie sua oficina e acompanhe os serviços realizados</p>
    </div>

    @if(!$workshop)
        <div class="card border-orange-200 bg-orange-50 text-center">
            <p class="text-lg font-semibold text-orange-800">Complete o cadastro da sua oficina</p>
            <p class="mt-2 text-sm text-orange-700">Para aparecer no diretório e vincular serviços, cadastre os dados da oficina.</p>
            <a href="{{ route('workshop.profile.create') }}" class="btn-primary mt-4">Cadastrar oficina</a>
        </div>
    @else
        <div class="mb-8 grid gap-4 sm:grid-cols-3">
            <div class="stat-card border-orange-200">
                <p class="text-sm text-automotive-600">Serviços Realizados</p>
                <p class="text-3xl font-bold text-wrench-600">{{ $maintenancesCount }}</p>
            </div>
            <div class="stat-card col-span-2">
                <p class="text-sm text-automotive-600">Sua Oficina</p>
                <p class="text-xl font-bold">{{ $workshop->name }}</p>
                <p class="text-sm text-automotive-600">{{ $workshop->city }}/{{ $workshop->state }}</p>
            </div>
        </div>

        <h2 class="mb-4 text-xl font-semibold">Serviços Recentes</h2>
        @forelse($recentMaintenances as $maintenance)
            <div class="card mb-3">
                <p class="font-semibold">{{ $maintenance->maintenance_type }}</p>
                <p class="text-sm text-automotive-600">{{ $maintenance->vehicle->brand }} {{ $maintenance->vehicle->model }} · {{ $maintenance->maintenance_date->format('d/m/Y') }}</p>
            </div>
        @empty
            <div class="card text-center text-automotive-500">Nenhum serviço vinculado à sua oficina ainda.</div>
        @endforelse
    @endif
</div>
@endsection
