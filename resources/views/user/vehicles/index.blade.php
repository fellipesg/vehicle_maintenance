@extends('layouts.app')

@section('title', 'Meus Veículos')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-8">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-3xl font-bold">🚗 Meus Veículos</h1>
        <a href="{{ route('user.vehicles.create') }}" class="btn-primary">+ Novo Veículo</a>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse($vehicles as $vehicle)
            <div class="card">
                <div class="mb-3 flex items-start justify-between">
                    <div>
                        <h2 class="text-lg font-semibold">{{ $vehicle->brand }} {{ $vehicle->model }}</h2>
                        <p class="text-sm text-automotive-600">{{ $vehicle->year }} · {{ $vehicle->color ?? '—' }}</p>
                    </div>
                    <span class="badge badge-blue">{{ $vehicle->license_plate }}</span>
                </div>
                <p class="mb-4 text-sm text-automotive-500">{{ $vehicle->maintenances_count }} manutenções registradas</p>
                <div class="flex gap-2">
                    <a href="{{ route('user.vehicles.show', $vehicle) }}" class="btn-primary !py-1.5 !text-xs flex-1 text-center">Ver</a>
                    <a href="{{ route('user.vehicles.edit', $vehicle) }}" class="btn-secondary !py-1.5 !text-xs">Editar</a>
                </div>
            </div>
        @empty
            <div class="card col-span-full text-center">
                <p class="text-automotive-500">Nenhum veículo cadastrado ainda.</p>
                <a href="{{ route('user.vehicles.create') }}" class="btn-primary mt-4">Cadastrar primeiro veículo</a>
            </div>
        @endforelse
    </div>
</div>
@endsection
