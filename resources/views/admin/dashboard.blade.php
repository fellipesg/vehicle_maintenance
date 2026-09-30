@extends('layouts.admin')

@section('title', 'Visão geral')

@php
    $adminBreadcrumbs = [['Visão geral']];
    $formatCount = fn (int $count): string => number_format($count, 0, ',', '.');
    $ownerCount = (int) ($usersByType['user'] ?? 0);
    $garageCount = (int) ($usersByType['garage'] ?? 0);
    $workshopAccountCount = (int) ($usersByType['workshop'] ?? 0);
    $pluralize = fn (int $count, string $singular, string $plural): string => $formatCount($count).' '.($count === 1 ? $singular : $plural);

    // Variação dos KPIs: o que entrou nos últimos 30 dias, com seta e texto (nunca só a cor).
    $recentTrend = fn (int $count, string $noneLabel): array => $count > 0
        ? ['trend' => 'up', 'label' => '+'.$formatCount($count).' nos últimos '.$recentDays.' dias']
        : ['trend' => 'neutral', 'label' => $noneLabel.' nos últimos '.$recentDays.' dias'];
    $userTrend = $recentTrend($newUserCount, 'Nenhum cadastro');
    $vehicleTrend = $recentTrend($newVehicleCount, 'Nenhum veículo novo');
    $maintenanceTrend = $recentTrend($newMaintenanceCount, 'Nenhum registro');

    $pendingItems = collect([
        [
            'count' => $workshopsWithoutCoordinatesCount,
            'icon' => 'map-pin',
            'title' => $pluralize($workshopsWithoutCoordinatesCount, 'oficina sem coordenadas', 'oficinas sem coordenadas'),
            'description' => 'Ficam fora do mapa e da busca por proximidade. Confira o endereço.',
            'href' => route('admin.workshops.index', ['localizacao' => 'sem-coordenadas']),
            'action' => 'Ver oficinas',
        ],
        [
            'count' => $scheduledPostCount,
            'icon' => 'clock',
            'title' => $pluralize($scheduledPostCount, 'artigo agendado', 'artigos agendados'),
            'description' => 'Entram no blog, no feed e no sitemap na data marcada.',
            'href' => route('admin.blog.index', ['status' => \App\Models\BlogPost::FILTER_SCHEDULED]),
            'action' => 'Ver agendados',
        ],
        [
            'count' => $draftPostCount,
            'icon' => 'pencil-square',
            'title' => $pluralize($draftPostCount, 'rascunho no blog', 'rascunhos no blog'),
            'description' => 'Só o admin vê. Revise e publique quando estiverem prontos.',
            'href' => route('admin.blog.index', ['status' => \App\Models\BlogPost::STATUS_DRAFT]),
            'action' => 'Ver rascunhos',
        ],
    ])->filter(fn (array $item): bool => $item['count'] > 0)->values();
@endphp

