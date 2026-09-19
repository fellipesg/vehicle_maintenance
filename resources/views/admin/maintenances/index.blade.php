@extends('layouts.admin')

@section('title', 'Manutenções — Admin')
@section('page_heading', 'Todas as manutenções')

@section('content')
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <p class="text-sm text-automotive-600">Histórico de manutenções em toda a plataforma.</p>
        <div class="flex flex-wrap gap-2 text-sm">
            <a href="{{ route('admin.maintenances.index') }}" class="btn-secondary {{ $verified === null ? '!bg-wrench-100' : '' }}">Todas</a>
            <a href="{{ route('admin.maintenances.index', ['verified' => '1']) }}" class="btn-secondary {{ $verified === '1' ? '!bg-wrench-100' : '' }}">Selo da oficina</a>
            <a href="{{ route('admin.maintenances.index', ['verified' => '0']) }}" class="btn-secondary {{ $verified === '0' ? '!bg-wrench-100' : '' }}">Declaradas</a>
        </div>
    </div>

    <x-provenance-legend class="mb-4" />

    <div class="card overflow-hidden !p-0">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-automotive-200 bg-automotive-50">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Data</th>
                        <th class="px-4 py-3 font-semibold">Veículo</th>
                        <th class="px-4 py-3 font-semibold">Procedência</th>
                        <th class="px-4 py-3 font-semibold">Oficina</th>
                        <th class="px-4 py-3 font-semibold">Tipo</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($maintenances as $maintenance)
                        @php
                            $provClass = $maintenance->isVerified() ? 'prov-verified' : 'prov-declared';
                        @endphp
                        <tr class="border-b border-automotive-100 {{ $provClass }}">
                            <td class="px-4 py-3 whitespace-nowrap">{{ $maintenance->maintenance_date->format('d/m/Y') }}</td>
                            <td class="px-4 py-3">
                                @if($maintenance->vehicle)
                                    <a href="{{ route('admin.vehicles.show', $maintenance->vehicle) }}" class="font-medium text-wrench-600 hover:underline">
                                        {{ $maintenance->vehicle->brand }} {{ $maintenance->vehicle->model }}
                                    </a>
                                    <span class="text-automotive-500">· {{ $maintenance->vehicle->license_plate }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-start gap-2 {{ $provClass }}">
                                    <x-provenance-marker :maintenance="$maintenance" size="sm" />
                                    <div class="min-w-0">
                                        <p class="font-medium text-automotive-900">{{ $maintenance->provenance_card_label }}</p>
                                        <p class="text-xs text-automotive-500">{{ $maintenance->provenance_meta }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">{{ $maintenance->workshop?->name ?? $maintenance->workshop_name ?? '—' }}</td>
                            <td class="px-4 py-3 text-automotive-600">{{ $maintenance->maintenance_type }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-10 text-center text-automotive-600">Nenhuma manutenção.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $maintenances->links() }}</div>
@endsection
