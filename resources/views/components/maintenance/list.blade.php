{{--
    Lista de manutenções de vários veículos (Manutenções do proprietário, do lojista, OS da oficina),
    com toolbar de filtros na query string, estados vazios e paginação.

    Cada linha mostra: serviço, data (dd/mm/aaaa), veículo (modelo + placa em mono), km
    (tabular-nums), oficina e procedência (Selo da oficina / Declarada), com o contrato de
    .ai/rules/theme.md.

    Props:
    - maintenances (obrigatório): Collection ou paginator de App\Models\Maintenance. Carregue
      vehicle, workshop, verifiedWorkshop e user, e withCount('invoices', 'photos') para a evidência.
    - layout: list (o padrão; cards de procedência, bons no celular) | table (x-ui.table empilhada
      abaixo de md).
    - caption: nome da lista ou da tabela para leitor de tela (padrão "Manutenções").
    - portal: App\Enums\Portal (ou 'user', 'garage', 'workshop', 'admin'), para os links padrão.
    - maintenance-url / vehicle-url: Closure(Model): ?string, padrão com "{id}", false (sem link) ou
      null (o padrão do portal, App\Support\Maintenance\MaintenanceLinks).
    - show-vehicle: mostra o veículo em cada linha (padrão: sim).
    - group-by-month: agrupa por mês do serviço, com o mês como título ("Março de 2026"). O título
      fica preso logo abaixo da navbar de layouts.app (top-16, a altura h-16 dela) ao rolar.
    - heading-level: nível do título de cada card no layout list (h2 | h3, o padrão | h4); com
      group-by-month, o mês usa esse nível e os cards o seguinte.

    Toolbar (tudo em GET, na URL atual, sem a página):
    - show-filters: mostra a toolbar (padrão: sim).
    - filter-options: opções próprias para o x-ui.segmented (modo links), no formato de lá. Sem elas,
      o padrão é Todas / Selo da oficina / Declaradas em ?{filter-param}= ('', '1', '0').
    - filter-param (padrão "verified"), filter-value (padrão: o valor da query), filter-label.
    - filter-default: o valor que quer dizer "sem filtro" nas opções próprias (padrão '', ex.:
      'todas' no filtro ?filtro= do lojista), para o estado vazio saber se há filtro ativo.
    - counts: contagens das opções padrão, ['' => 10, '1' => 4, '0' => 6].
    - vehicles: veículos do filtro "Veículo" (Collection de Vehicle ou [id => rótulo]); vazio esconde.
    - vehicle-param (padrão "veiculo"), vehicle-value (padrão: o valor da query).
    - clear-url: destino de "Limpar filtros" (padrão: a URL atual sem os filtros).

    Estados vazios:
    - empty-title / empty-description / empty-icon, e o slot empty-actions (CTA da tela, ex.:
      "Registrar manutenção").
    - com filtro ativo: "Nenhuma manutenção com Selo da oficina", "Nenhuma manutenção declarada" ou
      "Nenhuma manutenção neste filtro", com "Limpar filtros".

    Slot toolbar: controles extras à direita da toolbar.

    O controller aplica os filtros com App\Support\Maintenance\MaintenanceListFilters.

    Ex.: <x-maintenance.list :maintenances="$maintenances" :portal="\App\Enums\Portal::Owner"
             :vehicles="$vehicles" :counts="$counts" group-by-month>
             <x-slot:empty-actions><x-ui.button icon="plus" :href="route('user.maintenances.create')">Registrar manutenção</x-ui.button></x-slot:empty-actions>
         </x-maintenance.list>
--}}
@props([
    'maintenances',
    'layout' => 'list',
    'caption' => 'Manutenções',
    'portal' => null,
    'maintenanceUrl' => null,
    'vehicleUrl' => null,
    'showVehicle' => true,
    'groupByMonth' => false,
    'headingLevel' => 'h3',
    'showFilters' => true,
    'filterOptions' => null,
    'filterParam' => 'verified',
    'filterValue' => null,
    'filterLabel' => 'Filtrar por procedência',
    'filterDefault' => '',
    'counts' => [],
    'vehicles' => null,
    'vehicleParam' => 'veiculo',
    'vehicleValue' => null,
    'clearUrl' => null,
    'emptyTitle' => 'Nenhuma manutenção registrada',
    'emptyDescription' => null,
    'emptyIcon' => 'wrench-screwdriver',
])

