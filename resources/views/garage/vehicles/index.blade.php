@extends('layouts.app')

@section('title', 'Estoque')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-8">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <span class="badge badge-green mb-2">🏪 Garagem</span>
            <h1 class="text-3xl font-bold">Estoque de Veículos</h1>
        </div>
        <a href="{{ route('garage.vehicles.create') }}" class="btn-primary">+ Adicionar</a>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse($vehicles as $vehicle)
            <div class="card">
                <h2 class="text-lg font-semibold">{{ $vehicle->brand }} {{ $vehicle->model }}</h2>
                <p class="text-sm text-automotive-600">{{ $vehicle->year }} · {{ $vehicle->license_plate }}</p>
                <p class="mt-2 text-sm text-automotive-500">{{ $vehicle->maintenances_count }} revisões documentadas</p>
                <a href="{{ route('garage.vehicles.show', $vehicle) }}" class="btn-primary mt-4 !py-1.5 !text-xs w-full text-center">Ver detalhes</a>
            </div>
        @empty
            <div class="card col-span-full text-center">
                <p class="text-automotive-500">Nenhum veículo no estoque.</p>
                <a href="{{ route('garage.vehicles.create') }}" class="btn-primary mt-4">Adicionar veículo</a>
            </div>
        @endforelse
    </div>
</div>
@endsection
