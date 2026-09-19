@extends('layouts.app')

@section('title', 'Veículo — Admin')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-8">
    <a href="{{ route('admin.vehicles.index') }}" class="text-sm text-wrench-600 hover:underline">← Todos os veículos</a>

    <div class="mt-4 flex flex-wrap gap-6">
        <x-vehicle-cover :vehicle="$vehicle" class="h-40 w-64 rounded-lg object-cover shadow" />
        <div>
            <h1 class="text-3xl font-bold">{{ $vehicle->brand }} {{ $vehicle->model }}</h1>
            <p class="text-automotive-600">
                {{ $vehicle->year ?? '—' }} · Placa {{ $vehicle->license_plate ?? '—' }} · Chassi {{ $vehicle->chassis ?? '—' }}
            </p>
            @if($vehicle->renavam)
                <p class="text-sm text-automotive-500">RENAVAM {{ $vehicle->renavam }}</p>
            @endif
            @php $owner = $vehicle->owners->first(); @endphp
            @if($owner)
                <p class="mt-2 text-sm">
                    Dono atual:
                    <a href="{{ route('admin.users.show', $owner) }}" class="font-medium text-wrench-600 hover:underline">{{ $owner->name }}</a>
                </p>
            @endif
        </div>
    </div>

    <h2 class="mb-4 mt-8 text-xl font-semibold">Manutenções</h2>
    @forelse($vehicle->maintenances as $maintenance)
        <div class="card mb-3">
            <div class="flex flex-wrap justify-between gap-2">
                <div>
                    <p class="font-medium">{{ $maintenance->maintenance_type }}</p>
                    <p class="text-sm text-automotive-600">
                        {{ $maintenance->maintenance_date->format('d/m/Y') }}
                        · {{ $maintenance->provenance_card_label }}
                    </p>
                    @if($maintenance->workshop_name || $maintenance->workshop)
                        <p class="text-sm text-automotive-500">🔧 {{ $maintenance->workshop?->name ?? $maintenance->workshop_name }}</p>
                    @endif
                </div>
                @if($maintenance->isVerified())
                    <span class="badge badge-green">Verificada</span>
                @endif
            </div>
            @if($maintenance->description)
                <p class="mt-2 text-sm text-automotive-600">{{ Str::limit($maintenance->description, 300) }}</p>
            @endif
        </div>
    @empty
        <p class="text-automotive-600">Nenhuma manutenção registrada.</p>
    @endforelse
</div>
@endsection
