@extends('layouts.admin')

@section('title', 'Oficinas')

@php
    use App\Enums\WorkshopStatus;
    use App\Http\Controllers\Web\Admin\WorkshopController;

    $adminBreadcrumbs = [['Cadastros'], ['Oficinas']];
    $formatCount = fn (int $count): string => number_format($count, 0, ',', '.');
    $hasSearch = $search !== '';
    $keptQuery = fn (array $overrides = []): array => array_filter(array_merge([
        'q' => $hasSearch ? $search : null,
        'filtro' => $filter,
        'localizacao' => $location !== '' ? $location : null,
        'ordenar' => request()->query('ordenar') ? $sort : null,
        'direcao' => request()->query('direcao') ? $direction : null,
    ], $overrides), fn (mixed $value): bool => $value !== null && $value !== '');
    $locationOptions = collect(WorkshopController::LOCATIONS)
        ->map(fn (string $locationLabel, string $locationValue): array => [
            'value' => $locationValue,
            'label' => $locationLabel,
            'count' => $locationCounts[$locationValue] ?? 0,
            'href' => route('admin.workshops.index', $keptQuery(['localizacao' => $locationValue, 'page' => null])),
        ])
        ->values()
        ->all();
    $statusTabs = [
        ['value' => WorkshopStatus::Pending->value, 'label' => 'Em análise', 'count' => $counts[WorkshopStatus::Pending->value], 'href' => route('admin.workshops.index', $keptQuery(['filtro' => WorkshopStatus::Pending->value, 'page' => null]))],
        ['value' => WorkshopStatus::Approved->value, 'label' => 'Aprovadas', 'count' => $counts[WorkshopStatus::Approved->value], 'href' => route('admin.workshops.index', $keptQuery(['filtro' => WorkshopStatus::Approved->value, 'page' => null]))],
        ['value' => WorkshopStatus::Rejected->value, 'label' => 'Recusadas', 'count' => $counts[WorkshopStatus::Rejected->value], 'href' => route('admin.workshops.index', $keptQuery(['filtro' => WorkshopStatus::Rejected->value, 'page' => null]))],
        ['value' => 'todas', 'label' => 'Todas', 'href' => route('admin.workshops.index', $keptQuery(['filtro' => 'todas', 'page' => null]))],
    ];
    $emptyTitle = match (true) {
        $hasSearch => 'Nenhuma oficina para “'.$search.'”',
        $location === 'sem-coordenadas' => 'Todas as oficinas estão no mapa',
        $location === 'no-mapa' => 'Nenhuma oficina no mapa ainda',
        default => 'Nenhuma oficina cadastrada',
    };
@endphp

