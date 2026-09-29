{{--
    Início do Lojista: KPIs com os totais reais do estoque (cada um abre o Estoque filtrado), "Prontos
    para vender" (com Selo da oficina, o argumento de venda), "Precisam de atenção" (sem histórico,
    só declaradas e procuração pendente ou recusada, com a próxima ação) e as manutenções recentes.
--}}
@extends('layouts.app')

@section('title', 'Início')

@php
    $plural = fn (int $count, string $singular, string $pluralForm): string => $count === 1 ? '1 '.$singular : number_format($count, 0, ',', '.').' '.$pluralForm;
    $stockUrl = fn (?string $filter = null): string => route('garage.vehicles.index', $filter !== null ? ['filtro' => $filter] : []);
    $vehicleLine = fn ($vehicle): string => collect([$vehicle->year, $vehicle->license_plate])->filter(fn (mixed $part): bool => filled($part))->implode(' · ');
    $attentionCopy = [
        'consignment_rejected' => ['badge' => 'Procuração recusada', 'variant' => 'danger', 'text' => 'Envie uma nova procuração para liberar o histórico.'],
        'consignment_missing' => ['badge' => 'Procuração não enviada', 'variant' => 'warning', 'text' => 'Envie a procuração do proprietário para liberar o histórico.'],
        'consignment_pending' => ['badge' => 'Procuração em análise', 'variant' => 'warning', 'text' => 'Aguardando a análise da equipe.'],
        'without_history' => ['badge' => 'Sem histórico', 'variant' => 'neutral', 'text' => 'Nenhuma manutenção registrada. Registre a revisão pré-venda.'],
        'declared_only' => ['badge' => 'Só declaradas', 'variant' => 'declared', 'text' => 'Leve a uma oficina da rede para ter o Selo da oficina.'],
    ];
@endphp

