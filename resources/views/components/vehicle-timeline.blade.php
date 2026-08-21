@props(['timeline'])

@php
    $events = $timeline['events'] ?? [];
    $vehicle = $timeline['vehicle'] ?? [];
    $summary = $timeline['summary'] ?? [];
    $reminder = $timeline['reminder'] ?? [];
    $nextDue = $summary['next_due_kilometers'] ?? null;
    $remaining = $summary['kilometers_remaining'] ?? null;
    $progress = $summary['progress_percent'] ?? null;
    $isOverdue = (bool) ($summary['is_overdue'] ?? false);
    $approxAnnualKm = $summary['approximate_annual_kilometers'] ?? null;

    $timelineEvents = array_values(array_filter(
        $events,
        fn (array $event) => ($event['type'] ?? '') !== 'upcoming',
    ));
    $upcomingEvent = collect($events)->first(fn (array $event) => ($event['type'] ?? '') === 'upcoming');
    $displayEvents = $upcomingEvent ? [...$timelineEvents, $upcomingEvent] : $timelineEvents;
    $eventCount = max(count($displayEvents), 1);

    $defaultIndex = 0;
    foreach ($displayEvents as $index => $event) {
        if (($event['is_current'] ?? false) && ($event['type'] ?? '') !== 'upcoming') {
            $defaultIndex = $index;
            break;
        }
    }

    $defaultEvent = $displayEvents[$defaultIndex] ?? null;
    $timelineProgressPercent = $eventCount > 1
        ? min(100, max(0, ($defaultIndex / ($eventCount - 1)) * 100))
        : 0;
@endphp