@php
    use App\Support\Maintenance\MaintenanceLinks;
    use App\Support\Vehicle\VehicleMaintenanceHistory;
    use Illuminate\Contracts\Pagination\Paginator;

    $listLayout = \App\Support\UiProps::oneOf('x-maintenance.list', 'layout', $layout, ['list', 'table'], 'list');
    $listHeadingTag = \App\Support\UiProps::oneOf('x-maintenance.list', 'heading-level', $headingLevel, ['h2', 'h3', 'h4'], 'h3');
    $listCardHeadingTag = $groupByMonth ? match ($listHeadingTag) { 'h2' => 'h3', default => 'h4' } : $listHeadingTag;
    $listPortal = MaintenanceLinks::portal($portal);
    $listIsPaginated = $maintenances instanceof Paginator;
    // Relações e contagens que a lista usa, carregadas de uma vez (sem N+1 e sem acesso lazy, que o
    // preventLazyLoading barra fora de produção). O que o controller já carregou não é refeito.
    $listItems = new \Illuminate\Database\Eloquent\Collection(collect($listIsPaginated ? $maintenances->items() : $maintenances)->all());
    $listItems->loadMissing(['vehicle', 'workshop', 'verifiedWorkshop', 'user']);
    $listFirstItem = $listItems->first();
    if ($listFirstItem !== null && ! array_key_exists('invoices_count', $listFirstItem->getAttributes()) && ! $listFirstItem->relationLoaded('invoices')) {
        $listItems->loadCount('invoices');
    }
    if ($listFirstItem !== null && ! array_key_exists('photos_count', $listFirstItem->getAttributes()) && ! $listFirstItem->relationLoaded('photos')) {
        $listItems->loadCount('photos');
    }
    $listMaintenanceUrl = MaintenanceLinks::resolver($maintenanceUrl, fn (\App\Models\Maintenance $maintenance): ?string => MaintenanceLinks::detailUrl($maintenance, $listPortal));
    $listVehicleUrl = MaintenanceLinks::resolver($vehicleUrl, fn (?\App\Models\Vehicle $vehicle): ?string => $vehicle ? MaintenanceLinks::vehicleUrl($vehicle, $listPortal) : null);
    $listVehicleOf = fn (\App\Models\Maintenance $maintenance): ?\App\Models\Vehicle => $maintenance->relationLoaded('vehicle') ? $maintenance->vehicle : $maintenance->vehicle()->first();

    $listCurrentUrl = request()->url();
    $listQuery = collect(request()->query())->except([$filterParam, $vehicleParam, 'page'])->filter(fn (mixed $value): bool => is_scalar($value))->all();
    $listUsesDefaultFilters = $filterOptions === null;
    $listFilterValue = $listUsesDefaultFilters
        ? VehicleMaintenanceHistory::normalizeFilter($filterValue ?? request()->query($filterParam))
        : (string) ($filterValue ?? (is_scalar(request()->query($filterParam)) ? request()->query($filterParam) : $filterDefault));
    $listFilterQueryFor = function (string $value) use ($listQuery, $filterParam, $vehicleParam): array {
        $query = $listQuery;
        $currentVehicle = request()->query($vehicleParam);

        if (is_scalar($currentVehicle) && (string) $currentVehicle !== '') {
            $query[$vehicleParam] = (string) $currentVehicle;
        }

        if ($value !== '') {
            $query[$filterParam] = $value;
        }

        return $query;
    };
    $listFilterUrl = fn (string $value): string => $listCurrentUrl.(($query = $listFilterQueryFor($value)) !== [] ? '?'.http_build_query($query) : '');
    $listSegmentedOptions = $listUsesDefaultFilters
        ? [
            ['value' => '', 'label' => 'Todas', 'href' => $listFilterUrl(''), 'count' => $counts[''] ?? $counts['all'] ?? null],
            ['value' => '1', 'label' => 'Selo da oficina', 'href' => $listFilterUrl('1'), 'count' => $counts['1'] ?? null],
            ['value' => '0', 'label' => 'Declaradas', 'href' => $listFilterUrl('0'), 'count' => $counts['0'] ?? null],
        ]
        : $filterOptions;

    $listVehicleOptions = collect($vehicles ?? [])->mapWithKeys(function (mixed $vehicleOption, mixed $key): array {
        if ($vehicleOption instanceof \App\Models\Vehicle) {
            $optionLabel = trim($vehicleOption->brand.' '.$vehicleOption->model);

            return [(string) $vehicleOption->id => filled($vehicleOption->license_plate) ? $optionLabel.' · '.$vehicleOption->license_plate : $optionLabel];
        }

        return [(string) $key => (string) $vehicleOption];
    })->all();
    $listVehicleValue = (string) ($vehicleValue ?? (is_scalar(request()->query($vehicleParam)) ? request()->query($vehicleParam) : ''));
    $listIsFiltered = $listFilterValue !== (string) $filterDefault || $listVehicleValue !== '';
    $listClearUrl = $clearUrl ?? ($listCurrentUrl.($listQuery !== [] ? '?'.http_build_query($listQuery) : ''));
    $listFilteredTitle = match (true) {
        $listUsesDefaultFilters && $listFilterValue !== '' => VehicleMaintenanceHistory::emptyFilterTitle($listFilterValue),
        $listFilterValue === (string) $filterDefault && $listVehicleValue !== '' => 'Nenhuma manutenção neste veículo',
        default => 'Nenhuma manutenção neste filtro',
    };
    $listGroups = $groupByMonth
        ? $listItems->groupBy(fn (\App\Models\Maintenance $maintenance): string => $maintenance->maintenance_date?->format('Y-m') ?? 'sem-data')
        : collect(['todas' => $listItems]);
    $listMonthLabel = fn (string $monthKey): string => $monthKey === 'sem-data'
        ? 'Sem data'
        : \Illuminate\Support\Str::ucfirst(\Carbon\Carbon::createFromFormat('Y-m-d', $monthKey.'-01')->locale('pt_BR')->translatedFormat('F \d\e Y'));
    $listId = 'lista-manutencoes-'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(6));
    $listHasToolbarSlot = isset($toolbar) && ! \App\Support\UiProps::isBlank($toolbar);
    $listHasEmptyActions = isset($emptyActions) && ! \App\Support\UiProps::isBlank($emptyActions);