@section('content')
    <x-ui.page-header title="Oficinas" description="Uma oficina escreve no histórico permanente de um chassi com o Selo da oficina, move o hodômetro do veículo e manda convite para terceiros. Confira o CNPJ no registro público antes de liberar.">
        <x-slot:actions>
            <x-admin.view-switch :list="route('admin.workshops.index')" :map="route('admin.maps.workshops')" current="list" label="Visualização das oficinas" />
        </x-slot:actions>
    </x-ui.page-header>

    <x-ui.segmented label="Filtrar oficinas pela análise" :options="$statusTabs" :value="$filter" class="mb-4" />

    <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between" data-slot="admin-workshops-toolbar">
        <x-ui.segmented label="Filtrar oficinas por localização" :options="$locationOptions" :value="$location" />

        <form method="GET" action="{{ route('admin.workshops.index') }}" role="search" aria-label="Buscar oficinas" class="flex w-full gap-2 lg:w-auto" data-submit-busy="off">
            @foreach($keptQuery(['q' => null, 'page' => null]) as $queryKey => $queryValue)
                <input type="hidden" name="{{ $queryKey }}" value="{{ $queryValue }}">
            @endforeach
            <label for="admin-workshop-search" class="sr-only">Buscar por nome ou cidade</label>
            <x-ui.input
                type="search"
                id="admin-workshop-search"
                name="q"
                :value="$search"
                placeholder="Nome ou cidade"
                leading-icon="magnifying-glass"
                autocomplete="off"
                class="min-w-0 flex-1 lg:w-72"
            />
            <x-ui.button type="submit" variant="secondary">Buscar</x-ui.button>
        </form>
    </div>

    <p class="mb-4 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted-foreground" data-admin-workshops-summary>
        <span>
            @if($hasSearch)
                {{ $formatCount($workshops->total()) }} {{ $workshops->total() === 1 ? 'resultado' : 'resultados' }} para
                <span class="font-medium text-foreground">“{{ $search }}”</span>
            @else
                {{ $formatCount($workshops->total()) }} {{ $workshops->total() === 1 ? 'oficina' : 'oficinas' }}
            @endif
        </span>
        @if($hasSearch)
            <x-ui.link :href="route('admin.workshops.index', $keptQuery(['q' => null, 'page' => null]))" icon="x-mark">Limpar busca</x-ui.link>
        @endif
    </p>

    <x-ui.table caption="Oficinas cadastradas" stack :sort="$sort" :direction="$direction">
        <x-slot:head>
            <tr>
                <th class="min-w-56" data-sort="nome">Oficina</th>
                <th class="min-w-48">Análise</th>
                <th class="min-w-60" data-sort="cidade">Endereço</th>
                <th class="text-right" data-sort="selos" data-sort-default="desc">Manutenções com selo</th>
                <th>Localização</th>
                <th>Fotos das manutenções</th>
                <th class="text-right"><span class="sr-only">Ações</span></th>
            </tr>
        </x-slot:head>

        @foreach($workshops as $workshop)
            @php
                $workshopCity = collect([$workshop->city, $workshop->state])->filter(fn ($part) => filled($part))->implode('/');
                $workshopStreet = collect([$workshop->street, $workshop->number])->filter(fn ($part) => filled($part))->implode(', ');
                $workshopAddress = collect([$workshopStreet, $workshop->neighborhood])->filter(fn ($part) => filled($part))->implode(' — ');
                $workshopIsOnMap = filled($workshop->latitude) && filled($workshop->longitude);
            @endphp
            <tr data-workshop-row="{{ $workshop->id }}">
                <th scope="row" class="font-medium">
                    @if($workshop->user_id)
                        <x-ui.link :href="route('admin.users.show', $workshop->user_id)">{{ $workshop->name }}</x-ui.link>
                    @else
                        {{ $workshop->name }}
                    @endif
                    @if($workshopCity !== '')
                        <span class="block text-xs font-normal text-muted-foreground">{{ $workshopCity }}</span>
                    @endif
                </th>
                <td data-slot="workshop-review">
                    <x-ui.badge :variant="$workshop->status->badgeVariant()">{{ $workshop->status->label() }}</x-ui.badge>
                    <span class="mt-1 block text-xs text-muted-foreground">
                        CNPJ: <span class="tabular-nums text-foreground">{{ $workshop->displayDocument() ?? 'não informado' }}</span>
                    </span>
                    @if($workshop->reviewed_at)
                        <span class="block text-xs text-muted-foreground">
                            {{ $workshop->reviewed_at->format('d/m/Y H:i') }}@if($workshop->reviewer) · {{ $workshop->reviewer->name }}@endif
                        </span>
                    @endif
                    @if(filled($workshop->review_notes))
                        <span class="block text-xs text-muted-foreground">{{ $workshop->review_notes }}</span>
                    @endif
                </td>
                <td class="text-muted-foreground">{{ $workshopAddress !== '' ? $workshopAddress : '—' }}</td>
                <td class="text-right">{{ $formatCount((int) $workshop->sealed_maintenances_count) }}</td>
                <td>
                    @if($workshopIsOnMap)
                        <x-ui.badge variant="success" icon="map-pin">No mapa</x-ui.badge>
                    @else
                        <x-ui.badge variant="warning" icon="exclamation-triangle">Sem coordenadas</x-ui.badge>
                    @endif
                </td>
                <td data-slot="maintenance-photos-status">
                    @if(\App\Support\Maintenance\MaintenancePhotoAccess::isActiveFor($workshop))
                        <x-ui.badge variant="success" icon="photo">Ligadas</x-ui.badge>
                    @else
                        <x-ui.badge variant="neutral">Desligadas</x-ui.badge>
                    @endif
                </td>
                <td class="py-2 text-right">
                    <div class="flex flex-col items-stretch gap-2 lg:items-end">
                        @if($workshop->status !== WorkshopStatus::Approved)
                            <form method="POST" action="{{ route('admin.workshops.approve', $workshop) }}">
                                @csrf
                                <x-ui.button size="sm" type="submit" icon="check" loading-label="Aprovando…">Aprovar oficina</x-ui.button>
                            </form>
                        @endif

                        @if($workshop->status !== WorkshopStatus::Rejected)
                            <form method="POST" action="{{ route('admin.workshops.reject', $workshop) }}" class="flex flex-wrap items-center gap-2 lg:justify-end">
                                @csrf
                                <x-ui.input name="review_notes" placeholder="Motivo (opcional)" maxlength="1000" class="text-sm" :aria-label="'Motivo da recusa da oficina '.$workshop->name" />
                                <x-ui.button variant="secondary" size="sm" type="submit" loading-label="Recusando…">Recusar</x-ui.button>
                            </form>
                        @endif
                    </div>

                    <x-admin.row-actions :label="'Ações para a oficina '.$workshop->name" :id="'oficina-'.$workshop->id.'-acoes'">
                        @if($workshop->user_id)
                            <x-ui.dropdown-item :href="route('admin.users.show', $workshop->user_id)" icon="user-circle">Abrir cadastro</x-ui.dropdown-item>
                        @endif
                        <x-ui.dropdown-item :href="route('admin.maintenances.index', ['oficina' => $workshop->id])" icon="wrench-screwdriver">Ver manutenções</x-ui.dropdown-item>
                        @if($workshopIsOnMap)
                            <x-ui.dropdown-item :href="route('admin.maps.workshops')" icon="map">Ver no mapa</x-ui.dropdown-item>
                        @endif
                    </x-admin.row-actions>
                </td>
            </tr>
        @endforeach

        <x-slot:empty>
            @if($hasSearch || $location !== '')
                <x-ui.empty-state
                    :icon="$location === 'sem-coordenadas' && ! $hasSearch ? 'check-circle' : 'magnifying-glass'"
                    :title="$emptyTitle"
                    :description="$hasSearch ? 'Confira o nome ou a cidade e busque de novo.' : 'Mostre todas as oficinas para ver o restante.'"
                    variant="plain"
                    size="sm"
                    heading-level="p"
                >
                    <x-slot:actions>
                        <x-ui.button variant="secondary" :href="route('admin.workshops.index')">{{ $hasSearch ? 'Limpar busca' : 'Mostrar todas' }}</x-ui.button>
                    </x-slot:actions>
                </x-ui.empty-state>
            @else
                <x-ui.empty-state
                    icon="building-storefront"
                    title="Nenhuma oficina cadastrada"
                    description="As oficinas aparecem aqui quando uma conta de oficina completa o cadastro."
                    variant="plain"
                    size="sm"
                    heading-level="p"
                />
            @endif
        </x-slot:empty>
    </x-ui.table>

    <div class="mt-4">{{ $workshops->links() }}</div>
@endsection
