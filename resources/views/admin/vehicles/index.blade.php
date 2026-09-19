@extends('layouts.app')

@section('title', 'Veículos — Admin')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-6">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-automotive-900">Todos os veículos</h1>
            <p class="mt-1 text-sm text-automotive-600">Frota completa da plataforma</p>
        </div>
        <form method="GET" action="{{ route('admin.vehicles.index') }}" class="flex gap-2">
            <input
                type="search"
                name="search"
                value="{{ $search }}"
                placeholder="Chassi, placa, RENAVAM…"
                class="input-field min-w-[220px]"
            >
            <button type="submit" class="btn-secondary">Buscar</button>
        </form>
    </div>

    <div class="card overflow-hidden !p-0">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-automotive-200 bg-automotive-50">
                    <tr>
                        <th class="px-4 py-3 font-semibold text-automotive-800">Capa</th>
                        <th class="px-4 py-3 font-semibold text-automotive-800">Veículo</th>
                        <th class="px-4 py-3 font-semibold text-automotive-800">Chassi</th>
                        <th class="px-4 py-3 font-semibold text-automotive-800">Placa</th>
                        <th class="px-4 py-3 font-semibold text-automotive-800">Dono atual</th>
                        <th class="px-4 py-3 font-semibold text-automotive-800">Última oficina</th>
                        <th class="px-4 py-3 font-semibold text-automotive-800">Manut.</th>
                        <th class="px-4 py-3 text-right font-semibold text-automotive-800"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($vehicles as $vehicle)
                        @php
                            $owner = $vehicle->owners->first();
                            $latest = $vehicle->maintenances->first();
                            $workshopLabel = $latest?->workshop?->name ?? $latest?->workshop_name;
                        @endphp
                        <tr class="border-b border-automotive-100 last:border-0">
                            <td class="px-4 py-3">
                                <x-vehicle-cover :vehicle="$vehicle" class="h-12 w-20 rounded object-cover" />
                            </td>
                            <td class="px-4 py-3 font-medium text-automotive-900">
                                {{ $vehicle->brand }} {{ $vehicle->model }}
                                @if($vehicle->year)
                                    <span class="text-automotive-500">· {{ $vehicle->year }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 font-mono text-xs text-automotive-700">{{ $vehicle->chassis ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $vehicle->license_plate ?? '—' }}</td>
                            <td class="px-4 py-3">
                                @if($owner)
                                    <a href="{{ route('admin.users.show', $owner) }}" class="text-wrench-600 hover:underline">{{ $owner->name }}</a>
                                @else
                                    <span class="text-automotive-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-automotive-600">{{ $workshopLabel ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $vehicle->maintenances_count }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('admin.vehicles.show', $vehicle) }}" class="text-wrench-600 hover:underline">Ver</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-10 text-center text-automotive-600">Nenhum veículo encontrado.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $vehicles->links() }}
    </div>
</div>
@endsection
