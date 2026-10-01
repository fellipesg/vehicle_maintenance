{{--
    Linha do tempo do veículo, renderizada no servidor a partir de
    App\Services\Vehicle\VehicleTimelineBuilder::build(): um evento por coluna, da mais antiga para a
    mais recente (km crescente), e a próxima revisão estimada no fim.

    As colunas são abas (WAI-ARIA Tabs) e cada evento tem o próprio painel de detalhes, já no HTML:
    resources/js/ui/tabs.js (iniciado pelo app.js) troca a seleção no clique e nas setas, Home e End,
    sem montar HTML no navegador. resources/js/vehicle-timeline-portal.js (initVehicleTimelines) só
    acrescenta o ajuste fino do trilho, o esmaecido nas bordas quando há rolagem e a coluna
    selecionada centralizada.

    Props:
    - timeline (obrigatório): o array do VehicleTimelineBuilder.
    - maintenance-url: link "Ver manutenção completa" no painel de cada manutenção. Closure(int $id)
      ou padrão com "{id}" ("/usuario/manutencoes/{id}"); null não mostra o link.
    - filter: procedência filtrada agora ('1' Selo da oficina, '0' Declaradas, '' todas). As colunas
      de manutenção que não entram no filtro saem (hidden + disabled) e o trilho de progresso some.
    - summary: cabeçalho com "N manutenções · R$ em itens" e a barra até a próxima revisão (padrão:
      sim). A ficha (x-vehicle.detail) já mostra isso em x-ui.stat e passa :summary="false".
    - title / heading-level: título do card ("Linha do tempo") e o nível, h2 (o padrão) ou h3; o
      título do painel usa o nível seguinte.
    - id-prefix: prefixo dos ids das abas e painéis (padrão "linha-do-tempo").

    Slot footer: abaixo da linha do tempo, no mesmo card (os pontos de procedência e o filtro
    Todas / Selo da oficina / Declaradas, como no app: .ai/rules/provenance.md).

    Ex.: <x-vehicle-timeline :timeline="$timeline" maintenance-url="/usuario/manutencoes/{id}" />
--}}
@props([
    'timeline',
    'maintenanceUrl' => null,
    'filter' => '',
    'summary' => true,
    'title' => 'Linha do tempo',
    'headingLevel' => 'h2',
    'idPrefix' => 'linha-do-tempo',
])

