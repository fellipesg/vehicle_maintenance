@extends('layouts.admin')

@section('title', 'Usuários')

@php
    use App\Http\Controllers\Web\Admin\UserController;

    $adminBreadcrumbs = [['Cadastros'], ['Usuários']];
    $formatCount = fn (int $count): string => number_format($count, 0, ',', '.');
    $hasSearch = $search !== '';
    $isFiltered = $hasSearch || $profile !== '' || $withoutCoordinates;
    $keptQuery = fn (array $overrides = []): array => array_filter(array_merge([
        'q' => $hasSearch ? $search : null,
        'perfil' => $profile !== '' ? $profile : null,
        'localizacao' => $withoutCoordinates ? 'sem-coordenadas' : null,
        'ordenar' => request()->query('ordenar') ? $sort : null,
        'direcao' => request()->query('direcao') ? $direction : null,
    ], $overrides), fn (mixed $value): bool => $value !== null && $value !== '');
    $profileOptions = collect(UserController::PROFILES)
        ->map(fn (string $profileLabel, string $profileValue): array => [
            'value' => $profileValue,
            'label' => $profileLabel,
            'count' => $profileCounts[$profileValue] ?? 0,
            'href' => route('admin.users.index', $keptQuery(['perfil' => $profileValue, 'page' => null])),
        ])
        ->values()
        ->all();
    $profileNoun = match ($profile) {
        'proprietarios' => ['proprietário', 'proprietários'],
        'lojistas' => ['lojista', 'lojistas'],
        'oficinas' => ['conta de oficina', 'contas de oficina'],
        'administradores' => ['administrador', 'administradores'],
        default => ['usuário', 'usuários'],
    };
    $emptyFilteredTitle = $hasSearch
        ? 'Nenhum usuário para “'.$search.'”'
        : 'Nenhum '.$profileNoun[0].' neste filtro';
@endphp

