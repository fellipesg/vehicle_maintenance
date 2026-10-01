@extends('layouts.app')

@section('title', 'Ordens de serviço')

@php
    use App\Enums\ServiceCategory;
    use App\Support\Maintenance\WorkshopMaintenanceFilters;

    $indexUrl = route('workshop.maintenances.index');
    // Query atual sem a procedência e sem a página: base dos links do controle de procedência.
    $currentQuery = collect(request()->query())
        ->except(['verified', 'page'])
        ->filter(fn ($value) => is_scalar($value) && (string) $value !== '')
        ->all();
    $provenanceUrl = fn (string $value): string => $indexUrl.(($query = array_filter($currentQuery + ['verified' => $value], fn ($item) => (string) $item !== '')) !== [] ? '?'.http_build_query($query) : '');
    $provenanceOptions = [
        ['value' => '', 'label' => 'Todas', 'href' => $provenanceUrl(''), 'count' => $counts[''] ?? null],
        ['value' => '1', 'label' => 'Selo da oficina', 'href' => $provenanceUrl('1'), 'count' => $counts['1'] ?? null],
        ['value' => '0', 'label' => 'Declaradas', 'href' => $provenanceUrl('0'), 'count' => $counts['0'] ?? null],
    ];
    $activeFilters = $filters->activeFilterCount();
    $keepQuery = array_filter([
        'verified' => $filters->verified,
        'ordenar' => request()->query('ordenar'),
        'direcao' => request()->query('direcao'),
    ], fn ($value) => is_scalar($value) && (string) $value !== '');
    $total = $maintenances?->total() ?? 0;
@endphp

