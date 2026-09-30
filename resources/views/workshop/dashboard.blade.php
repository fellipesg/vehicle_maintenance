{{--
    Início da Oficina como fila de trabalho: "Nova OS por placa" (GET para a Nova OS, que confere o
    veículo), indicadores com totais reais (cada um abre a lista de OS filtrada), pendências com a
    próxima ação e as OS recentes. Sem oficina cadastrada, o estado vazio lista o que falta.
--}}
@extends('layouts.app')

@section('title', 'Início')

@php
    $number = fn (int $value): string => number_format($value, 0, ',', '.');
    $ordersUrl = fn (array $query = []): string => route('workshop.maintenances.index', $query);
@endphp

@section('content')
    <x-ui.container padded>
        <x-ui.page-header :title="'Olá, '.auth()->user()->name"
                          :description="$workshop ? 'Registre as OS pela placa e acompanhe o que falta em cada uma.' : 'Falta pouco para registrar a primeira OS com o Selo da oficina.'">
            @if($workshop)
                <x-slot:actions>
                    <x-ui.button icon="plus" :href="route('workshop.maintenances.create')">Nova OS</x-ui.button>
                </x-slot:actions>
            @endif
        </x-ui.page-header>

        @if(! $workshop)
            <x-ui.empty-state icon="building-storefront" heading-level="h2" title="Complete o cadastro da sua oficina"
                              description="Com a oficina cadastrada, cada OS recebe o Selo da oficina e a oficina aparece no diretório para os clientes." data-dashboard-empty>
                <ol class="mt-1 space-y-1.5 text-left">
                    <li class="flex items-start gap-2"><span class="inline-flex size-5 shrink-0 items-center justify-center rounded-full bg-accent text-xs font-semibold text-accent-foreground" aria-hidden="true">1</span>Dados e endereço da oficina</li>
                    <li class="flex items-start gap-2"><span class="inline-flex size-5 shrink-0 items-center justify-center rounded-full bg-accent text-xs font-semibold text-accent-foreground" aria-hidden="true">2</span>Logo, que aparece no Selo da oficina e no PDF</li>
                    <li class="flex items-start gap-2"><span class="inline-flex size-5 shrink-0 items-center justify-center rounded-full bg-accent text-xs font-semibold text-accent-foreground" aria-hidden="true">3</span>Um modelo de garantia para anexar às OS</li>
                </ol>
                <x-slot:actions>
                    <x-ui.button :href="route('workshop.profile.create')">Cadastrar oficina</x-ui.button>
                </x-slot:actions>
            </x-ui.empty-state>
        @else
            <div class="space-y-8">
                <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)]">
                    <x-ui.card as="section" title="Nova OS por placa" heading-level="h2"
                               description="Digite a placa: conferimos o veículo antes de você preencher a OS." data-new-order-by-plate>
                        <form method="GET" action="{{ route('workshop.maintenances.create') }}" role="search" aria-label="Nova OS pela placa">
                            <x-ui.field name="license_plate" label="Placa do veículo" hint="ABC1D23 ou ABC1234, sem traço.">
                                <div class="flex flex-col gap-2 sm:flex-row">
                                    <x-ui.input id="inicio-placa" required maxlength="8" autocomplete="off" autocapitalize="characters" spellcheck="false"
                                                data-mask="plate" class="font-mono tracking-wider uppercase sm:max-w-48" />
                                    <x-ui.button type="submit" icon="magnifying-glass" loading-label="Buscando…">Buscar veículo</x-ui.button>
                                </div>
                            </x-ui.field>
                        </form>
                    </x-ui.card>

                    <section aria-labelledby="indicadores-titulo">
                        <h2 id="indicadores-titulo" class="sr-only">Indicadores da oficina</h2>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <x-ui.stat label="OS no mês" :value="$number($stats['month'])" hint="Com o Selo da oficina, pela data do serviço" icon="calendar-days"
                                       :href="$ordersUrl(['verified' => '1', 'de' => $stats['month_start'], 'ate' => $stats['today']])" data-stat="month" />
                            <x-ui.stat label="Selos emitidos" :value="$number($stats['sealed'])" hint="Todas as OS da oficina" icon="shield-check"
                                       :href="$ordersUrl(['verified' => '1'])" data-stat="sealed" />
                            <x-ui.stat label="Garantias vigentes" :value="$number($stats['warranties'])" hint="Termos ainda válidos" icon="check-circle"
                                       :href="$ordersUrl(['garantia' => 'vigente'])" data-stat="warranties" />
                            <x-ui.stat label="Vencem em 30 dias" :value="$number($stats['expiring'])" hint="Bom momento para chamar o cliente" icon="clock"
                                       :href="$ordersUrl(['garantia' => 'vencendo'])" data-stat="expiring" />
                        </div>
                    </section>
                </div>

                <x-ui.section title="Pendências" description="O que deixa as OS e o perfil completos para o cliente." data-pending>
                    @if(empty($pendingItems))
                        <x-ui.empty-state variant="plain" size="sm" icon="check-circle" heading-level="h3" title="Tudo em dia"
                                          description="As OS têm nota fiscal e fotos de depois, e o perfil está completo." class="rounded-card border border-border bg-surface" />
                    @else
                        <ul role="list" class="grid gap-3 sm:grid-cols-2">
                            @foreach($pendingItems as $pending)
                                <li class="flex flex-col gap-3 rounded-card border border-border bg-surface p-4 shadow-sm" data-pending-item="{{ $pending['key'] }}">
                                    <div class="flex items-start gap-3">
                                        <span class="inline-flex size-9 shrink-0 items-center justify-center rounded-control bg-warning-soft text-warning" aria-hidden="true">
                                            <x-ui.icon name="exclamation-triangle" class="size-5" />
                                        </span>
                                        <div class="min-w-0">
                                            <h3 class="text-base font-semibold text-foreground">{{ $pending['label'] }}</h3>
                                            <p class="mt-0.5 text-sm text-muted-foreground">{{ $pending['description'] }}</p>
                                        </div>
                                    </div>
                                    <x-ui.link :href="$pending['url']" arrow class="self-start">{{ $pending['action'] }}</x-ui.link>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-ui.section>

                <x-ui.section title="OS recentes" data-recent>
                    @if($recentMaintenances->isNotEmpty())
                        <x-slot:actions>
                            <x-ui.link :href="$ordersUrl()" arrow>Ver todas as OS</x-ui.link>
                        </x-slot:actions>
                    @endif
                    @if($recentMaintenances->isEmpty())
                        <x-ui.empty-state icon="wrench-screwdriver" heading-level="h3" title="Nenhuma OS registrada ainda"
                                          description="Registre a primeira OS pela placa: ela recebe o Selo da oficina e entra no histórico do cliente.">
                            <x-slot:actions>
                                <x-ui.button icon="plus" :href="route('workshop.maintenances.create')">Nova OS</x-ui.button>
                            </x-slot:actions>
                        </x-ui.empty-state>
                    @else
                        @include('workshop.maintenances._table', [
                            'maintenances' => $recentMaintenances,
                            'caption' => 'OS recentes',
                            'sortable' => false,
                        ])
                    @endif
                </x-ui.section>
            </div>
        @endif
    </x-ui.container>
@endsection