@section('content')
    <x-ui.page-header title="Visão geral" description="Crescimento da plataforma, adoção do Selo da oficina e o que precisa de atenção." />

    <div class="space-y-10">
        <section aria-labelledby="indicadores-titulo">
            <h2 id="indicadores-titulo" class="sr-only">Indicadores</h2>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4" data-slot="admin-kpis">
                <x-ui.stat
                    label="Usuários"
                    :value="$formatCount($userCount)"
                    icon="users"
                    :href="route('admin.users.index')"
                    :trend="$userTrend['trend']"
                    :trend-label="$userTrend['label']"
                    :hint="$pluralize($ownerCount, 'proprietário', 'proprietários').' · '.$pluralize($garageCount, 'lojista', 'lojistas').' · '.$pluralize($workshopAccountCount, 'oficina', 'oficinas')"
                />
                <x-ui.stat
                    label="Veículos"
                    :value="$formatCount($vehicleCount)"
                    icon="truck"
                    :href="route('admin.vehicles.index')"
                    :trend="$vehicleTrend['trend']"
                    :trend-label="$vehicleTrend['label']"
                />
                <x-ui.stat
                    label="Manutenções"
                    :value="$formatCount($maintenanceCount)"
                    icon="wrench-screwdriver"
                    :href="route('admin.maintenances.index')"
                    :trend="$maintenanceTrend['trend']"
                    :trend-label="$maintenanceTrend['label']"
                    :hint="$sealedPercent.'% com Selo da oficina'"
                >
                    {{-- A porcentagem já está no texto; a barra só repete o dado para quem enxerga. --}}
                    <div class="h-1.5 overflow-hidden rounded-full bg-prov-declared-surface ring-1 ring-border ring-inset" aria-hidden="true" data-slot="sealed-share">
                        <div class="h-full rounded-full bg-prov-verified" style="width: {{ $sealedPercent }}%"></div>
                    </div>
                </x-ui.stat>
                <x-ui.stat
                    label="Oficinas"
                    :value="$formatCount($workshopCount)"
                    icon="building-storefront"
                    :href="route('admin.workshops.index')"
                    :hint="$workshopsWithoutCoordinatesCount > 0 ? $pluralize($workshopsWithoutCoordinatesCount, 'sem coordenadas', 'sem coordenadas') : 'Todas no mapa'"
                />
            </div>
        </section>

        <x-ui.section id="manutencoes-por-mes" title="Manutenções por mês" description="Selo da oficina × Declaradas, pela data do serviço, nos últimos 12 meses.">
            <x-slot:actions>
                <x-ui.link :href="route('admin.maintenances.index')" arrow>Ver manutenções</x-ui.link>
            </x-slot:actions>

            <x-admin.provenance-chart :series="$monthlySeries" />
        </x-ui.section>

        <div class="grid items-start gap-10 lg:grid-cols-2">
            <x-ui.section id="cadastros-recentes" title="Cadastros recentes" description="As cinco contas mais novas da plataforma.">
                <x-slot:actions>
                    <x-ui.link :href="route('admin.users.index')" arrow>Ver todos os usuários</x-ui.link>
                </x-slot:actions>

                @if($recentUsers->isEmpty())
                    <x-ui.empty-state icon="users" title="Nenhum usuário cadastrado" description="As contas aparecem aqui assim que alguém se cadastra." />
                @else
                    <ul role="list" class="divide-y divide-border rounded-card border border-border bg-surface" data-slot="recent-users">
                        @foreach($recentUsers as $account)
                            <li class="flex items-center gap-3 px-4 py-3">
                                <x-ui.avatar :name="$account->name" size="sm" />
                                <div class="min-w-0 flex-1">
                                    <p class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                        <x-ui.link :href="route('admin.users.show', $account)" class="font-medium">{{ $account->name }}</x-ui.link>
                                        <x-admin.user-type-badge :user="$account" size="sm" />
                                        @if($account->is_admin)
                                            <x-ui.badge variant="info" size="sm" icon="shield-check">Administrador</x-ui.badge>
                                        @endif
                                    </p>
                                    <p class="truncate text-sm text-muted-foreground">{{ $account->email }}</p>
                                </div>
                                @if($account->created_at)
                                    <time datetime="{{ $account->created_at->toIso8601String() }}" class="shrink-0 text-xs text-subtle-foreground" title="{{ \App\Support\DisplayTime::local($account->created_at)->format('d/m/Y H:i') }}">{{ $account->created_at->locale('pt_BR')->diffForHumans() }}</time>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.section>

            <x-ui.section id="pendencias" title="Pendências" description="O que precisa de uma ação do admin.">
                @if($pendingItems->isEmpty())
                    <x-ui.empty-state icon="check-circle" title="Nada pendente" description="Todas as oficinas estão no mapa e o blog não tem rascunhos nem artigos agendados." />
                @else
                    <ul role="list" class="divide-y divide-border rounded-card border border-border bg-surface" data-slot="pending-items">
                        @foreach($pendingItems as $pendingItem)
                            <li class="flex items-start gap-3 px-4 py-3">
                                <span class="inline-flex size-9 shrink-0 items-center justify-center rounded-control bg-warning-soft text-warning" aria-hidden="true">
                                    <x-ui.icon :name="$pendingItem['icon']" />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="font-medium text-foreground tabular-nums">{{ $pendingItem['title'] }}</p>
                                    <p class="text-sm text-muted-foreground">{{ $pendingItem['description'] }}</p>
                                </div>
                                <x-ui.link :href="$pendingItem['href']" class="shrink-0 self-center text-sm">{{ $pendingItem['action'] }}</x-ui.link>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.section>
        </div>
    </div>
@endsection
