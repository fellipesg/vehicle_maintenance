@extends('layouts.app')

@section('title', 'Buscar Veículo')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-8">
    <div class="mb-8 text-center">
        <h1 class="text-3xl font-bold text-automotive-900">🔍 Buscar Histórico de Veículo</h1>
        <p class="mt-2 text-automotive-600">Consulte o histórico completo de manutenções por chassi, placa ou RENAVAM</p>
    </div>

    <div class="mx-auto max-w-xl">
        <form method="GET" action="{{ route('vehicle.search') }}" class="card flex gap-3">
            <input type="text" name="identifier" value="{{ $identifier ?? '' }}"
                   placeholder="Chassi, placa ou RENAVAM"
                   class="form-input flex-1" required>
            <button type="submit" class="btn-primary">Buscar</button>
        </form>
    </div>

    @if(isset($identifier) && $identifier)
        @if($vehicle)
            <div class="mt-8">
                @if(($matchedBy ?? null) === 'previous_plate' && ($previousPlateEndedAt ?? null))
                    <div class="mb-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        A placa {{ strtoupper($identifier) }} pertenceu a este veículo até {{ $previousPlateEndedAt->format('d/m/Y') }}.
                        Placa atual: {{ $vehicle->license_plate }}.
                    </div>
                @endif
                <div class="card mb-6 !p-0 overflow-hidden">
                    <x-vehicle-cover :vehicle="$vehicle" variant="card" class="aspect-[21/9] w-full max-h-72" />
                    <div class="flex flex-wrap items-start justify-between gap-4 p-6">
                        <div class="space-y-3">
                            <div>
                                <h2 class="text-2xl font-bold">{{ $vehicle->brand }} {{ $vehicle->model }}</h2>
                                <p class="text-automotive-600">{{ $vehicle->year }} · {{ $vehicle->color ?? 'Cor não informada' }}</p>
                            </div>
                            <x-vehicle-identity :vehicle="$vehicle" size="hero" :edit-route="null" />
                            <p class="text-sm text-automotive-500">RENAVAM: {{ $vehicle->renavam }}</p>
                        </div>
                    </div>
                </div>

                <x-provenance-strip
                    :vehicle="$vehicle"
                    maintenance-path-prefix="#"
                    :filter-base-url="route('vehicle.search', ['identifier' => $identifier])"
                    class="mb-6"
                />

                @if ($vehicle->relationLoaded('plates') && $vehicle->plates->count() > 1)
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

                <h3 class="mb-4 text-xl font-semibold">Histórico de manutenções ({{ $vehicle->maintenances->count() }})</h3>

                @php
                    $verifiedFilter = request()->query('verified');
                    $filtered = $vehicle->maintenances;
                    if ($verifiedFilter === '1') {
                        $filtered = $filtered->filter(fn ($m) => $m->isVerified());
                    } elseif ($verifiedFilter === '0') {
                        $filtered = $filtered->filter(fn ($m) => ! $m->isVerified());
                    }
                @endphp

                @forelse($filtered as $maintenance)
                    <x-provenance-card :maintenance="$maintenance" class="mb-4" />
                @empty
                    <div class="card text-center text-automotive-500">Nenhuma manutenção registrada para este veículo.</div>
                @endforelse

                <x-provenance-legend class="mt-8" />
            </div>
        @else
            <div class="mx-auto mt-8 max-w-xl rounded-lg border border-yellow-200 bg-yellow-50 p-4 text-center text-yellow-800">
                Nenhum veículo encontrado para &quot;{{ $identifier }}&quot;.
            </div>
        @endif
    @endif
</div>
@endsection