@php
    $timelineHeadingTag = \App\Support\UiProps::oneOf('x-vehicle-timeline', 'heading-level', $headingLevel, ['h2', 'h3'], 'h2');
    $timelinePanelHeadingTag = $timelineHeadingTag === 'h2' ? 'h3' : 'h4';
    $timelineFilter = \App\Support\Vehicle\VehicleMaintenanceHistory::normalizeFilter($filter);
    $timelineIsFiltered = $timelineFilter !== '';

    $events = $timeline['events'] ?? [];
    $timelineVehicle = $timeline['vehicle'] ?? [];
    $timelineSummary = $timeline['summary'] ?? [];
    $nextDue = $timelineSummary['next_due_kilometers'] ?? null;
    $remaining = $timelineSummary['kilometers_remaining'] ?? null;
    $progress = $timelineSummary['progress_percent'] ?? null;
    $odometerProgress = $timelineSummary['odometer_progress_percent'] ?? $progress;
    $isOverdue = (bool) ($timelineSummary['is_overdue'] ?? false);
    $approxAnnualKm = $timelineSummary['approximate_annual_kilometers'] ?? null;
    $maintenanceCount = (int) ($timelineSummary['maintenance_count'] ?? 0);

    $pastEvents = array_values(array_filter($events, fn (array $event) => ($event['type'] ?? '') !== 'upcoming'));
    $upcomingEvent = collect($events)->first(fn (array $event) => ($event['type'] ?? '') === 'upcoming');
    $displayEvents = $upcomingEvent ? [...$pastEvents, $upcomingEvent] : $pastEvents;

    $eventIsVisible = fn (array $event): bool => ($event['type'] ?? '') !== 'maintenance'
        || \App\Support\Vehicle\VehicleMaintenanceHistory::matchesFilter((bool) ($event['is_verified'] ?? false), $timelineFilter);
    $visibleIndexes = array_keys(array_filter($displayEvents, $eventIsVisible));
    $visibleCount = max(count($visibleIndexes), 1);

    $selectedIndex = null;
    foreach ($displayEvents as $eventIndex => $event) {
        if (($event['is_current'] ?? false) && ($event['type'] ?? '') !== 'upcoming' && in_array($eventIndex, $visibleIndexes, true)) {
            $selectedIndex = $eventIndex;
        }
    }
    if ($selectedIndex === null) {
        $visiblePast = array_values(array_filter($visibleIndexes, fn (int $eventIndex): bool => ($displayEvents[$eventIndex]['type'] ?? '') !== 'upcoming'));
        $selectedIndex = $visiblePast !== [] ? end($visiblePast) : ($visibleIndexes[0] ?? 0);
    }

    $trackCurrentIndex = (int) ($timelineSummary['track_current_index'] ?? $selectedIndex);
    $trackPercent = (float) ($timelineSummary['track_progress_percent'] ?? 0);

    $formatKm = fn (mixed $value): string => $value === null ? '—' : number_format((int) $value, 0, ',', '.').' km';
    $formatMoney = fn (mixed $value): string => 'R$ '.number_format((float) ($value ?? 0), 2, ',', '.');
    $formatDate = fn (?string $value): ?string => filled($value) ? \Carbon\Carbon::parse($value)->format('d/m/Y') : null;
    $eventUrl = function (array $event) use ($maintenanceUrl): ?string {
        if (($event['type'] ?? '') !== 'maintenance' || ! isset($event['id'])) {
            return null;
        }

        $url = match (true) {
            $maintenanceUrl instanceof \Closure => $maintenanceUrl((int) $event['id']),
            is_string($maintenanceUrl) && $maintenanceUrl !== '' => str_replace('{id}', (string) $event['id'], $maintenanceUrl),
            default => null,
        };

        return is_string($url) && $url !== '' ? $url : null;
    };

    $headerParts = ['Da mais antiga para a mais recente'];
    if ($summary) {
        $headerParts[] = \App\Support\Vehicle\VehicleMaintenanceHistory::countLabel($maintenanceCount);
        $headerParts[] = $formatMoney($timelineSummary['total_spent'] ?? 0).' em itens';
        if ($approxAnnualKm) {
            $headerParts[] = '~'.$formatKm($approxAnnualKm).'/ano (aprox.)';
        }
    }
    $odometerValue = (int) min(100, max(0, (float) ($odometerProgress ?? 0)));
    $odometerText = $isOverdue
        ? 'Revisão estimada em '.$formatKm($nextDue).', em atraso'
        : 'Faltam '.$formatKm($remaining).' para '.$formatKm($nextDue);
@endphp

