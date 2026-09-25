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

    @if($vehicle->activeConsignment)
        <div class="card mb-6 border-l-4 border-amber-400">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <span class="badge badge-orange">Consignação</span>
                    <p class="mt-2 text-sm text-automotive-700">
                        Proprietário: <strong>{{ $vehicle->activeConsignment->owner_name }}</strong>
                    </p>
                    @if($vehicle->activeConsignment->isDisputed())
                        <p class="mt-2 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-900">
                            O proprietário contestou esta consignação. Novos registros de manutenção estão
                            bloqueados até que nossa equipe analise o caso.
                        </p>
                    @endif
                    @unless($seesFullHistory)
                        <p class="mt-1 text-sm text-automotive-600">
                            Você vê apenas as manutenções registradas pela sua garagem. O histórico anterior
                            @if($vehicle->activeConsignment->isHistoryReviewPending())
                                está aguardando a liberação do proprietário ou a análise da procuração.
                            @else
                                pertence ao proprietário e depende da liberação dele.
                            @endif
                        </p>
                        @if(! $vehicle->activeConsignment->isHistoryReviewPending() && ! $vehicle->activeConsignment->isDisputed())
                            <form method="POST" action="{{ route('garage.vehicles.consignment.request-history', $vehicle) }}" class="mt-2">
                                @csrf
                                <button type="submit" class="btn-secondary !py-1.5 !text-xs">
                                    Pedir liberação do histórico ao proprietário
                                </button>
                            </form>
                        @endif
                    @endunless
                </div>
                <form
                    method="POST"
                    action="{{ route('garage.vehicles.consignment.end', $vehicle) }}"
                    class="flex flex-wrap items-center gap-2"
                    onsubmit="return confirm('Encerrar a consignação deste veículo? Você perde o acesso, mas as manutenções registradas permanecem no histórico.');"
                >
                    @csrf
                    <select name="end_reason" class="form-input !py-1.5 !text-sm">
                        <option value="sold">Veículo vendido</option>
                        <option value="owner_withdrew">Devolvido ao proprietário</option>
                    </select>
                    <button type="submit" class="btn-secondary !py-1.5 !text-sm">Encerrar consignação</button>
                </form>
            </div>
            @error('consignment')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
            @error('end_reason')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    @endif

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
