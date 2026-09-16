import { renderProvenanceMarker } from './provenance-ui';

function formatTimelineDate(value) {
    if (!value) {
        return '—';
    }

    const [year, month, day] = value.split('-');

    return `${day}/${month}/${year}`;
}

function formatTimelineMoney(value) {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(value || 0);
}

function formatTimelineKm(value) {
    if (value == null) {
        return '—';
    }

    return `${new Intl.NumberFormat('pt-BR').format(value)} km`;
}

function maintenanceMarkerPayload(event) {
    return {
        is_verified: event.is_verified === true,
        verified_at: event.is_verified ? event.date : null,
        verified_workshop: event.workshop_logo_url
            ? { logo_url: event.workshop_logo_url, name: event.workshop_name }
            : null,
        workshop_name: event.workshop_name,
        provenance_label: event.provenance_label,
        registered_by_type: event.registered_by_type,
    };
}

function timelineMarkerHtml(event, { selected = false } = {}) {
    if (event.type === 'maintenance') {
        const marker = renderProvenanceMarker(maintenanceMarkerPayload(event));
        const ring = selected ? ' ring-2 ring-wrench-500 ring-offset-2' : '';

        return `<span class="inline-flex shrink-0${ring}" data-timeline-marker>${marker}</span>`;
    }

    if (event.type === 'upcoming') {
        return '<span class="box-border h-4 w-4 rounded-full border-2 border-dashed border-automotive-400 bg-white" data-timeline-marker></span>';
    }

    const isReached = event.is_current;
    if (selected) {
        return '<span class="box-border h-5 w-5 rounded-full border-[3px] border-white bg-wrench-500 outline outline-[3px] outline-wrench-500" data-timeline-marker></span>';
    }

    if (isReached) {
        return '<span class="box-border h-4 w-4 rounded-full border-2 border-wrench-500 bg-wrench-500" data-timeline-marker></span>';
    }

    return '<span class="box-border h-4 w-4 rounded-full border-2 border-automotive-400 bg-white" data-timeline-marker></span>';
}

function warrantyItemHtml(item) {
    if (!item?.has_warranty || !item.warranty_starts_at || !item.warranty_ends_at) {
        return '';
    }

    const badgeClass = item.is_under_warranty ? 'badge-green' : 'badge-orange';
    const label = item.is_under_warranty ? 'Em garantia' : 'Garantia encerrada';

    return `
        <div class="mt-1 flex flex-wrap items-center gap-2">
            <span class="badge ${badgeClass}">${label}</span>
            <span class="text-xs text-automotive-600">
                ${formatTimelineDate(item.warranty_starts_at)} — ${formatTimelineDate(item.warranty_ends_at)}
            </span>
        </div>
    `;
}

export function positionTimelineProgress(root, percent) {
    const grid = root.querySelector('[data-timeline-grid]');
    const progress = root.querySelector('[data-timeline-progress]');

    if (!grid || !progress) {
        return;
    }

    const columns = root.querySelectorAll('[data-timeline-column]');

    if (columns.length < 2) {
        return;
    }

    const ratio = Math.min(1, Math.max(0, Number(percent ?? progress.dataset.trackPercent ?? 0) / 100));
    const first = columns[0];
    const last = columns[columns.length - 1];
    const firstCenter = first.offsetLeft + (first.offsetWidth / 2);
    const lastCenter = last.offsetLeft + (last.offsetWidth / 2);

    progress.style.left = `${firstCenter}px`;
    progress.style.width = `${Math.max(0, lastCenter - firstCenter) * ratio}px`;
    progress.style.right = 'auto';
}

function renderTimelineItems(event) {
    if ((event.items || []).length) {
        return event.items.map((item, itemIndex) => {
            const divider = itemIndex > 0
                ? '<div class="h-px bg-automotive-200" aria-hidden="true"></div>'
                : '';

            return `${divider}
                <div class="flex items-start justify-between gap-4 bg-automotive-50 px-3 py-2.5">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-automotive-900">${item.name}</p>
                        <p class="text-xs text-automotive-500">${item.quantity}x</p>
                        ${warrantyItemHtml(item)}
                    </div>
                    <p class="shrink-0 text-sm text-automotive-900">${formatTimelineMoney(item.total_price)}</p>
                </div>`;
        }).join('');
    }

    return event.type === 'upcoming'
        ? '<div class="bg-automotive-50 px-3 py-4 text-center text-sm text-automotive-500">Marco estimado para a próxima revisão preventiva.</div>'
        : '<div class="bg-automotive-50 px-3 py-4 text-center text-sm text-automotive-500">Sem itens registrados.</div>';
}

