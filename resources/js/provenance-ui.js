function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function formatDate(value) {
    if (!value) {
        return '—';
    }

    const date = new Date(value.includes('T') ? value : `${value}T00:00:00`);

    return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString('pt-BR');
}

function isVerifiedMaintenance(maintenance) {
    return maintenance.is_verified === true || maintenance.verified_at != null;
}

/** @param {Array<{ is_verified?: boolean, verified_at?: string|null }>} maintenances */
export function filterMaintenancesByVerifiedQuery(maintenances, searchOrVerified = '') {
    let verified = null;

    if (searchOrVerified === '1' || searchOrVerified === '0') {
        verified = searchOrVerified;
    } else {
        verified = new URLSearchParams(searchOrVerified || window.location.search).get('verified');
    }

    if (verified === '1') {
        return maintenances.filter((maintenance) => isVerifiedMaintenance(maintenance));
    }

    if (verified === '0') {
        return maintenances.filter((maintenance) => ! isVerifiedMaintenance(maintenance));
    }

    return maintenances;
}

export function renderMaintenanceHistoryList(
    maintenances,
    { basePath = '/usuario', linkMaintenances = true } = {},
) {
    if (maintenances.length === 0) {
        return '<div class="card text-center text-automotive-500">Nenhuma manutenção neste filtro.</div>';
    }

    return maintenances
        .map((maintenance) => renderProvenanceCard(maintenance, {
            href: linkMaintenances ? `${basePath}/manutencoes/${maintenance.id}` : null,
        }))
        .join('');
}

function provenanceFilterChipClass(isActive) {
    return `underline ${isActive ? 'font-semibold text-automotive-900' : ''}`;
}

export function initProvenanceStripFilters(
    root,
    allMaintenances,
    { basePath = '/usuario', linkMaintenances = true } = {},
) {
    const listHost = root.querySelector('[data-maintenance-list]');
    const titleEl = root.querySelector('[data-maintenance-list-title]');

    if (!listHost) {
        return;
    }

    const titleTemplate = titleEl?.dataset.titleTemplate
        ?? '🔧 Histórico de Manutenções ({count})';

    const applyFilter = (verifiedValue) => {
        const filtered = filterMaintenancesByVerifiedQuery(
            allMaintenances,
            verifiedValue === null ? '' : verifiedValue,
        );

        const url = new URL(window.location.href);
        if (verifiedValue === null || verifiedValue === '') {
            url.searchParams.delete('verified');
        } else {
            url.searchParams.set('verified', verifiedValue);
        }
        history.replaceState(null, '', url);

        root.querySelectorAll('[data-provenance-filter]').forEach((button) => {
            const value = button.dataset.provenanceFilter ?? '';
            const active =
                (value === '' && !url.searchParams.get('verified'))
                || url.searchParams.get('verified') === value;
            button.className = provenanceFilterChipClass(active);
        });

        listHost.innerHTML = renderMaintenanceHistoryList(filtered, {
            basePath,
            linkMaintenances,
        });

        if (titleEl) {
            titleEl.textContent = titleTemplate.replace('{count}', String(filtered.length));
        }
    };

    root.querySelectorAll('[data-provenance-filter]').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            const value = button.dataset.provenanceFilter ?? '';
            applyFilter(value === '' ? null : value);
        });
    });

    const initialVerified = new URLSearchParams(window.location.search).get('verified');
    applyFilter(initialVerified === '1' || initialVerified === '0' ? initialVerified : null);
}

function provenanceRootClass(maintenance) {
    return isVerifiedMaintenance(maintenance) ? 'prov-verified' : 'prov-declared';
}