@section('content')
    <x-ui.page-header title="Usuários" description="Contas da plataforma: proprietários, lojistas, oficinas e administradores.">
        <x-slot:actions>
            <x-admin.view-switch :list="route('admin.users.index')" :map="route('admin.maps.users')" current="list" label="Visualização dos usuários" />
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between" data-slot="admin-users-toolbar">
        <x-ui.segmented label="Filtrar usuários por perfil" :options="$profileOptions" :value="$profile" />

        <form method="GET" action="{{ route('admin.users.index') }}" role="search" aria-label="Buscar usuários" class="flex w-full gap-2 lg:w-auto" data-submit-busy="off">
            @foreach($keptQuery(['q' => null, 'page' => null]) as $queryKey => $queryValue)
                <input type="hidden" name="{{ $queryKey }}" value="{{ $queryValue }}">
            @endforeach
            <label for="admin-user-search" class="sr-only">Buscar por nome ou e-mail</label>
            <x-ui.input
                type="search"
                id="admin-user-search"
                name="q"
                :value="$search"
                placeholder="Nome ou e-mail"
                leading-icon="magnifying-glass"
                autocomplete="off"
                class="min-w-0 flex-1 lg:w-72"
            />
            <x-ui.button type="submit" variant="secondary">Buscar</x-ui.button>
        </form>
    </div>

    <p class="mb-4 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted-foreground" data-admin-users-summary>
        <span>
            @if($hasSearch)
                {{ $formatCount($users->total()) }} {{ $users->total() === 1 ? 'resultado' : 'resultados' }} para
                <span class="font-medium text-foreground">“{{ $search }}”</span>
            @else
                {{ $formatCount($users->total()) }} {{ $users->total() === 1 ? $profileNoun[0] : $profileNoun[1] }}
            @endif
            @if($withoutCoordinates)
                sem coordenadas
            @endif
        </span>
        @if($hasSearch)
            <x-ui.link :href="route('admin.users.index', $keptQuery(['q' => null, 'page' => null]))" icon="x-mark">Limpar busca</x-ui.link>
        @endif
        @if($withoutCoordinates)
            <x-ui.link :href="route('admin.users.index', $keptQuery(['localizacao' => null, 'page' => null]))" icon="x-mark">Mostrar todas as localizações</x-ui.link>
        @endif
    </p>

    <x-ui.table caption="Usuários cadastrados" stack :sort="$sort" :direction="$direction">
        <x-slot:head>
            <tr>
                <th class="min-w-60" data-sort="nome">Nome</th>
                <th>Perfil</th>
                <th class="text-right" data-sort="veiculos" data-sort-default="desc">Veículos atuais</th>
                <th class="text-right" data-sort="manutencoes" data-sort-default="desc">Manutenções lançadas</th>
                <th data-sort="cadastro" data-sort-default="desc">Cadastro</th>
                <th class="text-right"><span class="sr-only">Ações</span></th>
            </tr>
        </x-slot:head>

        @foreach($users as $account)
            <tr>
                <th scope="row" class="font-medium">
                    <span class="flex items-center gap-3">
                        <x-ui.avatar :name="$account->name" size="sm" class="max-md:hidden" />
                        <span class="min-w-0">
                            <x-ui.link :href="route('admin.users.show', $account)">{{ $account->name }}</x-ui.link>
                            <span class="block truncate text-xs font-normal text-muted-foreground">{{ $account->email }}</span>
                        </span>
                    </span>
                </th>
                <td>
                    <span class="inline-flex flex-wrap justify-end gap-1.5 md:justify-start">
                        <x-admin.user-type-badge :user="$account" size="sm" />
                        @if($account->is_admin)
                            <x-ui.badge variant="info" size="sm" icon="shield-check">Administrador</x-ui.badge>
                        @endif
                    </span>
                </td>
                <td class="text-right">{{ $formatCount((int) $account->current_vehicles_count) }}</td>
                <td class="text-right">{{ $formatCount((int) $account->maintenances_count) }}</td>
                <td class="whitespace-nowrap">
                    @if($account->created_at)
                        <time datetime="{{ $account->created_at->toDateString() }}">{{ $account->created_at->format('d/m/Y') }}</time>
                    @else
                        —
                    @endif
                </td>
                <td class="py-2 text-right">
                    <x-admin.row-actions :label="'Ações para '.$account->name" :id="'usuario-'.$account->id.'-acoes'">
                        <x-ui.dropdown-item :href="route('admin.users.show', $account)" icon="user-circle">Abrir cadastro</x-ui.dropdown-item>
                        @if($account->maintenances_count > 0)
                            <x-ui.dropdown-item :href="route('admin.maintenances.index', ['usuario' => $account->id])" icon="wrench-screwdriver">Ver manutenções lançadas</x-ui.dropdown-item>
                        @endif
                        <x-ui.dropdown-item :href="'mailto:'.$account->email" icon="envelope">Enviar e-mail</x-ui.dropdown-item>
                    </x-admin.row-actions>
                </td>
            </tr>
        @endforeach

        <x-slot:empty>
            @if($isFiltered)
                <x-ui.empty-state
                    icon="magnifying-glass"
                    :title="$emptyFilteredTitle"
                    :description="$hasSearch ? 'Confira o nome ou o e-mail e busque de novo.' : 'Mostre todos os usuários para ver as outras contas.'"
                    variant="plain"
                    size="sm"
                    heading-level="p"
                >
                    <x-slot:actions>
                        <x-ui.button variant="secondary" :href="route('admin.users.index')">{{ $hasSearch ? 'Limpar busca' : 'Mostrar todos' }}</x-ui.button>
                    </x-slot:actions>
                </x-ui.empty-state>
            @else
                <x-ui.empty-state
                    icon="users"
                    title="Nenhum usuário cadastrado"
                    description="As contas aparecem aqui assim que alguém se cadastra."
                    variant="plain"
                    size="sm"
                    heading-level="p"
                />
            @endif
        </x-slot:empty>
    </x-ui.table>

    <div class="mt-4">
        {{ $users->links() }}
    </div>
@endsection
