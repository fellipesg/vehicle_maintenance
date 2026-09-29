{{--
    Manutenções do Lojista: o histórico dos veículos do estoque (Selo da oficina, declaradas por
    proprietários e as que a loja registrou) em <x-maintenance.list>, agrupado por mês, com o filtro
    Todas do estoque / Com Selo da oficina / Declaradas / Registradas por mim (?filtro=) e o filtro
    por veículo (?veiculo=). Cada card abre o detalhe (garage.maintenances.show) quando o veículo
    ainda está no estoque com o histórico liberado; registros de veículos que já saíram ficam sem link.
--}}
@extends('layouts.app')

@section('title', 'Manutenções')

@php
    $listQuery = collect(request()->query())->except(['page', 'filtro'])->filter(fn (mixed $value): bool => is_scalar($value) && (string) $value !== '')->all();
    $filterOptions = collect($filters)
        ->map(fn (array $option, string $key): array => [
            'value' => $key,
            'label' => $option['label'],
            'count' => $option['count'],
            'href' => route('garage.maintenances.index', $key === 'todas' ? $listQuery : [...$listQuery, 'filtro' => $key]),
            'attributes' => ['data-maintenance-filter' => $key],
        ])
        ->values()
        ->all();
    $isOwnHistory = fn (?int $vehicleId): bool => $vehicleId !== null && in_array($vehicleId, $historyVehicleIds, true);
    $maintenanceUrl = fn (\App\Models\Maintenance $maintenance): ?string => $isOwnHistory((int) $maintenance->vehicle_id)
        ? route('garage.maintenances.show', $maintenance)
        : null;
    $vehicleUrl = fn (?\App\Models\Vehicle $vehicle): ?string => $vehicle !== null && $isOwnHistory((int) $vehicle->id)
        ? route('garage.vehicles.show', $vehicle)
        : null;
@endphp

@section('content')
    <x-ui.container padded>
        <x-ui.page-header title="Manutenções" description="Histórico dos veículos do estoque: Selo da oficina, declaradas por proprietários e as revisões pré-venda que você registrou.">
            <x-slot:actions>
                <x-ui.button icon="plus" :href="route('garage.maintenances.create')">Registrar manutenção</x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        <x-maintenance.list
            :maintenances="$maintenances"
            :portal="\App\Enums\Portal::Dealer"
            caption="Manutenções do estoque"
            :maintenance-url="$maintenanceUrl"
            :vehicle-url="$vehicleUrl"
            :filter-options="$filterOptions"
            filter-param="filtro"
            filter-default="todas"
            :filter-value="$filter"
            filter-label="Filtrar manutenções"
            :vehicles="$vehicleOptions"
            group-by-month
            heading-level="h2"
            empty-title="Nenhuma manutenção nos veículos do estoque"
            empty-description="Registre a revisão pré-venda de um veículo do estoque. Os selos das oficinas da rede e o que os proprietários declararam também aparecem aqui."
            :clear-url="route('garage.maintenances.index')"
        >
            <x-slot:empty-actions>
                <x-ui.button icon="plus" :href="route('garage.maintenances.create')">Registrar manutenção</x-ui.button>
            </x-slot:empty-actions>
        </x-maintenance.list>

        <x-provenance-legend class="mt-6" />
    </x-ui.container>
@endsection