export function renderProvenanceMarker(maintenance, size = 'md') {
    const verified = isVerifiedMaintenance(maintenance);
    const sizeClass = size === 'sm' ? 'prov-marker--sm' : size === 'lg' ? 'prov-marker--lg' : '';
    const logo = maintenance.verified_workshop?.logo_url ?? maintenance.workshop?.logo_url;
    const initials = (maintenance.verified_workshop?.name ?? maintenance.workshop_name ?? maintenance.user?.name ?? '?')
        .split(' ')
        .map((p) => p[0])
        .join('')
        .slice(0, 2)
        .toUpperCase();

    if (verified) {
        const inner = logo
            ? `<img src="${escapeHtml(logo)}" alt="" class="h-full w-full object-cover">`
            : escapeHtml(initials);

        return `<div class="prov-marker prov-marker--verified ${sizeClass} ${provenanceRootClass(maintenance)}">${inner}</div>`;
    }

    return `<div class="prov-marker prov-marker--declared ${sizeClass} ${provenanceRootClass(maintenance)}">${escapeHtml(initials)}</div>`;
}

export function renderProvenanceCard(maintenance, { href = null, titleWeight = true } = {}) {
    const verified = isVerifiedMaintenance(maintenance);
    const railClass = verified ? 'prov-rail' : 'prov-rail prov-rail--declared';
    const cardClass = verified ? 'prov-card' : 'prov-card prov-card--declared';
    const titleClass = verified ? 'font-semibold text-automotive-900' : 'font-medium text-automotive-700';
    const label = maintenance.provenance_card_label ?? maintenance.provenance_label ?? '';
    const meta = maintenance.provenance_meta ?? maintenance.provenance_sublabel ?? '';
    const type = escapeHtml(maintenance.maintenance_type ?? '');
    const inner = `
        <div class="${railClass} ${provenanceRootClass(maintenance)}"></div>
        ${renderProvenanceMarker(maintenance)}
        <div class="min-w-0 flex-1">
            <p class="${titleClass}">${type}</p>
            <p class="text-sm text-automotive-600">${escapeHtml(label)}</p>
            <p class="text-xs text-automotive-500">${escapeHtml(meta)}</p>
        </div>
    `;

    if (href) {
        return `<a href="${escapeHtml(href)}" class="${cardClass} ${provenanceRootClass(maintenance)} mb-3 block hover:border-wrench-300">${inner}</a>`;
    }

    return `<div class="${cardClass} ${provenanceRootClass(maintenance)} mb-3">${inner}</div>`;
}

export function renderVehicleIdentity(vehicle, { size = 'card', editUrl = null } = {}) {
    const isHero = size === 'hero';
    const chassisClass = isHero
        ? 'font-mono tracking-wider font-semibold text-automotive-900 text-lg sm:text-xl'
        : 'font-mono tracking-wider font-semibold text-automotive-900 text-sm';
    const chassis = vehicle.chassis;
    const plate = vehicle.current_plate ?? vehicle.license_plate;

    let chassisBlock = '';
    if (chassis) {
        const copyBtn = isHero
            ? `<button type="button" class="btn-secondary text-xs py-1 px-2" data-copy-chassis data-chassis="${escapeHtml(chassis)}">Copiar</button>`
            : '';
        chassisBlock = `<div class="flex flex-wrap items-center gap-2"><p class="${chassisClass}">${escapeHtml(chassis)}</p>${copyBtn}</div>`;
    } else if (editUrl) {
        chassisBlock = `<a href="${escapeHtml(editUrl)}" class="inline-flex rounded-md border border-amber-300 bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-900">Chassi não informado</a>`;
    } else {
        chassisBlock = `<span class="inline-flex rounded-md border border-amber-300 bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-900">Chassi não informado</span>`;
    }

    const plateChip = plate
        ? `<span class="inline-flex rounded-md border border-automotive-200 px-2 py-0.5 text-xs text-automotive-700">Placa atual ${escapeHtml(plate)}</span>`
        : '';

    return `
        <div class="space-y-1">
            <p class="text-xs uppercase tracking-wide text-automotive-500">Chassi</p>
            ${chassisBlock}
            ${plateChip}
        </div>
    `;
}

const PROVENANCE_DOTS_MAX = 16;

