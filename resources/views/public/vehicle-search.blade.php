@extends('layouts.app')

@section('title', 'Buscar Veículo')

@php
    $categories = [
        'mechanical' => 'Mecânica', 'electrical' => 'Elétrica', 'suspension' => 'Suspensão',
        'painting' => 'Pintura', 'finishing' => 'Acabamento', 'interior' => 'Interior', 'other' => 'Outros',
    ];
@endphp

@section('content')
<div class="mx-auto max-w-7xl px-4 py-8">
    <div class="mb-8 text-center">
        <h1 class="text-3xl font-bold text-automotive-900">🔍 Buscar Histórico de Veículo</h1>
        <p class="mt-2 text-automotive-600">Consulte o histórico completo de manutenções por placa ou RENAVAM</p>
    </div>

    <div class="mx-auto max-w-xl">
        <form method="GET" action="{{ route('vehicle.search') }}" class="card flex gap-3">
            <input type="text" name="identifier" value="{{ $identifier ?? '' }}"
                   placeholder="Digite a placa ou RENAVAM"
                   class="form-input flex-1" required>
            <button type="submit" class="btn-primary">Buscar</button>
        </form>
    </div>

    @if(isset($identifier) && $identifier)
        @if($vehicle)
            <div class="mt-8">
                <div class="card mb-6">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h2 class="text-2xl font-bold">{{ $vehicle->brand }} {{ $vehicle->model }}</h2>
                            <p class="text-automotive-600">{{ $vehicle->year }} · {{ $vehicle->color ?? 'Cor não informada' }}</p>
                        </div>
                        <div class="text-right">
                            <span class="badge badge-blue text-base">{{ $vehicle->license_plate }}</span>
                            <p class="mt-1 text-sm text-automotive-500">RENAVAM: {{ $vehicle->renavam }}</p>
                        </div>
                    </div>
                </div>

                <h3 class="mb-4 text-xl font-semibold">Histórico de Manutenções ({{ $vehicle->maintenances->count() }})</h3>

                @forelse($vehicle->maintenances->sortByDesc('maintenance_date') as $maintenance)
                    <div class="card mb-4">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <span class="badge badge-orange">{{ $categories[$maintenance->service_category] ?? $maintenance->service_category }}</span>
                                <h4 class="mt-2 font-semibold">{{ $maintenance->maintenance_type }}</h4>
                                <p class="text-sm text-automotive-600">{{ $maintenance->description }}</p>
                            </div>
                            <div class="text-right text-sm text-automotive-500">
                                <p>{{ $maintenance->maintenance_date->format('d/m/Y') }}</p>
                                @if($maintenance->kilometers)<p>{{ number_format($maintenance->kilometers, 0, ',', '.') }} km</p>@endif
                                @if($maintenance->workshop_name)<p>🔧 {{ $maintenance->workshop_name }}</p>@endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="card text-center text-automotive-500">Nenhuma manutenção registrada para este veículo.</div>
                @endforelse
            </div>
        @else
            <div class="mx-auto mt-8 max-w-xl rounded-lg border border-yellow-200 bg-yellow-50 p-4 text-center text-yellow-800">
                Veículo não encontrado para "{{ $identifier }}".
            </div>
        @endif
    @endif
</div>
@endsection