export function renderVehicleTimeline(timeline) {
    const events = timeline?.events ?? [];
    const vehicle = timeline?.vehicle ?? {};
    const summary = timeline?.summary ?? {};

    const timelineEvents = events.filter((event) => event.type !== 'upcoming');
    const upcomingEvent = events.find((event) => event.type === 'upcoming');
    const displayEvents = upcomingEvent ? [...timelineEvents, upcomingEvent] : timelineEvents;

    if (displayEvents.length === 0) {
        return '';
    }

    const eventCount = displayEvents.length;
    let defaultIndex = 0;

    displayEvents.forEach((event, index) => {
        if (event.is_current && event.type !== 'upcoming') {
            defaultIndex = index;
        }
    });

    const defaultEvent = displayEvents[defaultIndex];
    const trackCurrentIndex = summary.track_current_index ?? defaultIndex;
    const timelineProgressPercent = summary.track_progress_percent
        ?? (eventCount > 1 ? (trackCurrentIndex / (eventCount - 1)) * 100 : 0);

    const nextDue = summary.next_due_kilometers;
    const remaining = summary.kilometers_remaining;
    const progress = summary.odometer_progress_percent ?? summary.progress_percent;
    const isOverdue = summary.is_overdue === true;
    const approxAnnualKm = summary.approximate_annual_kilometers;

    const columnsHtml = displayEvents.map((event, index) => {
        const isUpcoming = event.type === 'upcoming';
        const isSelected = index === defaultIndex;
        const isReached = !isUpcoming && index <= trackCurrentIndex;
        const itemsCount = event.items_count ?? (event.items?.length ?? 0);

        return `
            <button
                type="button"
                class="group relative z-[2] flex cursor-pointer flex-col items-center gap-1.5 px-2 text-center transition ${isUpcoming ? 'opacity-60' : ''}"
                data-timeline-column
                data-index="${index}"
                aria-pressed="${isSelected ? 'true' : 'false'}"
            >
                <span class="text-xs ${isUpcoming ? 'text-automotive-400' : 'text-automotive-500'}">
                    ${event.kilometers != null ? formatTimelineKm(event.kilometers) : '—'}
                </span>
                ${timelineMarkerHtml(event, { selected: isSelected && !isUpcoming })}
                ${event.date
                    ? `<span class="text-[11px] ${isSelected && !isUpcoming ? 'font-medium text-wrench-600' : 'text-automotive-500'}" data-column-date>${formatTimelineDate(event.date)}</span>`
                    : (isUpcoming ? '<span class="text-[11px] text-automotive-400">Agendado</span>' : '')}
                <span class="text-sm leading-snug ${isUpcoming ? 'font-medium text-automotive-500' : 'font-medium text-automotive-900'} ${isSelected && !isUpcoming ? 'font-semibold' : ''}" data-column-title>
                    ${event.label ?? 'Evento'}
                </span>
                ${isUpcoming
                    ? `<span class="text-xs text-automotive-400">~${formatTimelineKm(event.kilometers_remaining ?? remaining ?? 0)} restantes</span>`
                    : `<span class="text-xs text-automotive-600" data-column-total>${formatTimelineMoney(event.total_amount)}</span>
                       <span class="text-[11px] text-automotive-500" data-column-meta>
                           ${itemsCount} ${itemsCount === 1 ? 'item' : 'itens'}${event.has_invoice ? ' · NF' : ''}
                       </span>`}
            </button>
        `;
    }).join('');

    const progressHtml = nextDue ? `
        <div class="mt-5 rounded-xl border border-automotive-200 bg-white px-4 py-3">
            <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                <p class="font-medium text-automotive-900">Odômetro atual: ${formatTimelineKm(summary.last_kilometers)}</p>
                <p class="text-automotive-500">Meta: ${formatTimelineKm(nextDue)}</p>
            </div>
            <div class="mt-3 h-2.5 overflow-hidden rounded-full border border-automotive-200 bg-automotive-100">
                <div class="h-full rounded-full transition-all duration-300 ${isOverdue ? 'bg-red-500' : 'bg-wrench-500'}" style="width: ${Math.min(100, Math.max(0, progress ?? 0))}%"></div>
            </div>
            <p class="mt-2 text-sm ${isOverdue ? 'font-medium text-red-600' : 'text-automotive-600'}">
                ${isOverdue
                    ? `Revisão estimada em ${formatTimelineKm(nextDue)} — em atraso`
                    : `Faltam ${formatTimelineKm(remaining)} para ${formatTimelineKm(nextDue)}`}
            </p>
        </div>
    ` : '';

    return `
        <section class="card !p-0 overflow-hidden mb-8" data-vehicle-timeline>
            <div class="border-b border-automotive-200 px-6 py-5">
                <h2 class="text-lg font-medium text-automotive-900">Linha do tempo</h2>
                <p class="mt-0.5 text-sm text-automotive-600">
                    ${vehicle.brand ?? ''} ${vehicle.model ?? ''} ·
                    ${summary.maintenance_count ?? 0} manutenção(ões) ·
                    ${formatTimelineMoney(summary.total_spent)} em itens
                    ${approxAnnualKm ? ` · ~${formatTimelineKm(approxAnnualKm)}/ano (aprox.)` : ''}
                </p>
                ${progressHtml}
            </div>
            <div class="overflow-x-auto px-6 py-6">
                <div class="relative grid min-w-full gap-2" style="grid-template-columns: repeat(${eventCount}, minmax(148px, 1fr));" data-timeline-grid>
                    <div class="pointer-events-none absolute left-[calc(100%/(2*${eventCount}))] right-[calc(100%/(2*${eventCount}))] top-8 z-0 h-0.5 bg-automotive-300" aria-hidden="true"></div>
                    <div class="pointer-events-none absolute top-8 z-[1] h-0.5 bg-wrench-500 transition-all duration-300" style="left: calc(100% / (2 * ${eventCount})); width: calc((100% - (100% / ${eventCount})) * ${timelineProgressPercent / 100});" data-timeline-progress data-track-index="${trackCurrentIndex}" data-track-percent="${timelineProgressPercent}" aria-hidden="true"></div>
                    ${columnsHtml}
                </div>
            </div>
            <div class="border-t border-automotive-200 px-6 py-5" data-timeline-detail>
                <p class="text-[11px] font-medium uppercase tracking-wider text-automotive-500">Detalhes</p>
                <h3 class="mt-3 text-lg font-medium text-automotive-900" data-detail-title>${defaultEvent?.label ?? 'Evento'}</h3>
                <div class="mt-4 grid gap-3 sm:grid-cols-3">
                    <div class="rounded-lg bg-automotive-50 px-3 py-3">
                        <p class="text-[11px] text-automotive-500">Data</p>
                        <p class="mt-1 text-sm font-medium text-automotive-900" data-detail-date>${formatTimelineDate(defaultEvent?.date)}</p>
                    </div>
                    <div class="rounded-lg bg-automotive-50 px-3 py-3">
                        <p class="text-[11px] text-automotive-500">Quilometragem</p>
                        <p class="mt-1 text-sm font-medium text-automotive-900" data-detail-km>${formatTimelineKm(defaultEvent?.kilometers)}</p>
                    </div>
                    <div class="rounded-lg bg-automotive-50 px-3 py-3">
                        <p class="text-[11px] text-automotive-500">Total em itens</p>
                        <p class="mt-1 text-sm font-medium text-wrench-600" data-detail-total>
                            ${defaultEvent?.type === 'upcoming' ? '—' : formatTimelineMoney(defaultEvent?.total_amount)}
                        </p>
                    </div>
                </div>
                <p class="mt-3 text-sm text-automotive-600 ${defaultEvent?.workshop_name ? '' : 'hidden'}" data-detail-workshop>${defaultEvent?.workshop_name ?? ''}</p>
                <div class="mt-4 overflow-hidden rounded-lg" data-detail-items>${renderTimelineItems(defaultEvent ?? {})}</div>
            </div>
        </section>
    `;
}

