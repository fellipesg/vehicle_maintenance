@extends('layouts.app')

@section('title', $vehicle->brand . ' ' . $vehicle->model)

@section('content')
<div class="mx-auto max-w-7xl px-4 py-8">
    <span class="badge badge-green mb-2">🏪 Garagem</span>
    <x-vehicle-cover :vehicle="$vehicle" variant="hero" class="mb-6" />
    <div class="mb-4 flex flex-wrap items-start justify-between gap-4">
        <div class="space-y-3">
            <h1 class="text-3xl font-bold">{{ $vehicle->brand }} {{ $vehicle->model }}</h1>
            <p class="text-automotive-600">{{ $vehicle->year }} · {{ $vehicle->color ?? '—' }}</p>
            <x-vehicle-identity :vehicle="$vehicle" size="hero" :edit-route="null" />
        </div>
        <a href="{{ route('garage.maintenances.create') }}?vehicle_id={{ $vehicle->id }}" class="btn-primary">+ Registrar revisão pré-venda</a>
    </div>

    <x-provenance-strip
        :vehicle="$vehicle"
        maintenance-path-prefix="#"
        :filter-base-url="route('garage.vehicles.show', $vehicle)"
        class="mb-6"
    />

    @if ($vehicle->relationLoaded('plates') && $vehicle->plates->isNotEmpty())
        <details class="card mb-6">
            <summary class="cursor-pointer font-semibold text-automotive-900">Histórico de placas</summary>
            <table class="mt-4 w-full text-sm">
                <thead>
                    <tr class="text-left text-automotive-500">
                        <th class="pb-2">Placa</th>
                        <th class="pb-2">De</th>
                        <th class="pb-2">Até</th>
                        <th class="pb-2">Origem</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($vehicle->plates as $plateRow)
                        <tr class="border-t border-automotive-100">
                            <td class="py-2 font-mono">{{ $plateRow->plate }}</td>
                            <td class="py-2">{{ $plateRow->started_at?->format('d/m/Y') ?? '—' }}</td>
                            <td class="py-2">{{ $plateRow->ended_at?->format('d/m/Y') ?? 'Vigente' }}</td>
                            <td class="py-2">{{ \App\Models\VehiclePlate::sourceLabel($plateRow->source) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </details>
    @endif

    <x-provenance-legend class="mb-4" />

    <h2 class="mb-4 text-xl font-semibold">Revisões documentadas</h2>
    @forelse($vehicle->maintenances->sortByDesc('maintenance_date') as $maintenance)
        <x-provenance-card :maintenance="$maintenance" />
    @empty
        <div class="card text-center text-automotive-500">Nenhuma revisão documentada.</div>
    @endforelse
</div>
@endsection