@section('content')
    <x-ui.container padded>
        <x-ui.page-header :title="'Olá, '.auth()->user()->name" description="Seu estoque e o histórico de procedência que ajuda a vender cada veículo.">
            <x-slot:actions>
                <x-ui.button variant="secondary" icon="wrench-screwdriver" :href="route('garage.maintenances.create')">Registrar manutenção</x-ui.button>
                <x-ui.button icon="plus" :href="route('garage.vehicles.create')">Adicionar ao estoque</x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        @if ($stats['vehicles'] === 0)
            <x-ui.empty-state
                icon="truck"
                title="Seu estoque está vazio"
                description="Adicione o primeiro veículo pelo CRLV-e. O histórico com Selo da oficina vira argumento de venda para o comprador."
                heading-level="h2"
                data-dashboard-empty
            >
                <x-slot:actions>
                    <x-ui.button icon="plus" :href="route('garage.vehicles.create')">Adicionar ao estoque</x-ui.button>
                </x-slot:actions>
            </x-ui.empty-state>
        @else
            <section aria-labelledby="indicadores-titulo" class="mb-8">
                <h2 id="indicadores-titulo" class="sr-only">Indicadores do estoque</h2>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <x-ui.stat
                        label="No estoque"
                        :value="$stats['vehicles']"
                        :hint="$stats['consignment'] > 0 ? $plural($stats['consignment'], 'em consignação', 'em consignação') : 'Todos da loja'"
                        icon="truck"
                        :href="$stockUrl()"
                        data-stat="vehicles"
                    />
                    <x-ui.stat
                        label="Veículos com selo"
                        :value="$stats['sealed_vehicles']"
                        :hint="$plural($stats['sealed'], 'manutenção com Selo da oficina', 'manutenções com Selo da oficina')"
                        icon="shield-check"
                        :href="$stockUrl(\App\Support\Vehicle\DealerStock::FILTER_SEALED)"
                        data-stat="sealed-vehicles"
                    />
                    <x-ui.stat
                        label="Só com declaradas"
                        :value="$stats['declared_only_vehicles']"
                        :hint="$plural($stats['declared'], 'manutenção declarada', 'manutenções declaradas')"
                        icon="document-text"
                        :href="$stockUrl(\App\Support\Vehicle\DealerStock::FILTER_DECLARED_ONLY)"
                        data-stat="declared-only-vehicles"
                    />
                    <x-ui.stat
                        label="Sem histórico"
                        :value="$stats['without_history']"
                        hint="Nenhuma manutenção registrada"
                        icon="clock"
                        :href="$stockUrl(\App\Support\Vehicle\DealerStock::FILTER_WITHOUT_HISTORY)"
                        data-stat="without-history"
                    />
                </div>
            </section>

            <div class="grid gap-8 lg:grid-cols-2">
                <x-ui.section id="prontos-para-vender" title="Prontos para vender" description="Veículos com ao menos uma manutenção com Selo da oficina.">
                    @if ($readyVehicles->isNotEmpty())
                        <x-slot:actions>
                            <x-ui.link :href="$stockUrl(\App\Support\Vehicle\DealerStock::FILTER_SEALED)" arrow>Ver todos</x-ui.link>
                        </x-slot:actions>
                        <ul role="list" class="divide-y divide-border overflow-hidden rounded-card border border-border bg-surface" data-dashboard-list="ready">
                            @foreach ($readyVehicles as $vehicle)
                                <li class="flex items-start gap-3 p-4" data-stock-vehicle="{{ $vehicle->id }}">
                                    <x-vehicle-cover :vehicle="$vehicle" />
                                    <div class="min-w-0 flex-1 space-y-1">
                                        <h3 class="text-base leading-6 font-semibold text-foreground">
                                            <a href="{{ route('garage.vehicles.show', $vehicle) }}" class="rounded-sm underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring">{{ trim($vehicle->brand.' '.$vehicle->model) }}</a>
                                        </h3>
                                        <p class="text-sm text-muted-foreground tabular-nums">{{ $vehicleLine($vehicle) }}</p>
                                        <x-provenance-strip :vehicle="$vehicle" :dot-href="false" class="mb-0" label="Procedência das manutenções deste veículo, da mais antiga à mais recente" />
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <x-ui.empty-state
                            icon="shield-check"
                            title="Nenhum veículo com Selo da oficina ainda"
                            description="O selo aparece quando uma oficina da rede registra o serviço no portal dela. Ele é o argumento de venda mais forte do histórico."
                            size="sm"
                        />
                    @endif
                </x-ui.section>

                <x-ui.section id="precisam-de-atencao" title="Precisam de atenção" description="Sem histórico, só com declaradas ou com a procuração pendente.">
                    @if ($attention->isNotEmpty())
                        <x-slot:actions>
                            <x-ui.link :href="$stockUrl()" arrow>Ver estoque</x-ui.link>
                        </x-slot:actions>
                        <ul role="list" class="divide-y divide-border overflow-hidden rounded-card border border-border bg-surface" data-dashboard-list="attention">
                            @foreach ($attention as $item)
                                @php
                                    $vehicle = $item['vehicle'];
                                    $copy = $attentionCopy[$item['reason']];
                                @endphp
                                <li class="flex items-start gap-3 p-4" data-stock-vehicle="{{ $vehicle->id }}" data-attention="{{ $item['reason'] }}">
                                    <x-vehicle-cover :vehicle="$vehicle" />
                                    <div class="min-w-0 flex-1 space-y-1">
                                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                            <h3 class="text-base leading-6 font-semibold text-foreground">
                                                @if ($item['can_open'])
                                                    <a href="{{ route('garage.vehicles.show', $vehicle) }}" class="rounded-sm underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring">{{ trim($vehicle->brand.' '.$vehicle->model) }}</a>
                                                @else
                                                    {{ trim($vehicle->brand.' '.$vehicle->model) }}
                                                @endif
                                            </h3>
                                            <x-ui.badge :variant="$copy['variant']" size="sm">{{ $copy['badge'] }}</x-ui.badge>
                                        </div>
                                        <p class="text-sm text-muted-foreground tabular-nums">{{ $vehicleLine($vehicle) }}</p>
                                        <p class="text-sm text-foreground">{{ $copy['text'] }}</p>
                                        @if (in_array($item['reason'], ['consignment_rejected', 'consignment_missing'], true))
                                            <x-ui.link :href="route('garage.vehicles.create')" class="text-sm" arrow>{{ $item['reason'] === 'consignment_missing' ? 'Enviar procuração' : 'Reenviar procuração' }}</x-ui.link>
                                        @elseif ($item['reason'] === 'without_history' && $item['can_add_maintenance'])
                                            <x-ui.link :href="route('garage.maintenances.create', ['vehicle_id' => $vehicle->id])" icon="plus" class="text-sm">Registrar manutenção<span class="sr-only"> em {{ trim($vehicle->brand.' '.$vehicle->model) }}</span></x-ui.link>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                        @if ($attentionTotal > $attention->count())
                            <p class="mt-2 text-sm text-muted-foreground">Mais {{ $plural($attentionTotal - $attention->count(), 'veículo', 'veículos') }} no estoque.</p>
                        @endif
                    @else
                        <x-ui.empty-state
                            icon="check-circle"
                            title="Tudo em dia"
                            description="Todos os veículos do estoque têm histórico com Selo da oficina e nenhuma procuração está pendente."
                            size="sm"
                        />
                    @endif
                </x-ui.section>
            </div>

            <x-ui.section id="manutencoes-recentes" title="Manutenções recentes do estoque" description="As últimas registradas nos veículos do estoque, com a procedência de cada uma." class="mt-8">
                @if ($recentMaintenances->isNotEmpty())
                    <x-slot:actions>
                        <x-ui.link :href="route('garage.maintenances.index')" arrow>Ver todas</x-ui.link>
                    </x-slot:actions>
                @endif
                <x-maintenance.list
                    :maintenances="$recentMaintenances"
                    :portal="\App\Enums\Portal::Dealer"
                    caption="Manutenções recentes do estoque"
                    :show-filters="false"
                    heading-level="h3"
                    empty-title="Nenhuma manutenção nos veículos do estoque"
                    empty-description="Registre a revisão pré-venda de um veículo para começar o histórico que o comprador vai ver."
                >
                    <x-slot:empty-actions>
                        <x-ui.button variant="secondary" icon="plus" :href="route('garage.maintenances.create')">Registrar manutenção</x-ui.button>
                    </x-slot:empty-actions>
                </x-maintenance.list>
            </x-ui.section>
        @endif
    </x-ui.container>
@endsection
