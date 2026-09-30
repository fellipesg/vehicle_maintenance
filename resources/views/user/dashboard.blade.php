{{--
    Início do proprietário, renderizado no servidor (App\Services\User\OwnerDashboard):

    - Primeiro uso: o card "Primeiros passos" (CRLV-e → primeira manutenção → PDF do histórico) com
      o progresso; os KPIs e as listas só aparecem quando já há veículo.
    - KPIs com totais reais: Veículos, Manutenções ("N com selo · M declaradas") e Próxima
      revisão (a mais próxima entre os veículos, com barra e "Em atraso").
    - Pendências acionáveis: revisão chegando, chassi não informado, capa ausente e garantia
      vencendo em até 30 dias.
    - Meus veículos (miniatura e pontos de procedência) e Últimas manutenções (x-maintenance.list).
--}}
@extends('layouts.app')

@section('title', 'Início')

@php
    use App\Enums\Portal;
    use App\Support\Vehicle\VehicleMaintenanceHistory;

    $ownerFirstName = \Illuminate\Support\Str::of((string) $user->name)->trim()->before(' ')->toString();
    $ownerGreeting = $ownerFirstName !== '' ? 'Olá, '.$ownerFirstName : 'Olá';
    $ownerHasVehicles = $dashboard['vehicle_count'] > 0;
    $ownerFirstSteps = $dashboard['first_steps'];
    $ownerNextRevision = $dashboard['next_revision'];
    $ownerPending = $dashboard['pending'];
    $ownerKm = fn (int $kilometers): string => number_format($kilometers, 0, ',', '.').' km';
    $ownerToneClasses = [
        'danger' => 'bg-danger-soft text-danger',
        'warning' => 'bg-warning-soft text-warning',
        'info' => 'bg-info-soft text-info',
    ];
    $ownerTonePrefix = [
        'danger' => 'Urgente:',
        'warning' => 'Atenção:',
        'info' => 'Sugestão:',
    ];
    $ownerStepsPercent = (int) round($ownerFirstSteps['done'] / max(1, $ownerFirstSteps['total']) * 100);
@endphp

