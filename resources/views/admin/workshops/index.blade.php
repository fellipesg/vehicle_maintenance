@extends('layouts.admin')

@section('title', 'Oficinas')

@php
    use App\Http\Controllers\Web\Admin\WorkshopController;

    $adminBreadcrumbs = [['Cadastros'], ['Oficinas']];
    $formatCount = fn (int $count): string => number_format($count, 0, ',', '.');
    $hasSearch = $search !== '';
    $keptQuery = fn (array $overrides = []): array => array_filter(array_merge([
        'q' => $hasSearch ? $search : null,
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
    $emptyTitle = match (true) {
        $hasSearch => 'Nenhuma oficina para “'.$search.'”',
        $location === 'sem-coordenadas' => 'Todas as oficinas estão no mapa',
        $location === 'no-mapa' => 'Nenhuma oficina no mapa ainda',
        default => 'Nenhuma oficina cadastrada',
    };
@endphp

@section('content')
    <x-ui.page-header title="Oficinas" description="Oficinas cadastradas na plataforma, com a situação no mapa e as manutenções que cada uma verificou.">
        <x-slot:actions>
            <x-admin.view-switch :list="route('admin.workshops.index')" :map="route('admin.maps.workshops')" current="list" label="Visualização das oficinas" />
        </x-slot:actions>
    </x-ui.page-header>

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
                <th class="min-w-60" data-sort="cidade">Endereço</th>
                <th class="text-right" data-sort="selos" data-sort-default="desc">Manutenções com selo</th>
                <th>Localização</th>
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
            <tr>
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
                <td class="text-muted-foreground">{{ $workshopAddress !== '' ? $workshopAddress : '—' }}</td>
                <td class="text-right">{{ $formatCount((int) $workshop->sealed_maintenances_count) }}</td>
                <td>
                    @if($workshopIsOnMap)
                        <x-ui.badge variant="success" icon="map-pin">No mapa</x-ui.badge>
                    @else
                        <x-ui.badge variant="warning" icon="exclamation-triangle">Sem coordenadas</x-ui.badge>
                    @endif
                </td>
                <td class="py-2 text-right">
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
