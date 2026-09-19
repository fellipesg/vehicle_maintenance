@extends('layouts.admin')

@section('title', 'Veículo — Admin')
@section('page_heading', $vehicle->brand.' '.$vehicle->model)

@section('content')
    <a href="{{ route('admin.vehicles.index') }}" class="text-sm text-wrench-600 hover:underline">← Todos os veículos</a>

    <div class="mt-4 flex flex-wrap gap-6">
        <x-vehicle-cover :vehicle="$vehicle" class="h-40 w-64 rounded-lg object-cover shadow" />
        <div>
            <h2 class="text-2xl font-bold">{{ $vehicle->brand }} {{ $vehicle->model }}</h2>
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

    <div class="mb-4 mt-8 flex flex-wrap items-end justify-between gap-4">
        <h3 class="text-xl font-semibold">Manutenções</h3>
        @if($showMaintenanceFilter)
            <div class="flex flex-wrap gap-2 text-sm">
                <a href="{{ route('admin.vehicles.show', $vehicle) }}" class="btn-secondary {{ ($verified ?? null) === null ? '!bg-wrench-100' : '' }}">Todas</a>
                <a href="{{ route('admin.vehicles.show', [$vehicle, 'verified' => '1']) }}" class="btn-secondary {{ ($verified ?? null) === '1' ? '!bg-wrench-100' : '' }}">Selo da oficina</a>
                <a href="{{ route('admin.vehicles.show', [$vehicle, 'verified' => '0']) }}" class="btn-secondary {{ ($verified ?? null) === '0' ? '!bg-wrench-100' : '' }}">Declaradas</a>
            </div>
        @endif
    </div>

    <x-provenance-legend class="mb-4" />

    @forelse($vehicle->maintenances as $maintenance)
        <div class="mb-3">
            <x-provenance-card :maintenance="$maintenance" />
            @if($maintenance->description)
                <p class="mt-2 text-sm text-automotive-600">{{ Str::limit($maintenance->description, 300) }}</p>
            @endif
        </div>
    @empty
        <p class="text-automotive-600">Nenhuma manutenção registrada.</p>
    @endforelse
@endsection
