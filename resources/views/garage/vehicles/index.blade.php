{{--
    Estoque do Lojista: busca (placa, modelo ou chassi), filtros de procedência (Com selo, Só
    declaradas, Sem histórico, Consignação), ordenação, alternância Cards | Tabela e paginação, tudo
    na query string (App\Support\Vehicle\DealerStock). O card inteiro leva à ficha; consignação sem
    procuração aprovada não abre a ficha (403) e mostra o status da procuração no lugar.
--}}
@extends('layouts.app')

@section('title', 'Estoque')

@php
    use App\Support\Vehicle\DealerStock;

    $garageUser = auth()->user();
    $stockQuery = collect(request()->query())->except(['page', 'filtro'])->filter(fn (mixed $value): bool => is_scalar($value) && (string) $value !== '')->all();
    $stockFilterUrl = fn (string $filter): string => route('garage.vehicles.index', $filter === DealerStock::FILTER_ALL ? $stockQuery : [...$stockQuery, 'filtro' => $filter]);
    $stockFilterOptions = collect(DealerStock::filterLabels())
        ->map(fn (string $label, string $filter): array => [
            'value' => $filter,
            'label' => $label,
            'href' => $stockFilterUrl($filter),
            'count' => $counts[$filter] ?? null,
            'attributes' => ['data-stock-filter' => $filter === DealerStock::FILTER_ALL ? 'todos' : $filter],
        ])
        ->values()
        ->all();
    $stockViewOptions = [
        ['value' => DealerStock::VIEW_CARDS, 'label' => 'Cards', 'icon' => 'squares-2x2', 'href' => request()->fullUrlWithQuery(['visao' => DealerStock::VIEW_CARDS])],
        ['value' => DealerStock::VIEW_TABLE, 'label' => 'Tabela', 'icon' => 'bars-3', 'href' => request()->fullUrlWithQuery(['visao' => DealerStock::VIEW_TABLE])],
    ];
    $stockSortUrl = fn (string $column, string $direction): string => request()->fullUrlWithQuery(['ordem' => $column.'_'.$direction, 'page' => null]);
    $stockVehicleLabel = fn (int $count): string => $count === 1 ? '1 veículo' : number_format($count, 0, ',', '.').' veículos';
    $stockCountText = $stock->isFiltered()
        ? $stockVehicleLabel($vehicles->total()).' de '.number_format($stockTotal, 0, ',', '.').' no estoque'
        : $stockVehicleLabel($stockTotal).' no estoque';
    $stockKm = fn (?int $kilometers): string => $kilometers === null ? '—' : number_format($kilometers, 0, ',', '.').' km';
    $stockLastMaintenance = fn ($vehicle): ?string => filled($vehicle->maintenances_max_maintenance_date)
        ? \Illuminate\Support\Carbon::parse($vehicle->maintenances_max_maintenance_date)->format('d/m/Y')
        : null;
    $stockDeclaredLabel = fn (int $count): string => $count === 1 ? '1 declarada' : $count.' declaradas';
@endphp