@if (count($displayEvents) > 0)
    <section {{ $attributes->class(['overflow-hidden rounded-card border border-border bg-surface shadow-sm'])->merge(['aria-labelledby' => $idPrefix.'-titulo', 'data-vehicle-timeline' => '']) }}>
        <div class="border-b border-border px-4 py-5 sm:px-6">
            <{{ $timelineHeadingTag }} id="{{ $idPrefix }}-titulo" class="text-lg font-semibold text-foreground">{{ $title }}</{{ $timelineHeadingTag }}>
            <p class="mt-0.5 text-sm text-muted-foreground">{{ implode(' · ', $headerParts) }}</p>

            @if ($summary && $nextDue)
                <div class="mt-5 rounded-control border border-border bg-surface px-4 py-3">
                    <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                        <p class="font-medium text-foreground">Odômetro atual: <span class="tabular-nums">{{ $formatKm($timelineSummary['last_kilometers'] ?? 0) }}</span></p>
                        <p class="text-muted-foreground">Meta: <span class="tabular-nums">{{ $formatKm($nextDue) }}</span></p>
                    </div>
                    <div
                        class="mt-3 h-2.5 overflow-hidden rounded-full border border-border bg-surface-muted"
                        role="progressbar"
                        aria-label="Quilometragem até a próxima revisão"
                        aria-valuemin="0"
                        aria-valuemax="100"
                        aria-valuenow="{{ $odometerValue }}"
                        aria-valuetext="{{ $odometerText }}"
                    >
                        <div @class(['h-full rounded-full', 'bg-danger' => $isOverdue, 'bg-primary' => ! $isOverdue]) style="width: {{ $odometerValue }}%"></div>
                    </div>
                    <p @class(['mt-2 text-sm', 'font-medium text-danger' => $isOverdue, 'text-muted-foreground' => ! $isOverdue])>
                        {{ $odometerText }}
                        @if (! $isOverdue && $progress !== null)
                            <span class="text-muted-foreground">({{ number_format((float) $progress, 0, ',', '.') }}% do intervalo)</span>
                        @endif
                    </p>
                </div>
            @endif
        </div>

        <div data-ui-tabs data-timeline-tabs>
            <div class="relative overflow-x-auto px-4 py-4 sm:px-6 data-[overflow=true]:[mask-image:linear-gradient(to_right,transparent,black_1.5rem,black_calc(100%-1.5rem),transparent)]" data-timeline-scroller>
                <div
                    id="{{ $idPrefix }}-colunas"
                    role="tablist"
                    aria-label="Eventos da linha do tempo, do mais antigo ao mais recente"
                    aria-orientation="horizontal"
                    class="--prevent-on-load-init relative grid min-w-full auto-cols-[minmax(9.5rem,1fr)] grid-flow-col gap-2"
                    data-timeline-grid
                    data-visible-count="{{ $visibleCount }}"
                >
                    <div
                        class="pointer-events-none absolute top-[3.375rem] z-0 h-0.5 bg-border-strong"
                        style="left: calc(100% / (2 * {{ $visibleCount }})); right: calc(100% / (2 * {{ $visibleCount }}));"
                        aria-hidden="true"
                        data-timeline-rail
                    ></div>
                    <div
                        class="pointer-events-none absolute top-[3.375rem] z-[1] h-0.5 bg-primary"
                        style="left: calc(100% / (2 * {{ $visibleCount }})); width: calc((100% - (100% / {{ $visibleCount }})) * {{ $trackPercent / 100 }});"
                        aria-hidden="true"
                        data-timeline-progress
                        data-track-index="{{ $trackCurrentIndex }}"
                        data-track-percent="{{ $trackPercent }}"
                        @if ($timelineIsFiltered) hidden @endif
                    ></div>

                    @foreach ($displayEvents as $eventIndex => $event)
                        @php
                            $eventType = (string) ($event['type'] ?? '');
                            $isUpcoming = $eventType === 'upcoming';
                            $isMaintenance = $eventType === 'maintenance';
                            $isSelected = $eventIndex === $selectedIndex;
                            $isVisible = in_array($eventIndex, $visibleIndexes, true);
                            $isReached = ! $isUpcoming && $eventIndex <= $trackCurrentIndex;
                            $eventVerified = (bool) ($event['is_verified'] ?? false);
                            $itemsCount = (int) ($event['items_count'] ?? count($event['items'] ?? []));
                            $panelId = $idPrefix.'-evento-'.$eventIndex;
                            $eventDate = $formatDate($event['date'] ?? null);
                        @endphp
                        <button
                            type="button"
                            role="tab"
                            id="{{ $panelId }}-aba"
                            aria-controls="{{ $panelId }}"
                            aria-selected="{{ $isSelected ? 'true' : 'false' }}"
                            tabindex="{{ $isSelected ? '0' : '-1' }}"
                            data-ui-tab="{{ $panelId }}"
                            data-timeline-column
                            data-index="{{ $eventIndex }}"
                            data-event-type="{{ $eventType }}"
                            @if ($isMaintenance) data-verified="{{ $eventVerified ? '1' : '0' }}" @endif
                            @unless ($isVisible) hidden disabled @endunless
                            class="group relative z-[2] flex min-w-0 cursor-pointer flex-col items-center gap-1.5 rounded-control px-2 pt-2 pb-3 text-center transition-colors duration-fast ease-smooth-out hover:bg-surface-muted/60 aria-selected:bg-accent/70 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring motion-reduce:transition-none"
                        >
                            <span class="h-4 text-xs leading-4 text-muted-foreground tabular-nums">{{ isset($event['kilometers']) ? $formatKm($event['kilometers']) : '—' }}</span>
                            <span class="flex h-12 items-center justify-center" data-timeline-marker>
                                @if ($isMaintenance)
                                    <span class="inline-flex rounded-full ring-offset-2 ring-offset-surface group-aria-selected:ring-2 group-aria-selected:ring-ring">
                                        <x-provenance-marker :event="$event" size="lg" />
                                    </span>
                                    <span class="sr-only">{{ $eventVerified ? 'Selo da oficina' : 'Declarada' }}</span>
                                @elseif ($isUpcoming)
                                    <span class="box-border size-4 rounded-full border-2 border-dashed border-input bg-surface" aria-hidden="true"></span>
                                @else
                                    <span @class([
                                        'box-border size-4 rounded-full border-2 group-aria-selected:size-5 group-aria-selected:ring-2 group-aria-selected:ring-ring group-aria-selected:ring-offset-2',
                                        'border-primary bg-primary' => $isReached,
                                        'border-input bg-surface' => ! $isReached,
                                    ]) aria-hidden="true"></span>
                                @endif
                            </span>
                            <span class="text-xs text-muted-foreground tabular-nums group-aria-selected:font-medium group-aria-selected:text-link" data-column-date>{{ $eventDate ?? ($isUpcoming ? 'Estimada' : '—') }}</span>
                            <span @class([
                                'text-sm leading-snug group-aria-selected:font-semibold',
                                'font-medium text-foreground' => ! $isUpcoming,
                                'font-medium text-muted-foreground' => $isUpcoming,
                            ]) data-column-title>{{ $event['label'] ?? 'Evento' }}</span>
                            @if ($isUpcoming)
                                <span class="text-xs text-muted-foreground tabular-nums">~{{ $formatKm($event['kilometers_remaining'] ?? $remaining ?? 0) }} restantes</span>
                            @elseif ($isMaintenance)
                                <span class="text-xs text-muted-foreground tabular-nums" data-column-total>{{ $formatMoney($event['total_amount'] ?? 0) }}</span>
                                <span class="text-xs text-muted-foreground" data-column-meta>{{ $itemsCount }} {{ $itemsCount === 1 ? 'item' : 'itens' }}@if ($event['has_invoice'] ?? false) · NF-e @endif</span>
                            @endif
                        </button>
                    @endforeach
                </div>
            </div>
            @if (count($visibleIndexes) > 2)
                <p class="px-4 pb-3 text-xs text-muted-foreground sm:hidden">Deslize para o lado para ver todos os eventos.</p>
            @endif

            @foreach ($displayEvents as $eventIndex => $event)
                @php
                    $eventType = (string) ($event['type'] ?? '');
                    $isUpcoming = $eventType === 'upcoming';
                    $isMaintenance = $eventType === 'maintenance';
                    $eventVerified = (bool) ($event['is_verified'] ?? false);
                    $panelId = $idPrefix.'-evento-'.$eventIndex;
                    $eventDate = $formatDate($event['date'] ?? null);
                    $eventItems = $event['items'] ?? [];
                    $generalWarranty = $event['general_warranty'] ?? null;
                    $detailUrl = $eventUrl($event);
                @endphp
                <div
                    id="{{ $panelId }}"
                    role="tabpanel"
                    aria-labelledby="{{ $panelId }}-aba"
                    tabindex="0"
                    data-ui-tab-panel
                    data-timeline-panel
                    @unless ($eventIndex === $selectedIndex) hidden @endunless
                    class="border-t border-border px-4 py-5 sm:px-6 focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-ring"
                >
                    <p class="text-xs font-medium tracking-wider text-muted-foreground uppercase">Detalhes</p>
                    <{{ $timelinePanelHeadingTag }} class="mt-2 text-lg font-semibold text-foreground" data-detail-title>{{ $event['label'] ?? 'Evento' }}</{{ $timelinePanelHeadingTag }}>

                    @if ($isMaintenance)
                        <p class="mt-2 flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                            <x-ui.badge :variant="$eventVerified ? 'seal' : 'declared'">{{ $eventVerified ? 'Selo da oficina' : ($event['provenance_label'] ?? 'Declarada') }}</x-ui.badge>
                            @if (filled($event['workshop_name'] ?? null))
                                <span data-detail-workshop>{{ $event['workshop_name'] }}</span>
                            @endif
                        </p>
                    @endif

                    <dl class="mt-4 grid gap-3 sm:grid-cols-3">
                        <div class="rounded-control bg-surface-muted px-3 py-3">
                            <dt class="text-xs text-muted-foreground">Data</dt>
                            <dd class="mt-1 text-sm font-medium text-foreground tabular-nums" data-detail-date>{{ $eventDate ?? ($isUpcoming ? 'Estimada' : '—') }}</dd>
                        </div>
                        <div class="rounded-control bg-surface-muted px-3 py-3">
                            <dt class="text-xs text-muted-foreground">Quilometragem</dt>
                            <dd class="mt-1 text-sm font-medium text-foreground tabular-nums" data-detail-km>{{ isset($event['kilometers']) ? $formatKm($event['kilometers']) : '—' }}</dd>
                        </div>
                        <div class="rounded-control bg-surface-muted px-3 py-3">
                            <dt class="text-xs text-muted-foreground">Total em itens</dt>
                            <dd class="mt-1 text-sm font-medium text-foreground tabular-nums" data-detail-total>{{ $isUpcoming ? '—' : $formatMoney($event['total_amount'] ?? 0) }}</dd>
                        </div>
                    </dl>

                    @if ($isUpcoming && filled($event['description'] ?? null))
                        <p class="mt-4 text-sm text-muted-foreground">{{ $event['description'] }}</p>
                    @endif

                    @if (is_array($generalWarranty))
                        <p class="mt-4 flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                            <span class="font-medium text-foreground">Garantia do serviço</span>
                            <x-ui.badge :variant="($generalWarranty['is_vigente'] ?? false) ? 'success' : 'neutral'" dot>{{ $generalWarranty['label'] ?? '' }}</x-ui.badge>
                        </p>
                    @endif

                    @if ($eventItems !== [])
                        <ul role="list" class="mt-4 divide-y divide-border overflow-hidden rounded-control border border-border" aria-label="Itens de {{ $event['label'] ?? 'evento' }}" data-detail-items>
                            @foreach ($eventItems as $item)
                                <li class="flex items-start justify-between gap-4 bg-surface px-3 py-2.5">
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-foreground">{{ $item['name'] ?? '' }}</p>
                                        <p class="text-xs text-muted-foreground tabular-nums">{{ $item['quantity'] ?? 1 }}x</p>
                                        @if (($item['has_warranty'] ?? false) && ! empty($item['warranty_starts_at']) && ! empty($item['warranty_ends_at']))
                                            <p class="mt-1 flex flex-wrap items-center gap-2">
                                                <x-ui.badge size="sm" :variant="($item['is_under_warranty'] ?? false) ? 'success' : 'neutral'" dot>{{ ($item['is_under_warranty'] ?? false) ? 'Em garantia' : 'Garantia encerrada' }}</x-ui.badge>
                                                <span class="text-xs text-muted-foreground tabular-nums">{{ $formatDate($item['warranty_starts_at']) }} a {{ $formatDate($item['warranty_ends_at']) }}</span>
                                            </p>
                                        @endif
                                    </div>
                                    <p class="shrink-0 text-sm text-foreground tabular-nums">{{ $formatMoney($item['total_price'] ?? 0) }}</p>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="mt-4 rounded-control bg-surface-muted px-3 py-4 text-center text-sm text-muted-foreground" data-detail-items>
                            {{ $isUpcoming ? 'Marco estimado para a próxima revisão preventiva.' : ($isMaintenance ? 'Sem itens registrados.' : 'Quilometragem informada no cadastro do veículo.') }}
                        </p>
                    @endif

                    @if ($detailUrl)
                        <p class="mt-4">
                            <x-ui.link :href="$detailUrl" arrow>Ver manutenção completa</x-ui.link>
                        </p>
                    @endif
                </div>
            @endforeach
        </div>

        @isset($footer)
            @if ($footer->hasActualContent())
                <div {{ $footer->attributes->class(['border-t border-border px-4 py-4 sm:px-6'])->merge(['data-timeline-footer' => '']) }}>{{ $footer }}</div>
            @endif
        @endisset
    </section>
@endif