@if(count($displayEvents) > 0)
    <section {{ $attributes->merge(['class' => 'card !p-0 overflow-hidden']) }} data-vehicle-timeline>
        <div class="border-b border-automotive-200 px-6 py-5">
            <h2 class="text-lg font-medium text-automotive-900">Linha do tempo</h2>
            <p class="mt-0.5 text-sm text-automotive-600">
                {{ $vehicle['brand'] ?? '' }} {{ $vehicle['model'] ?? '' }} ·
                {{ $summary['maintenance_count'] ?? 0 }} manutenção(ões) ·
                R$ {{ number_format((float) ($summary['total_spent'] ?? 0), 2, ',', '.') }} em itens
                @if($approxAnnualKm)
                    · ~{{ number_format((int) $approxAnnualKm, 0, ',', '.') }} km/ano (aprox.)
                @endif
            </p>

            @if($nextDue)
                <div class="mt-5 rounded-xl border border-automotive-200 bg-white px-4 py-3">
                    <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                        <p class="font-medium text-automotive-900">
                            Odômetro atual: {{ number_format((int) ($summary['last_kilometers'] ?? 0), 0, ',', '.') }} km
                        </p>
                        <p class="text-automotive-500">
                            Meta: {{ number_format((int) $nextDue, 0, ',', '.') }} km
                        </p>
                    </div>
                    <div class="mt-3 h-2.5 overflow-hidden rounded-full border border-automotive-200 bg-automotive-100">
                        <div
                            class="h-full rounded-full transition-all duration-300 {{ $isOverdue ? 'bg-red-500' : 'bg-wrench-500' }}"
                            style="width: {{ min(100, max(0, (float) ($progress ?? 0))) }}%"
                            role="progressbar"
                            aria-valuenow="{{ (int) ($progress ?? 0) }}"
                            aria-valuemin="0"
                            aria-valuemax="100"
                        ></div>
                    </div>
                    <p class="mt-2 text-sm {{ $isOverdue ? 'font-medium text-red-600' : 'text-automotive-600' }}">
                        @if($isOverdue)
                            Revisão estimada em {{ number_format((int) $nextDue, 0, ',', '.') }} km — em atraso
                        @else
                            Faltam {{ number_format((int) $remaining, 0, ',', '.') }} km para {{ number_format((int) $nextDue, 0, ',', '.') }} km
                            @if($progress !== null)
                                <span class="text-automotive-400">({{ number_format((float) $progress, 0, ',', '.') }}% do intervalo)</span>
                            @endif
                        @endif
                    </p>
                </div>
            @endif
        </div>

        <div class="overflow-x-auto px-6 py-6">
            <div
                class="relative grid min-w-full gap-2"
                style="grid-template-columns: repeat({{ $eventCount }}, minmax(148px, 1fr));"
                data-timeline-grid
            >
                <div
                    class="pointer-events-none absolute left-[calc(100%/(2*{{ $eventCount }}))] right-[calc(100%/(2*{{ $eventCount }}))] top-8 z-0 h-0.5 bg-automotive-300"
                    aria-hidden="true"
                ></div>
                <div
                    class="pointer-events-none absolute left-[calc(100%/(2*{{ $eventCount }}))] top-8 z-[1] h-0.5 bg-wrench-500 transition-all duration-300"
                    style="width: calc((100% - (100% / {{ $eventCount }})) * {{ $timelineProgressPercent / 100 }});"
                    data-timeline-progress
                    aria-hidden="true"
                ></div>

                @foreach($displayEvents as $index => $event)
                    @php
                        $isUpcoming = ($event['type'] ?? '') === 'upcoming';
                        $isSelected = $index === $defaultIndex;
                        $itemsCount = (int) ($event['items_count'] ?? count($event['items'] ?? []));
                    @endphp

                    <button
                        type="button"
                        class="group relative z-[2] flex cursor-pointer flex-col items-center gap-1.5 px-2 text-center transition {{ $isUpcoming ? 'opacity-60' : '' }}"
                        data-timeline-column
                        data-index="{{ $index }}"
                        aria-pressed="{{ $isSelected ? 'true' : 'false' }}"
                    >
                        <span @class([
                            'text-xs',
                            'text-automotive-500' => ! $isUpcoming,
                            'text-automotive-400' => $isUpcoming,
                        ])>
                            {{ isset($event['kilometers']) ? number_format((int) $event['kilometers'], 0, ',', '.') . ' km' : '—' }}
                        </span>

                        @if($isUpcoming)
                            <span class="box-border h-4 w-4 rounded-full border-2 border-dashed border-automotive-400 bg-white"></span>
                        @elseif($isSelected)
                            <span class="box-border h-5 w-5 rounded-full border-[3px] border-white bg-wrench-500 outline outline-[3px] outline-wrench-500"></span>
                        @else
                            <span class="box-border h-4 w-4 rounded-full border-2 border-automotive-400 bg-white"></span>
                        @endif

                        @if(! empty($event['date']))
                            <span @class([
                                'text-[11px]',
                                'font-medium text-wrench-600' => $isSelected && ! $isUpcoming,
                                'text-automotive-500' => ! $isSelected || $isUpcoming,
                            ]) data-column-date>
                                {{ \Carbon\Carbon::parse($event['date'])->format('d/m/Y') }}
                            </span>
                        @elseif($isUpcoming)
                            <span class="text-[11px] text-automotive-400">Agendado</span>
                        @endif

                        <span @class([
                            'text-sm leading-snug',
                            'font-medium text-automotive-900' => ! $isUpcoming,
                            'font-medium text-automotive-500' => $isUpcoming,
                            'font-semibold' => $isSelected && ! $isUpcoming,
                        ]) data-column-title>
                            {{ $event['label'] ?? 'Evento' }}
                        </span>

                        @if($isUpcoming)
                            <span class="text-xs text-automotive-400">
                                ~{{ number_format((int) ($event['kilometers_remaining'] ?? $remaining ?? 0), 0, ',', '.') }} km restantes
                            </span>
                        @else
                            <span class="text-xs text-automotive-600" data-column-total>
                                R$ {{ number_format((float) ($event['total_amount'] ?? 0), 2, ',', '.') }}
                            </span>
                            <span class="text-[11px] text-automotive-500" data-column-meta>
                                {{ $itemsCount }} {{ $itemsCount === 1 ? 'item' : 'itens' }}
                                @if($event['has_invoice'] ?? false)
                                    · NF
                                @endif
                            </span>
                        @endif
                    </button>
                @endforeach
            </div>
        </div>

        @if($defaultEvent)
            <div class="border-t border-automotive-200 px-6 py-5" data-timeline-detail>
                <p class="text-[11px] font-medium uppercase tracking-wider text-automotive-500">Detalhes</p>
                <h3 class="mt-3 text-lg font-medium text-automotive-900" data-detail-title>
                    {{ $defaultEvent['label'] ?? 'Evento' }}
                </h3>

                <div class="mt-4 grid gap-3 sm:grid-cols-3">
                    <div class="rounded-lg bg-automotive-50 px-3 py-3">
                        <p class="text-[11px] text-automotive-500">Data</p>
                        <p class="mt-1 text-sm font-medium text-automotive-900" data-detail-date>
                            {{ ! empty($defaultEvent['date']) ? \Carbon\Carbon::parse($defaultEvent['date'])->format('d/m/Y') : '—' }}
                        </p>
                    </div>
                    <div class="rounded-lg bg-automotive-50 px-3 py-3">
                        <p class="text-[11px] text-automotive-500">Quilometragem</p>
                        <p class="mt-1 text-sm font-medium text-automotive-900" data-detail-km>
                            {{ isset($defaultEvent['kilometers']) ? number_format((int) $defaultEvent['kilometers'], 0, ',', '.') . ' km' : '—' }}
                        </p>
                    </div>
                    <div class="rounded-lg bg-automotive-50 px-3 py-3">
                        <p class="text-[11px] text-automotive-500">Total em itens</p>
                        <p class="mt-1 text-sm font-medium text-wrench-600" data-detail-total>
                            @if(($defaultEvent['type'] ?? '') === 'upcoming')
                                —
                            @else
                                R$ {{ number_format((float) ($defaultEvent['total_amount'] ?? 0), 2, ',', '.') }}
                            @endif
                        </p>
                    </div>
                </div>

                @if(! empty($defaultEvent['workshop_name']))
                    <p class="mt-3 text-sm text-automotive-600" data-detail-workshop>{{ $defaultEvent['workshop_name'] }}</p>
                @else
                    <p class="mt-3 hidden text-sm text-automotive-600" data-detail-workshop></p>
                @endif

                <div class="mt-4 overflow-hidden rounded-lg" data-detail-items>
                    @forelse($defaultEvent['items'] ?? [] as $itemIndex => $item)
                        @if($itemIndex > 0)
                            <div class="h-px bg-automotive-200" aria-hidden="true"></div>
                        @endif
                        <div class="flex items-start justify-between gap-4 bg-automotive-50 px-3 py-2.5">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-automotive-900">{{ $item['name'] }}</p>
                                <p class="text-xs text-automotive-500">{{ $item['quantity'] }}x</p>
                            </div>
                            <p class="shrink-0 text-sm text-automotive-900">
                                R$ {{ number_format((float) ($item['total_price'] ?? 0), 2, ',', '.') }}
                            </p>
                        </div>
                    @empty
                        <div class="bg-automotive-50 px-3 py-4 text-center text-sm text-automotive-500">
                            {{ ($defaultEvent['type'] ?? '') === 'upcoming'
                                ? 'Marco estimado para a próxima revisão preventiva.'
                                : 'Sem itens registrados.' }}
                        </div>
                    @endforelse
                </div>
            </div>
        @endif
    </section>

    @push('scripts')
        <script>
            (() => {
                const timeline = @json($displayEvents);
                const eventCount = timeline.length;
                const root = document.querySelector('[data-vehicle-timeline]');
                if (!root) return;

                const title = root.querySelector('[data-detail-title]');
                const date = root.querySelector('[data-detail-date]');
                const km = root.querySelector('[data-detail-km]');
                const total = root.querySelector('[data-detail-total]');
                const workshop = root.querySelector('[data-detail-workshop]');
                const items = root.querySelector('[data-detail-items]');
                const timelineProgress = root.querySelector('[data-timeline-progress]');

                const formatMoney = (value) => new Intl.NumberFormat('pt-BR', {
                    style: 'currency',
                    currency: 'BRL',
                }).format(value || 0);

                const formatKm = (value) => value == null
                    ? '—'
                    : `${new Intl.NumberFormat('pt-BR').format(value)} km`;

                const formatDate = (value) => {
                    if (!value) return '—';
                    const [year, month, day] = value.split('-');
                    return `${day}/${month}/${year}`;
                };

                const updateTimelineProgress = (selectedIndex) => {
                    if (!timelineProgress || eventCount <= 1) return;
                    const percent = (selectedIndex / (eventCount - 1)) * 100;
                    timelineProgress.style.width = `calc((100% - (100% / ${eventCount})) * ${percent / 100})`;
                };

                const setSelectedStyles = (selectedIndex) => {
                    root.querySelectorAll('[data-timeline-column]').forEach((column) => {
                        const index = Number(column.dataset.index);
                        const isSelected = index === selectedIndex;
                        const isUpcoming = (timeline[index]?.type ?? '') === 'upcoming';
                        const columnDate = column.querySelector('[data-column-date]');
                        const columnTitle = column.querySelector('[data-column-title]');

                        column.setAttribute('aria-pressed', isSelected ? 'true' : 'false');

                        const dot = column.querySelector('span.rounded-full');
                        if (dot && ! isUpcoming) {
                            dot.className = isSelected
                                ? 'box-border h-5 w-5 rounded-full border-[3px] border-white bg-wrench-500 outline outline-[3px] outline-wrench-500'
                                : 'box-border h-4 w-4 rounded-full border-2 border-automotive-400 bg-white';
                        }

                        if (columnDate && ! isUpcoming) {
                            columnDate.classList.toggle('font-medium', isSelected);
                            columnDate.classList.toggle('text-wrench-600', isSelected);
                            columnDate.classList.toggle('text-automotive-500', ! isSelected);
                        }

                        if (columnTitle && ! isUpcoming) {
                            columnTitle.classList.toggle('font-semibold', isSelected);
                            columnTitle.classList.toggle('font-medium', ! isSelected);
                        }
                    });

                    updateTimelineProgress(selectedIndex);
                };

                const renderItems = (event) => {
                    if ((event.items || []).length) {
                        items.innerHTML = event.items.map((item, itemIndex) => {
                            const divider = itemIndex > 0
                                ? '<div class="h-px bg-automotive-200" aria-hidden="true"></div>'
                                : '';

                            return `${divider}
                                <div class="flex items-start justify-between gap-4 bg-automotive-50 px-3 py-2.5">
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-automotive-900">${item.name}</p>
                                        <p class="text-xs text-automotive-500">${item.quantity}x</p>
                                    </div>
                                    <p class="shrink-0 text-sm text-automotive-900">${formatMoney(item.total_price)}</p>
                                </div>`;
                        }).join('');

                        return;
                    }

                    items.innerHTML = event.type === 'upcoming'
                        ? '<div class="bg-automotive-50 px-3 py-4 text-center text-sm text-automotive-500">Marco estimado para a próxima revisão preventiva.</div>'
                        : '<div class="bg-automotive-50 px-3 py-4 text-center text-sm text-automotive-500">Sem itens registrados.</div>';
                };

                root.querySelectorAll('[data-timeline-column]').forEach((column) => {
                    column.addEventListener('click', () => {
                        const event = timeline[Number(column.dataset.index)];
                        if (! event) return;

                        setSelectedStyles(Number(column.dataset.index));

                        title.textContent = event.label || 'Evento';
                        date.textContent = formatDate(event.date);
                        km.textContent = formatKm(event.kilometers);
                        total.textContent = event.type === 'upcoming' ? '—' : formatMoney(event.total_amount);

                        if (event.workshop_name) {
                            workshop.textContent = event.workshop_name;
                            workshop.classList.remove('hidden');
                        } else {
                            workshop.textContent = '';
                            workshop.classList.add('hidden');
                        }

                        renderItems(event);
                    });
                });
            })();
        </script>
    @endpush
@endif