@endphp

<div {{ $attributes->class(['space-y-4'])->merge(['data-slot' => 'maintenance-list']) }}>
    @if ($showFilters)
        <div class="flex flex-col gap-3 lg:flex-row lg:flex-wrap lg:items-end lg:justify-between" data-slot="maintenance-list-toolbar">
            @if (! empty($listSegmentedOptions))
                <x-ui.segmented :label="$filterLabel" :options="$listSegmentedOptions" :value="$listFilterValue" />
            @endif
            @if ($listVehicleOptions !== [] || $listHasToolbarSlot)
                <div class="flex flex-wrap items-end gap-2">
                    @if ($listVehicleOptions !== [])
                        <form method="get" action="{{ $listCurrentUrl }}" class="flex flex-wrap items-end gap-2" aria-label="Filtrar por veículo" data-submit-busy="off">
                            @foreach ($listQuery as $queryKey => $queryValue)
                                <input type="hidden" name="{{ $queryKey }}" value="{{ $queryValue }}">
                            @endforeach
                            @if ($listFilterValue !== '')
                                <input type="hidden" name="{{ $filterParam }}" value="{{ $listFilterValue }}">
                            @endif
                            <div class="min-w-56">
                                <label for="{{ $listId }}-veiculo" class="form-label">Veículo</label>
                                <x-ui.select :id="$listId.'-veiculo'" :name="$vehicleParam" :options="$listVehicleOptions" :value="$listVehicleValue" placeholder="Todos os veículos" />
                            </div>
                            <x-ui.button type="submit" variant="secondary">Filtrar</x-ui.button>
                        </form>
                    @endif
                    {{ $toolbar ?? '' }}
                </div>
            @endif
        </div>
    @endif

    @if ($listItems->isEmpty())
        @if ($listIsFiltered)
            <x-ui.empty-state icon="funnel" :title="$listFilteredTitle" description="Limpe os filtros para ver todas as manutenções." data-maintenance-list-empty="filtered">
                <x-slot:actions>
                    <x-ui.button variant="secondary" :href="$listClearUrl">Limpar filtros</x-ui.button>
                </x-slot:actions>
            </x-ui.empty-state>
        @else
            <x-ui.empty-state :icon="$emptyIcon" :title="$emptyTitle" :description="$emptyDescription" data-maintenance-list-empty="all">
                @if ($listHasEmptyActions)
                    <x-slot:actions>{{ $emptyActions }}</x-slot:actions>
                @endif
            </x-ui.empty-state>
        @endif
    @elseif ($listLayout === 'table')
        <x-ui.table :caption="$caption" stack>
            <x-slot:head>
                <tr>
                    <th>Data</th>
                    <th>Serviço</th>
                    @if ($showVehicle)
                        <th>Veículo</th>
                    @endif
                    <th class="text-right">Km</th>
                    <th>Oficina</th>
                    <th>Procedência</th>
                </tr>
            </x-slot:head>
            @foreach ($listItems as $maintenance)
                @php
                    $rowUrl = $listMaintenanceUrl($maintenance);
                    $rowVehicle = $showVehicle ? $listVehicleOf($maintenance) : null;
                    $rowVehicleUrl = $rowVehicle ? $listVehicleUrl($rowVehicle) : null;
                    $rowVerified = $maintenance->isVerified();
                @endphp
                <tr data-maintenance-row="{{ $maintenance->id }}" data-verified="{{ $rowVerified ? '1' : '0' }}">
                    <td class="whitespace-nowrap tabular-nums">
                        @if ($maintenance->maintenance_date)
                            <time datetime="{{ $maintenance->maintenance_date->format('Y-m-d') }}">{{ $maintenance->maintenance_date->format('d/m/Y') }}</time>
                        @else
                            —
                        @endif
                    </td>
                    <th scope="row" class="font-medium">
                        @if ($rowUrl)
                            <a href="{{ $rowUrl }}" class="link">{{ $maintenance->maintenance_type }}</a>
                        @else
                            {{ $maintenance->maintenance_type }}
                        @endif
                    </th>
                    @if ($showVehicle)
                        <td>
                            @if ($rowVehicle)
                                <span class="inline-flex flex-wrap items-center justify-end gap-x-2 md:justify-start">
                                    @if ($rowVehicleUrl)
                                        <a href="{{ $rowVehicleUrl }}" class="link">{{ trim($rowVehicle->brand.' '.$rowVehicle->model) }}</a>
                                    @else
                                        <span>{{ trim($rowVehicle->brand.' '.$rowVehicle->model) }}</span>
                                    @endif
                                    @if (filled($rowVehicle->license_plate))
                                        <span class="font-mono text-xs tracking-wider text-muted-foreground">{{ $rowVehicle->license_plate }}</span>
                                    @endif
                                </span>
                            @else
                                —
                            @endif
                        </td>
                    @endif
                    <td class="text-right whitespace-nowrap tabular-nums">{{ $maintenance->kilometers !== null ? number_format((int) $maintenance->kilometers, 0, ',', '.').' km' : '—' }}</td>
                    <td>{{ $maintenance->displayWorkshopName() ?: '—' }}</td>
                    <td><x-ui.badge :variant="$rowVerified ? 'seal' : 'declared'">{{ $rowVerified ? 'Selo da oficina' : $maintenance->provenance_label }}</x-ui.badge></td>
                </tr>
            @endforeach
        </x-ui.table>
    @else
        @foreach ($listGroups as $monthKey => $groupItems)
            @if ($groupByMonth)
                <section aria-labelledby="{{ $listId }}-{{ $monthKey }}" class="space-y-3">
                    <{{ $listHeadingTag }} id="{{ $listId }}-{{ $monthKey }}" class="sticky top-16 z-10 -mx-1 bg-background/95 px-1 py-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase backdrop-blur-sm">{{ $listMonthLabel($monthKey) }}</{{ $listHeadingTag }}>
            @endif
            <ol role="list" class="space-y-3" @unless ($groupByMonth) aria-label="{{ $caption }}" @endunless data-maintenance-list-items>
                @foreach ($groupItems as $maintenance)
                    @php
                        $itemUrl = $listMaintenanceUrl($maintenance);
                        $itemVehicle = $showVehicle ? $listVehicleOf($maintenance) : null;
                    @endphp
                    <x-provenance-card
                        :maintenance="$maintenance"
                        as="li"
                        :heading-level="$listCardHeadingTag"
                        :href="$itemUrl"
                        :show-vehicle="$showVehicle"
                        :vehicle-href="$itemVehicle ? $listVehicleUrl($itemVehicle) : null"
                        :anchor="false"
                    />
                @endforeach
            </ol>
            @if ($groupByMonth)
                </section>
            @endif
        @endforeach
    @endif

    @if ($listIsPaginated && method_exists($maintenances, 'hasPages') && $maintenances->hasPages())
        <div class="pt-2">{{ $maintenances->withQueryString()->links() }}</div>
    @endif
</div>
