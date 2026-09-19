@extends('layouts.app')

@section('title', 'Manutenções — Admin')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-6">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-automotive-900">Todas as manutenções</h1>
        </div>
        <div class="flex gap-2 text-sm">
            <a href="{{ route('admin.maintenances.index') }}" class="btn-secondary {{ $verified === null ? '!bg-wrench-100' : '' }}">Todas</a>
            <a href="{{ route('admin.maintenances.index', ['verified' => '1']) }}" class="btn-secondary {{ $verified === '1' ? '!bg-wrench-100' : '' }}">Verificadas</a>
            <a href="{{ route('admin.maintenances.index', ['verified' => '0']) }}" class="btn-secondary {{ $verified === '0' ? '!bg-wrench-100' : '' }}">Não verificadas</a>
        </div>
    </div>

    <div class="card overflow-hidden !p-0">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-automotive-200 bg-automotive-50">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Data</th>
                        <th class="px-4 py-3 font-semibold">Veículo</th>
                        <th class="px-4 py-3 font-semibold">Selo / origem</th>
                        <th class="px-4 py-3 font-semibold">Oficina</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($maintenances as $maintenance)
                        <tr class="border-b border-automotive-100">
                            <td class="px-4 py-3">{{ $maintenance->maintenance_date->format('d/m/Y') }}</td>
                            <td class="px-4 py-3">
                                @if($maintenance->vehicle)
                                    <a href="{{ route('admin.vehicles.show', $maintenance->vehicle) }}" class="text-wrench-600 hover:underline">
                                        {{ $maintenance->vehicle->brand }} {{ $maintenance->vehicle->model }}
                                    </a>
                                    <span class="text-automotive-500">· {{ $maintenance->vehicle->license_plate }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $maintenance->provenance_card_label }}</td>
                            <td class="px-4 py-3">{{ $maintenance->workshop?->name ?? $maintenance->workshop_name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-10 text-center text-automotive-600">Nenhuma manutenção.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $maintenances->links() }}</div>
</div>
@endsection
