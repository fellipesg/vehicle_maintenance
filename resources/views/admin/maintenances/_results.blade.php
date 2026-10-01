{{--
    Tabela de manutenções do admin, também devolvida sozinha na troca de filtro por AJAX. O resumo
    (data-status-text) é o que o JS anuncia na região sr-only role="status" da página; a tabela não
    é região viva.
--}}
@php
    $filters ??= ['q' => '', 'from' => null, 'to' => null, 'direction' => 'desc'];
    $contextFilters ??= [];
    $maintenanceTotal = $maintenances->total();
    $maintenanceTotalLabel = number_format($maintenanceTotal, 0, ',', '.');
    $maintenanceNoun = $maintenanceTotal === 1 ? 'manutenção' : 'manutenções';
    $maintenanceFilterLabel = match ($verified ?? null) {
        '1' => 'Selo da oficina',
        '0' => 'Declaradas',
        default => null,
    };
    $maintenancePeriodLabel = match (true) {
        $filters['from'] !== null && $filters['to'] !== null => 'de '.$filters['from']->format('d/m/Y').' a '.$filters['to']->format('d/m/Y'),
        $filters['from'] !== null => 'desde '.$filters['from']->format('d/m/Y'),
        $filters['to'] !== null => 'até '.$filters['to']->format('d/m/Y'),
        default => null,
    };

    $maintenanceStatusText = $maintenanceTotal === 0
        ? 'Nenhuma manutenção encontrada'
        : $maintenanceTotalLabel.' '.$maintenanceNoun.' '.($maintenanceTotal === 1 ? 'encontrada' : 'encontradas');

    if ($maintenanceFilterLabel !== null) {
        $maintenanceStatusText .= ' · filtro: '.$maintenanceFilterLabel;
    }

    if ($filters['q'] !== '') {
        $maintenanceStatusText .= ' · busca: “'.$filters['q'].'”';
    }

    if ($maintenancePeriodLabel !== null) {
        $maintenanceStatusText .= ' · '.$maintenancePeriodLabel;
    }

    if ($maintenances->lastPage() > 1) {
        $maintenanceStatusText .= ' · página '.$maintenances->currentPage().' de '.$maintenances->lastPage();
    }

    $maintenanceIsFiltered = $maintenanceFilterLabel !== null || $filters['q'] !== '' || $maintenancePeriodLabel !== null || $contextFilters !== [];
    $maintenanceEmptyTitle = match (true) {
        $filters['q'] !== '' => 'Nenhuma manutenção para “'.$filters['q'].'”',
        ($verified ?? null) === '1' => 'Nenhuma manutenção com Selo da oficina',
        ($verified ?? null) === '0' => 'Nenhuma manutenção declarada',
        $maintenanceIsFiltered => 'Nenhuma manutenção neste filtro',
        default => 'Nenhuma manutenção registrada',
    };
@endphp

<p class="mb-3 text-sm text-muted-foreground" tabindex="-1"
   data-admin-maintenances-summary
   data-status-text="{{ $maintenanceStatusText }}">
    @if($maintenanceTotal === 0)
        Nenhuma manutenção encontrada.
    @elseif($maintenances->isEmpty())
        Nenhuma manutenção nesta página ({{ $maintenanceTotalLabel }} no total).
    @else
        Mostrando {{ $maintenances->firstItem() }} a {{ $maintenances->lastItem() }} de {{ $maintenanceTotalLabel }} {{ $maintenanceNoun }}.
    @endif
</p>

<x-ui.table caption="Manutenções da plataforma" stack sort="data" :direction="$filters['direction']">
    <x-slot:head>
        <tr>
            <th data-sort="data" data-sort-default="desc">Data</th>
            <th class="min-w-44">Serviço</th>
            <th class="min-w-40">Veículo</th>
            <th class="min-w-64">Procedência</th>
            <th class="min-w-44">Oficina</th>
            <th class="min-w-40">Registrada por</th>
        </tr>
    </x-slot:head>

    @foreach($maintenances as $maintenance)
        @php
            $provClass = $maintenance->isVerified() ? 'prov-verified' : 'prov-declared';
            $rowWorkshop = $maintenance->workshop;
            $rowWorkshopName = $rowWorkshop?->name ?? $maintenance->workshop_name;
        @endphp
        <tr class="{{ $provClass }}" data-maintenance-row="{{ $maintenance->id }}" data-verified="{{ $maintenance->isVerified() ? '1' : '0' }}">
            <td class="whitespace-nowrap">
                <time datetime="{{ $maintenance->maintenance_date->format('Y-m-d') }}">{{ $maintenance->maintenance_date->format('d/m/Y') }}</time>
            </td>
            <th scope="row" class="font-medium">
                {{ $maintenance->maintenance_type }}
                @if($maintenance->kilometers !== null)
                    <span class="block text-xs font-normal text-muted-foreground tabular-nums">{{ number_format((int) $maintenance->kilometers, 0, ',', '.') }} km</span>
                @endif
            </th>
            <td>
                @if($maintenance->vehicle)
                    <span class="inline-flex flex-col items-end md:items-start">
                        <x-ui.link :href="route('admin.vehicles.show', $maintenance->vehicle)">{{ $maintenance->vehicle->brand }} {{ $maintenance->vehicle->model }}</x-ui.link>
                        @if($maintenance->vehicle->license_plate)
                            <span class="font-mono text-xs tracking-wider text-muted-foreground">{{ $maintenance->vehicle->license_plate }}</span>
                        @endif
                    </span>
                @else
                    <span class="text-muted-foreground">—</span>
                @endif
            </td>
            <td>
                <div class="flex items-start gap-2 {{ $provClass }}">
                    <x-provenance-marker :maintenance="$maintenance" size="sm" />
                    <div class="min-w-0 text-left">
                        <p class="font-medium text-foreground">{{ $maintenance->provenance_card_label }}</p>
                        <p class="text-xs text-muted-foreground">{{ $maintenance->provenance_meta }}</p>
                    </div>
                </div>
            </td>
            <td>
                @if($rowWorkshop)
                    <x-ui.link :href="route('admin.workshops.index', ['q' => $rowWorkshop->name])">{{ $rowWorkshop->name }}</x-ui.link>
                @else
                    {{ $rowWorkshopName ?? '—' }}
                @endif
            </td>
            <td>
                @if($maintenance->user)
                    <x-ui.link :href="route('admin.users.show', $maintenance->user)">{{ $maintenance->user->name }}</x-ui.link>
                @else
                    <span class="text-muted-foreground">—</span>
                @endif
            </td>
        </tr>
    @endforeach

    <x-slot:empty>
        <x-ui.empty-state
            :icon="$maintenanceIsFiltered ? 'funnel' : 'wrench-screwdriver'"
            :title="$maintenanceEmptyTitle"
            :description="$maintenanceIsFiltered ? 'Mude a busca ou o período, ou limpe os filtros para ver todas.' : 'As manutenções aparecem aqui quando proprietários, lojistas ou oficinas registram um serviço.'"
            variant="plain"
            size="sm"
            heading-level="p"
        >
            @if($maintenanceIsFiltered)
                <x-slot:actions>
                    <x-ui.button variant="secondary" :href="route('admin.maintenances.index')">Limpar filtros</x-ui.button>
                </x-slot:actions>
            @endif
        </x-ui.empty-state>
    </x-slot:empty>
</x-ui.table>

<div class="mt-4" data-admin-maintenances-pagination>
    {{ $maintenances->links() }}
</div>