export function renderProvenanceStrip(vehicle, { basePath = '', interactiveFilters = false } = {}) {
    const strip = vehicle.provenance_strip ?? [];
    const total = vehicle.maintenances_count ?? strip.length;
    const verified = vehicle.verified_maintenances_count ?? strip.filter((s) => s.is_verified).length;
    const declared = Math.max(0, total - verified);
    const declaredLabel = declared === 1 ? 'declarada' : 'declaradas';
    const visibleDots = strip.slice(0, PROVENANCE_DOTS_MAX);
    const overflowDots = Math.max(0, strip.length - visibleDots.length);

    const dots = visibleDots
        .map((segment) => {
            const cls = segment.is_verified ? 'prov-dot--verified' : 'prov-dot--declared';
            const kind = segment.is_verified ? 'Selo da oficina' : 'Declarada';
            const title = `${kind} · ${formatDate(segment.date)}`;
            const href = segment.maintenance_id ? `${basePath}/manutencoes/${segment.maintenance_id}` : '#';

            return `<a href="${escapeHtml(href)}" class="prov-dot-link" title="${escapeHtml(title)}"><span class="prov-dot ${cls}"></span></a>`;
        })
        .join('');
    const overflow = overflowDots > 0
        ? `<span class="prov-dots-more" title="${overflowDots} manutenções a mais">+${overflowDots}</span>`
        : '';
    const dotsRow = visibleDots.length > 0
        ? `<div class="prov-dots-row" role="img" aria-label="Linha de procedência das manutenções, da mais antiga à mais recente">${dots}${overflow}</div>`
        : '';

    const current = new URLSearchParams(window.location.search).get('verified');

    let filterControls = '';

    if (interactiveFilters) {
        const chip = (value, label) => {
            const active = (value === '' && current === null) || current === value;

            return `<button type="button" data-provenance-filter="${value}" class="${provenanceFilterChipClass(active)}">${label}</button>`;
        };
        filterControls = `
            ${chip('', 'Todas')}
            ${chip('1', 'Selo da oficina')}
            ${chip('0', 'Declaradas')}
        `;
    } else {
        const params = new URLSearchParams(window.location.search);
        params.delete('verified');
        params.delete('page');
        const suffix = params.toString();
        const prefix = suffix ? `?${suffix}&` : '?';
        const allHref = suffix ? `?${suffix}` : window.location.pathname;
        const verifiedHref = `${prefix}verified=1`;
        const declaredHref = `${prefix}verified=0`;
        filterControls = `
            <a href="${escapeHtml(allHref)}" class="${provenanceFilterChipClass(current === null)}">Todas</a>
            <a href="${escapeHtml(verifiedHref)}" class="${provenanceFilterChipClass(current === '1')}">Selo da oficina</a>
            <a href="${escapeHtml(declaredHref)}" class="${provenanceFilterChipClass(current === '0')}">Declaradas</a>
        `;
    }

    return `
        <div class="mb-4" data-provenance-strip>
            <p class="prov-strip-summary text-sm text-automotive-700">
                <span class="font-medium text-[#0f766e]">${verified}</span> com selo ·
                <span class="font-medium text-[#92400e]">${declared}</span> ${declaredLabel}
            </p>
            ${dotsRow}
            <div class="mt-2 flex flex-wrap gap-3 text-sm">
                ${filterControls}
            </div>
        </div>
    `;
}

export function renderProvenanceLegend() {
    return `
        <div class="flex flex-wrap items-center gap-6 text-sm text-automotive-700">
            <div class="flex items-center gap-2">
                <div class="prov-marker prov-marker--verified prov-marker--sm prov-verified">OF</div>
                <span>Selo da oficina <span class="text-automotive-500">(verificada)</span></span>
            </div>
            <div class="flex items-center gap-2">
                <div class="prov-marker prov-marker--declared prov-marker--sm prov-declared">PR</div>
                <span>Declarada <span class="text-automotive-500">(não verificada)</span></span>
            </div>
        </div>
    `;
}