@section('content')
    <x-ui.container padded>
        <x-ui.page-header
            title="Ordens de serviço"
            description="As OS com o Selo da sua oficina e os serviços que clientes declararam citando a oficina."
        >
            @if($workshop)
                <x-slot:actions>
                    <x-ui.button icon="plus" :href="route('workshop.maintenances.create')">Nova OS</x-ui.button>
                </x-slot:actions>
            @endif
        </x-ui.page-header>

        @if(! $workshop)
            <x-ui.empty-state icon="building-storefront" title="Cadastre sua oficina para ver as OS"
                              description="Com a oficina cadastrada, cada OS registrada aqui recebe o Selo da oficina.">
                <x-slot:actions>
                    <x-ui.button :href="route('workshop.profile.create')">Cadastrar oficina</x-ui.button>
                </x-slot:actions>
            </x-ui.empty-state>
        @else
            <div class="space-y-4">
                <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end sm:justify-between">
                    <x-ui.segmented label="Filtrar por procedência" :options="$provenanceOptions" :value="$filters->verified" />
                    <p class="text-sm text-muted-foreground" data-maintenances-total>
                        {{ $total === 1 ? '1 OS' : number_format($total, 0, ',', '.').' OS' }}{{ $filters->isFiltered() ? ' neste filtro' : '' }}
                    </p>
                </div>

                <details class="group rounded-card border border-border bg-surface shadow-sm" data-maintenance-filters @if($activeFilters > 0) open @endif>
                    <summary class="flex min-h-12 cursor-pointer list-none items-center justify-between gap-3 rounded-card px-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring [&::-webkit-details-marker]:hidden">
                        <span class="inline-flex items-center gap-2 text-sm font-semibold text-foreground">
                            <x-ui.icon name="funnel" class="size-5 text-muted-foreground" />
                            Filtros
                            @if($activeFilters > 0)
                                <x-ui.badge variant="primary" size="sm">{{ $activeFilters === 1 ? '1 ativo' : $activeFilters.' ativos' }}</x-ui.badge>
                            @endif
                        </span>
                        <x-ui.icon name="chevron-down" class="size-5 text-muted-foreground transition-transform duration-fast ease-smooth-out group-open:rotate-180 motion-reduce:transition-none" />
                    </summary>
                    <form method="GET" action="{{ $indexUrl }}" class="grid gap-4 border-t border-border p-4 sm:grid-cols-2 sm:items-start lg:grid-cols-3" aria-label="Filtrar ordens de serviço" data-submit-busy="off">
                        @foreach($keepQuery as $keepName => $keepValue)
                            <input type="hidden" name="{{ $keepName }}" value="{{ $keepValue }}">
                        @endforeach

                        <x-ui.field name="placa" label="Placa" hint="Pode ser só parte da placa.">
                            <x-ui.input id="filtro-placa" :value="$filters->plate" autocomplete="off" autocapitalize="characters" spellcheck="false" maxlength="8" class="font-mono tracking-wider uppercase" />
                        </x-ui.field>

                        <fieldset class="grid min-w-0 grid-cols-2 gap-2">
                            <legend class="mb-1.5 text-sm font-medium text-foreground">Período do serviço</legend>
                            <x-ui.field name="de" label="De">
                                <x-ui.input type="date" id="filtro-de" :value="$filters->from?->toDateString()" />
                            </x-ui.field>
                            <x-ui.field name="ate" label="Até">
                                <x-ui.input type="date" id="filtro-ate" :value="$filters->until?->toDateString()" />
                            </x-ui.field>
                        </fieldset>

                        <x-ui.field name="categoria" label="Categoria">
                            <x-ui.select id="filtro-categoria" :options="ServiceCategory::options()" :value="$filters->category" placeholder="Todas as categorias" />
                        </x-ui.field>

                        <x-ui.field name="anexos" label="Anexos">
                            <x-ui.select id="filtro-anexos" :options="WorkshopMaintenanceFilters::ATTACHMENT_OPTIONS" :value="$filters->attachments" placeholder="Todos" />
                        </x-ui.field>

                        <x-ui.field name="garantia" label="Garantia">
                            <x-ui.select id="filtro-garantia" :options="WorkshopMaintenanceFilters::WARRANTY_OPTIONS" :value="$filters->warranty" placeholder="Todas" />
                        </x-ui.field>

                        <div class="flex flex-wrap items-end gap-2 max-sm:*:grow">
                            @if($activeFilters > 0)
                                <x-ui.button variant="ghost" :href="$indexUrl.($filters->verified !== '' ? '?verified='.$filters->verified : '')">Limpar filtros</x-ui.button>
                            @endif
                            <x-ui.button type="submit" variant="secondary" icon="funnel">Filtrar</x-ui.button>
                        </div>
                    </form>
                </details>

                @if($maintenances->isEmpty())
                    @if($filters->isFiltered())
                        <x-ui.empty-state icon="funnel" title="Nenhuma OS neste filtro"
                                          description="Mude os filtros ou limpe todos para ver as OS da oficina." data-maintenances-empty="filtered">
                            <x-slot:actions>
                                <x-ui.button variant="secondary" :href="$indexUrl">Limpar filtros</x-ui.button>
                            </x-slot:actions>
                        </x-ui.empty-state>
                    @else
                        <x-ui.empty-state icon="wrench-screwdriver" title="Nenhuma OS registrada ainda"
                                          description="Registre a primeira OS pela placa do veículo: ela recebe o Selo da oficina e entra no histórico do cliente." data-maintenances-empty="all">
                            <x-slot:actions>
                                <x-ui.button icon="plus" :href="route('workshop.maintenances.create')">Nova OS</x-ui.button>
                            </x-slot:actions>
                        </x-ui.empty-state>
                    @endif
                @else
                    @include('workshop.maintenances._table', [
                        'maintenances' => $maintenances,
                        'caption' => 'Ordens de serviço',
                        'sortable' => true,
                        'sort' => $filters->sort,
                        'direction' => $filters->direction,
                    ])

                    @if($maintenances->hasPages())
                        <div class="pt-2">{{ $maintenances->links() }}</div>
                    @endif
                @endif
            </div>
        @endif
    </x-ui.container>
@endsection
