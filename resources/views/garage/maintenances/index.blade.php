@extends('layouts.app')

@section('title', 'Manutenções')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-8">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <span class="badge badge-green mb-2">🏪 Garagem</span>
            <h1 class="text-3xl font-bold">Revisões Pré-Venda</h1>
        </div>
        <a href="{{ route('garage.maintenances.create') }}" class="btn-primary">+ Nova Revisão</a>
    </div>

    @forelse($maintenances as $maintenance)
        <div class="card mb-3">
            <p class="font-semibold">{{ $maintenance->maintenance_type }}</p>
            <p class="text-sm text-automotive-600">{{ $maintenance->vehicle->brand }} {{ $maintenance->vehicle->model }} · {{ $maintenance->vehicle->license_plate }} · {{ $maintenance->maintenance_date->format('d/m/Y') }}</p>
        </div>
    @empty
        <div class="card text-center text-automotive-500">Nenhuma revisão registrada.</div>
    @endforelse

    <div class="mt-4">{{ $maintenances->links() }}</div>
</div>
@endsection