@section('content')
    <x-ui.container padded>
        <x-ui.page-header title="Estoque" description="Veículos da sua loja, com o histórico de procedência de cada um.">
            <x-slot:actions>
                <x-ui.button icon="plus" :href="route('garage.vehicles.create')">Adicionar ao estoque</x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        @if ($stockTotal === 0)
            <x-ui.empty-state
                icon="truck"
                title="Nenhum veículo no estoque"
                description="Adicione pelo CRLV-e: lemos os dados, conferimos se o veículo já está na RevisaLog e trazemos o histórico com a procedência de cada manutenção."
                heading-level="h2"
                data-stock-empty="all"
            >
                <x-slot:actions>
                    <x-ui.button icon="plus" :href="route('garage.vehicles.create')">Adicionar ao estoque</x-ui.button>
                </x-slot:actions>
            </x-ui.empty-state>
        @else
            <div class="mb-6 space-y-4" data-slot="stock-toolbar">
                <form method="get" action="{{ route('garage.vehicles.index') }}" role="search" aria-label="Buscar no estoque" class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_16rem_auto] sm:items-end" data-submit-busy="off">
                    @if ($stock->filter !== DealerStock::FILTER_ALL)
                        <input type="hidden" name="filtro" value="{{ $stock->filter }}">
                    @endif
                    <x-ui.field name="busca" label="Buscar no estoque">
                        <x-ui.input type="search" :value="$stock->search" leading-icon="magnifying-glass" placeholder="Placa, modelo ou chassi" autocomplete="off" maxlength="100" />
                    </x-ui.field>
                    <x-ui.field name="ordem" label="Ordenar por">
                        <x-ui.select :options="$stock->sortOptions()" :value="$stock->orderValue()" />
                    </x-ui.field>
                    <x-ui.button type="submit" variant="secondary" icon="magnifying-glass">Buscar</x-ui.button>
                </form>

                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <x-ui.segmented label="Filtrar o estoque" :options="$stockFilterOptions" :value="$stock->filter" />
                    <x-ui.segmented label="Visualização" :options="$stockViewOptions" :value="$stockView" equal class="self-start" data-stock-view="{{ $stockView }}" />
                </div>
            </div>

            <p class="mb-3 text-sm text-muted-foreground tabular-nums" data-stock-count>{{ $stockCountText }}</p>

            @if ($vehicles->isEmpty())
                <x-ui.empty-state
                    icon="funnel"
                    title="Nenhum veículo encontrado"
                    :description="$stock->search !== '' ? 'Nada no estoque combina com “'.$stock->search.'” neste filtro. Confira a placa ou o modelo, ou limpe os filtros.' : 'Nenhum veículo do estoque está neste filtro.'"
                    heading-level="h2"
                    data-stock-empty="filtered"
                >
                    <x-slot:actions>
                        <x-ui.button variant="secondary" :href="route('garage.vehicles.index')">Limpar filtros</x-ui.button>
                    </x-slot:actions>
                </x-ui.empty-state>
            @elseif ($stockView === DealerStock::VIEW_TABLE)
                <x-ui.table caption="Veículos do estoque" stack :sort="$stock->sort" :direction="$stock->direction" :sort-url="$stockSortUrl">
                    <x-slot:head>
                        <tr>
                            <th data-sort="veiculo">Veículo</th>
                            <th>Placa</th>
                            <th class="text-right" data-sort="ano" data-sort-default="desc">Ano</th>
                            <th class="text-right" data-sort="km">Km</th>
                            <th>Procedência</th>
                            <th data-sort="manutencao" data-sort-default="desc">Última manutenção</th>
                            <th data-sort="entrada" data-sort-default="desc">Entrada</th>
                        </tr>
                    </x-slot:head>
                    @foreach ($vehicles as $vehicle)
                        @php
                            $vehicleName = trim($vehicle->brand.' '.$vehicle->model);
                            $seesFullHistory = $garageUser->canViewStockVehicleHistory($vehicle);
                            $sealedCount = (int) $vehicle->verified_maintenances_count;
                            $declaredCount = max(0, (int) $vehicle->maintenances_count - $sealedCount);
                        @endphp
                        <tr data-stock-vehicle="{{ $vehicle->id }}">
                            <th scope="row" class="font-medium">
                                <div class="flex items-center gap-3">
                                    <x-vehicle-cover :vehicle="$vehicle" />
                                    <div class="min-w-0">
                                        <a href="{{ route('garage.vehicles.show', $vehicle) }}" class="link">{{ $vehicleName }}</a>
                                        @if (filled($vehicle->color))
                                            <span class="block text-xs font-normal text-muted-foreground">{{ $vehicle->color }}</span>
                                        @endif
                                        @if ($garageUser->holdsOnConsignment($vehicle))
                                            <div class="mt-1 font-normal">
                                                @include('garage.vehicles._consignment-status', ['consignment' => $garageUser->consignmentFor($vehicle), 'compact' => $garageUser->canViewStockVehicleHistory($vehicle)])
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </th>
                            <td class="whitespace-nowrap"><span class="font-mono tracking-wider">{{ $vehicle->license_plate ?: '—' }}</span></td>
                            <td class="text-right">{{ $vehicle->year ?: '—' }}</td>
                            <td class="text-right whitespace-nowrap">{{ $stockKm($vehicle->current_kilometers) }}</td>
                            <td>
                                @if (! $seesFullHistory)
                                    <span class="text-muted-foreground">Só o que você registrou</span>
                                @elseif ((int) $vehicle->maintenances_count === 0)
                                    <span class="text-muted-foreground">Sem manutenções</span>
                                @else
                                    <span class="whitespace-nowrap">{{ $sealedCount }} com selo · {{ $stockDeclaredLabel($declaredCount) }}</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap">{{ $stockLastMaintenance($vehicle) ?? '—' }}</td>
                            <td class="whitespace-nowrap">{{ $vehicle->pivot?->created_at?->format('d/m/Y') ?? '—' }}</td>
                        </tr>
                    @endforeach
                </x-ui.table>
            @else
                <ul role="list" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3" aria-label="Veículos do estoque">
                    @foreach ($vehicles as $vehicle)
                        @php
                            $onConsignment = $garageUser->holdsOnConsignment($vehicle);
                            $lastMaintenance = $stockLastMaintenance($vehicle);
                        @endphp
                        <x-vehicle.card
                            :vehicle="$vehicle"
                            :href="route('garage.vehicles.show', $vehicle)"
                            as="li"
                            heading-level="h2"
                            :add-cover-url="$onConsignment ? null : route('garage.vehicles.edit', $vehicle).'#capas'"
                            data-stock-vehicle="{{ $vehicle->id }}"
                        >
                            @if ($lastMaintenance !== null)
                                <p class="text-muted-foreground">Última manutenção em <time class="tabular-nums">{{ $lastMaintenance }}</time></p>
                            @endif
                            @if ($onConsignment)
                                <div class="mt-2">
                                    @include('garage.vehicles._consignment-status', ['consignment' => $garageUser->consignmentFor($vehicle), 'compact' => $garageUser->canViewStockVehicleHistory($vehicle)])
                                </div>
                            @endif
                        </x-vehicle.card>
                    @endforeach
                </ul>
            @endif

            @if ($vehicles->hasPages())
                <div class="mt-6">{{ $vehicles->links() }}</div>
            @endif
        @endif
    </x-ui.container>
@endsection