@section('content')
    <x-ui.container padded data-owner-page="dashboard">
        <x-ui.page-header :title="$ownerGreeting" description="Seus veículos, a próxima revisão e o que precisa da sua atenção.">
            <x-slot:actions>
                @if ($ownerHasVehicles)
                    <x-ui.button variant="secondary" icon="wrench-screwdriver" :href="route('user.maintenances.create')">Registrar manutenção</x-ui.button>
                @endif
                <x-ui.button icon="plus" :href="route('user.vehicles.create')">Adicionar veículo</x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        <div class="space-y-6">
            @unless ($ownerFirstSteps['complete'])
                <x-ui.card
                    as="section"
                    id="primeiros-passos"
                    heading-level="h2"
                    title="Primeiros passos"
                    description="Três passos para ter o histórico do seu carro pronto para mostrar na venda ou na oficina."
                    data-first-steps
                >
                    <div class="mb-4 flex items-center gap-3">
                        <div
                            class="h-2 flex-1 overflow-hidden rounded-full bg-surface-muted"
                            role="progressbar"
                            aria-label="Primeiros passos concluídos"
                            aria-valuemin="0"
                            aria-valuemax="{{ $ownerFirstSteps['total'] }}"
                            aria-valuenow="{{ $ownerFirstSteps['done'] }}"
                            aria-valuetext="{{ $ownerFirstSteps['done'] }} de {{ $ownerFirstSteps['total'] }} concluídos"
                        >
                            <div class="h-full rounded-full bg-primary" style="width: {{ $ownerStepsPercent }}%"></div>
                        </div>
                        <p class="shrink-0 text-sm font-medium text-muted-foreground tabular-nums">{{ $ownerFirstSteps['done'] }} de {{ $ownerFirstSteps['total'] }} concluídos</p>
                    </div>

                    <ol role="list" class="space-y-3">
                        @foreach ($ownerFirstSteps['items'] as $step)
                            <li
                                @class([
                                    'flex flex-col gap-3 rounded-control border p-4 sm:flex-row sm:items-center sm:justify-between',
                                    'border-border bg-surface-muted/60' => $step['done'],
                                    'border-border-strong bg-surface' => ! $step['done'],
                                ])
                                data-first-step="{{ $step['key'] }}"
                                data-done="{{ $step['done'] ? 'true' : 'false' }}"
                            >
                                <div class="flex min-w-0 items-start gap-3">
                                    @if ($step['done'])
                                        <span class="inline-flex size-8 shrink-0 items-center justify-center rounded-full bg-success-soft text-success">
                                            <x-ui.icon name="check" class="size-5" />
                                        </span>
                                    @else
                                        <span class="inline-flex size-8 shrink-0 items-center justify-center rounded-full border border-border-strong text-sm font-semibold text-foreground tabular-nums" aria-hidden="true">{{ $loop->iteration }}</span>
                                    @endif
                                    <div class="min-w-0">
                                        <h3 @class(['text-sm font-semibold', 'text-muted-foreground line-through decoration-1' => $step['done'], 'text-foreground' => ! $step['done']])>
                                            <span class="sr-only">Passo {{ $loop->iteration }} de {{ $ownerFirstSteps['total'] }}, {{ $step['done'] ? 'concluído' : 'pendente' }}: </span>{{ $step['title'] }}
                                        </h3>
                                        <p class="mt-0.5 text-sm text-muted-foreground">{{ $step['description'] }}</p>
                                    </div>
                                </div>
                                @if (! $step['done'] && $step['url'] !== null)
                                    <x-ui.button
                                        :variant="$loop->index === $ownerFirstSteps['done'] ? 'primary' : 'secondary'"
                                        size="sm"
                                        :href="$step['url']"
                                        class="shrink-0"
                                    >{{ $step['action'] }}</x-ui.button>
                                @elseif (! $step['done'])
                                    <p class="shrink-0 text-xs text-muted-foreground">Depois de adicionar o veículo</p>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </x-ui.card>
            @endunless

            @if ($ownerHasVehicles)
                <section aria-labelledby="inicio-resumo-titulo">
                    <h2 id="inicio-resumo-titulo" class="sr-only">Resumo</h2>
                    <div class="grid gap-3 sm:grid-cols-3 sm:gap-4">
                        <x-ui.stat
                            label="Veículos"
                            :value="$dashboard['vehicle_count']"
                            icon="truck"
                            :href="route('user.vehicles.index')"
                            data-stat="vehicles"
                        />
                        <x-ui.stat
                            label="Manutenções"
                            :value="number_format($dashboard['maintenance_count'], 0, ',', '.')"
                            :hint="$dashboard['sealed_count'].' com selo · '.VehicleMaintenanceHistory::declaredLabel($dashboard['declared_count'])"
                            icon="wrench-screwdriver"
                            :href="route('user.maintenances.index')"
                            data-stat="maintenances"
                        />
                        @if ($ownerNextRevision !== null)
                            @php
                                $ownerNextVehicleName = trim($ownerNextRevision['vehicle']->brand.' '.$ownerNextRevision['vehicle']->model);
                                $ownerNextText = $ownerNextRevision['is_overdue']
                                    ? $ownerNextVehicleName.': em atraso'
                                    : $ownerNextVehicleName.': faltam '.$ownerKm($ownerNextRevision['kilometers_remaining']);
                            @endphp
                            <x-ui.stat
                                label="Próxima revisão"
                                :value="'Aos '.$ownerKm($ownerNextRevision['next_due_kilometers'])"
                                :hint="$ownerNextText"
                                icon="calendar-days"
                                :href="route('user.vehicles.show', $ownerNextRevision['vehicle'])"
                                data-stat="next-revision"
                            >
                                <div class="flex items-center gap-2">
                                    <div
                                        class="h-2 flex-1 overflow-hidden rounded-full bg-surface-muted"
                                        role="progressbar"
                                        aria-label="Quilometragem até a próxima revisão"
                                        aria-valuemin="0"
                                        aria-valuemax="100"
                                        aria-valuenow="{{ $ownerNextRevision['progress_percent'] }}"
                                        aria-valuetext="{{ $ownerNextText }}"
                                    >
                                        <div @class(['h-full rounded-full', 'bg-danger' => $ownerNextRevision['is_overdue'], 'bg-primary' => ! $ownerNextRevision['is_overdue']]) style="width: {{ $ownerNextRevision['progress_percent'] }}%"></div>
                                    </div>
                                    @if ($ownerNextRevision['is_overdue'])
                                        <x-ui.badge variant="danger" size="sm" dot>Em atraso</x-ui.badge>
                                    @endif
                                </div>
                            </x-ui.stat>
                        @else
                            <x-ui.stat
                                label="Próxima revisão"
                                value="Sem estimativa"
                                hint="Registre uma manutenção com a quilometragem para estimar."
                                icon="calendar-days"
                                data-stat="next-revision"
                            />
                        @endif
                    </div>
                </section>

                <x-ui.card
                    as="section"
                    id="pendencias"
                    heading-level="h2"
                    title="Pendências"
                    :description="$ownerPending === [] ? null : 'O que precisa da sua atenção nos seus veículos.'"
                    data-owner-pending
                >
                    @if ($ownerPending === [])
                        <x-ui.empty-state
                            variant="plain"
                            size="sm"
                            icon="check-circle"
                            title="Tudo em dia"
                            description="Nenhuma revisão chegando, chassi ou capa faltando, nem garantia vencendo nos próximos 30 dias."
                        />
                    @else
                        <ul role="list" class="divide-y divide-border">
                            @foreach ($ownerPending as $pendingItem)
                                <li class="flex flex-col gap-3 py-3 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between" data-pending="{{ $pendingItem['key'] }}">
                                    <div class="flex min-w-0 items-start gap-3">
                                        <span class="inline-flex size-9 shrink-0 items-center justify-center rounded-control {{ $ownerToneClasses[$pendingItem['tone']] ?? $ownerToneClasses['info'] }}">
                                            <x-ui.icon :name="$pendingItem['icon']" />
                                        </span>
                                        <div class="min-w-0">
                                            <p class="font-medium text-foreground"><span class="sr-only">{{ $ownerTonePrefix[$pendingItem['tone']] ?? '' }} </span>{{ $pendingItem['title'] }}</p>
                                            <p class="text-sm text-muted-foreground">{{ $pendingItem['description'] }}</p>
                                        </div>
                                    </div>
                                    <x-ui.button size="sm" variant="secondary" :href="$pendingItem['url']" class="shrink-0">{{ $pendingItem['action'] }}<span class="sr-only">: {{ $pendingItem['title'] }}</span></x-ui.button>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-ui.card>

                <div class="grid gap-6 lg:grid-cols-2">
                    <x-ui.section id="inicio-veiculos" title="Meus veículos">
                        <x-slot:actions>
                            <x-ui.link :href="route('user.vehicles.index')" arrow>Ver todos</x-ui.link>
                        </x-slot:actions>

                        <ul role="list" class="divide-y divide-border overflow-hidden rounded-card border border-border bg-surface" data-owner-vehicles>
                            @foreach ($dashboard['vehicles']->take(\App\Services\User\OwnerDashboard::LIST_LIMIT) as $vehicle)
                                @php
                                    $vehicleRevision = $dashboard['revisions'][$vehicle->id] ?? null;
                                @endphp
                                <li class="relative flex items-start gap-3 p-4 transition-colors duration-fast ease-smooth-out hover:bg-surface-muted/60 motion-reduce:transition-none" data-vehicle-id="{{ $vehicle->id }}">
                                    <x-vehicle-cover :vehicle="$vehicle" />
                                    <div class="min-w-0 flex-1 space-y-1">
                                        <h3 class="font-semibold text-foreground">
                                            <a href="{{ route('user.vehicles.show', $vehicle) }}" class="after:absolute after:inset-0 focus-visible:outline-hidden focus-visible:after:outline-2 focus-visible:after:-outline-offset-2 focus-visible:after:outline-ring">{{ trim($vehicle->brand.' '.$vehicle->model) }}</a>
                                        </h3>
                                        <p class="flex flex-wrap items-center gap-x-2 text-sm text-muted-foreground">
                                            @if (filled($vehicle->license_plate))
                                                <span class="rounded-md border border-border-strong px-1.5 py-px font-mono text-xs font-semibold tracking-wider text-foreground"><span class="sr-only">Placa </span>{{ $vehicle->license_plate }}</span>
                                            @endif
                                            @if ($vehicle->current_kilometers !== null)
                                                <span class="tabular-nums"><span class="sr-only">Quilometragem atual: </span>{{ $ownerKm((int) $vehicle->current_kilometers) }}</span>
                                            @endif
                                        </p>
                                        @if ($vehicle->maintenances_count > 0)
                                            <x-provenance-strip :vehicle="$vehicle" :dot-href="false" class="mb-0" label="Procedência das manutenções deste veículo, da mais antiga à mais recente" />
                                        @else
                                            <p class="text-sm text-muted-foreground">Sem manutenções registradas</p>
                                        @endif
                                        @if (($vehicleRevision['next_due_kilometers'] ?? null) !== null)
                                            <p class="text-xs text-muted-foreground tabular-nums">
                                                @if ($vehicleRevision['is_overdue'])
                                                    <x-ui.badge variant="danger" size="sm" dot>Revisão em atraso</x-ui.badge>
                                                @else
                                                    Próxima revisão aos {{ $ownerKm((int) $vehicleRevision['next_due_kilometers']) }} · faltam {{ $ownerKm((int) $vehicleRevision['kilometers_remaining']) }}
                                                @endif
                                            </p>
                                        @endif
                                    </div>
                                    <x-ui.icon name="chevron-right" class="mt-1 size-5 shrink-0 text-muted-foreground" />
                                </li>
                            @endforeach
                        </ul>
                    </x-ui.section>

                    <x-ui.section id="inicio-manutencoes" title="Últimas manutenções">
                        <x-slot:actions>
                            <x-ui.link :href="route('user.maintenances.index')" arrow>Ver todas</x-ui.link>
                        </x-slot:actions>

                        <x-maintenance.list
                            :maintenances="$dashboard['recent_maintenances']"
                            :portal="Portal::Owner"
                            :show-filters="false"
                            caption="Últimas manutenções"
                            empty-title="Nenhuma manutenção registrada"
                            empty-description="Registre a primeira com a data, a quilometragem e a nota fiscal, se tiver."
                        >
                            <x-slot:empty-actions>
                                <x-ui.button icon="plus" :href="route('user.maintenances.create')">Registrar manutenção</x-ui.button>
                            </x-slot:empty-actions>
                        </x-maintenance.list>
                    </x-ui.section>
                </div>
            @endif
        </div>
    </x-ui.container>
@endsection