export function mountVehicleTimeline(root, timeline) {
    const section = root.querySelector('[data-vehicle-timeline]');

    if (!section) {
        return;
    }

    const trackPercent = timeline?.summary?.track_progress_percent
        ?? Number(section.querySelector('[data-timeline-progress]')?.dataset.trackPercent ?? 0);

    const layoutTrack = () => positionTimelineProgress(section, trackPercent);

    requestAnimationFrame(layoutTrack);
    window.addEventListener('resize', layoutTrack);

    const events = (timeline?.events ?? []).filter((event) => event.type !== 'upcoming');
    const upcomingEvent = (timeline?.events ?? []).find((event) => event.type === 'upcoming');
    const displayEvents = upcomingEvent ? [...events, upcomingEvent] : events;
    let defaultIndex = 0;

    displayEvents.forEach((event, index) => {
        if (event.is_current && event.type !== 'upcoming') {
            defaultIndex = index;
        }
    });

    const trackCurrentIndex = timeline?.summary?.track_current_index ?? defaultIndex;

    const title = section.querySelector('[data-detail-title]');
    const date = section.querySelector('[data-detail-date]');
    const km = section.querySelector('[data-detail-km]');
    const total = section.querySelector('[data-detail-total]');
    const workshop = section.querySelector('[data-detail-workshop]');
    const items = section.querySelector('[data-detail-items]');
    const setSelectedStyles = (selectedIndex) => {
        section.querySelectorAll('[data-timeline-column]').forEach((column) => {
            const index = Number(column.dataset.index);
            const isSelected = index === selectedIndex;
            const isUpcoming = (displayEvents[index]?.type ?? '') === 'upcoming';
            const columnDate = column.querySelector('[data-column-date]');
            const columnTitle = column.querySelector('[data-column-title]');

            column.setAttribute('aria-pressed', isSelected ? 'true' : 'false');

            const markerWrap = column.querySelector('[data-timeline-marker]')?.closest('[data-timeline-marker]')
                ?? column.querySelector('[data-timeline-marker]');
            const eventAtIndex = displayEvents[index];

            if (markerWrap && eventAtIndex?.type === 'maintenance') {
                const parent = markerWrap.parentElement?.matches('[data-timeline-marker]')
                    ? markerWrap.parentElement
                    : markerWrap.closest('span.inline-flex') ?? markerWrap;
                if (parent?.classList) {
                    parent.classList.toggle('ring-2', isSelected);
                    parent.classList.toggle('ring-wrench-500', isSelected);
                    parent.classList.toggle('ring-offset-2', isSelected);
                }
            } else if (markerWrap && !isUpcoming) {
                if (isSelected) {
                    markerWrap.className = 'box-border h-5 w-5 rounded-full border-[3px] border-white bg-wrench-500 outline outline-[3px] outline-wrench-500';
                } else if (index <= trackCurrentIndex) {
                    markerWrap.className = 'box-border h-4 w-4 rounded-full border-2 border-wrench-500 bg-wrench-500';
                } else {
                    markerWrap.className = 'box-border h-4 w-4 rounded-full border-2 border-automotive-400 bg-white';
                }
                markerWrap.setAttribute('data-timeline-marker', '');
            }

            if (columnDate && !isUpcoming) {
                columnDate.classList.toggle('font-medium', isSelected);
                columnDate.classList.toggle('text-wrench-600', isSelected);
                columnDate.classList.toggle('text-automotive-500', !isSelected);
            }

            if (columnTitle && !isUpcoming) {
                columnTitle.classList.toggle('font-semibold', isSelected);
                columnTitle.classList.toggle('font-medium', !isSelected);
            }
        });
    };

    section.querySelectorAll('[data-timeline-column]').forEach((column) => {
        column.addEventListener('click', () => {
            const event = displayEvents[Number(column.dataset.index)];
            if (!event) {
                return;
            }

            setSelectedStyles(Number(column.dataset.index));

            if (title) {
                title.textContent = event.label || 'Evento';
            }
            if (date) {
                date.textContent = formatTimelineDate(event.date);
            }
            if (km) {
                km.textContent = formatTimelineKm(event.kilometers);
            }
            if (total) {
                total.textContent = event.type === 'upcoming' ? '—' : formatTimelineMoney(event.total_amount);
            }

            if (workshop) {
                if (event.workshop_name) {
                    workshop.textContent = event.workshop_name;
                    workshop.classList.remove('hidden');
                } else {
                    workshop.textContent = '';
                    workshop.classList.add('hidden');
                }
            }

            if (items) {
                items.innerHTML = renderTimelineItems(event);
            }
        });
    });
}
