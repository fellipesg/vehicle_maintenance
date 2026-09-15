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

function provenanceRootClass(maintenance) {
    return isVerifiedMaintenance(maintenance) ? 'prov-verified' : 'prov-declared';
}

function markerHtml(maintenance, size = 'md') {
    const verified = isVerifiedMaintenance(maintenance);
    const sizeClass = size === 'sm' ? 'w-6 h-6 text-[10px]' : 'w-7 h-7 text-xs';
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
        ${markerHtml(maintenance)}
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

export function renderProvenanceStrip(vehicle, { basePath = '', filterQuery = '' } = {}) {
    const strip = vehicle.provenance_strip ?? [];
    const total = vehicle.maintenances_count ?? strip.length;
    const verified = vehicle.verified_maintenances_count ?? strip.filter((s) => s.is_verified).length;
    const declared = total - verified;

    const segments = strip
        .map((segment) => {
            const cls = segment.is_verified ? 'prov-strip-segment--verified' : 'prov-strip-segment--declared';
            const title = `${formatDate(segment.date)}`;
            const href = segment.maintenance_id ? `${basePath}/manutencoes/${segment.maintenance_id}` : '#';

            return `<a href="${escapeHtml(href)}" class="prov-strip-segment ${cls}" title="${escapeHtml(title)}"></a>`;
        })
        .join('');

    const params = new URLSearchParams(window.location.search);
    params.delete('verified');
    params.delete('page');
    const suffix = params.toString();
    const prefix = suffix ? `?${suffix}&` : '?';
    const allHref = suffix ? `?${suffix}` : window.location.pathname;
    const verifiedHref = `${prefix}verified=1`;
    const declaredHref = `${prefix}verified=0`;
    const current = new URLSearchParams(window.location.search).get('verified');

    return `
        <div class="mb-4">
            <div class="prov-strip">${segments}</div>
            <p class="mt-2 text-sm text-automotive-600">${total} manutenções · ${verified} com selo de oficina · ${declared} declaradas</p>
            <div class="mt-2 flex flex-wrap gap-3 text-sm">
                <a href="${escapeHtml(allHref)}" class="underline ${current === null ? 'font-semibold text-automotive-900' : ''}">Todas</a>
                <a href="${escapeHtml(verifiedHref)}" class="underline ${current === '1' ? 'font-semibold text-automotive-900' : ''}">Selo da oficina</a>
                <a href="${escapeHtml(declaredHref)}" class="underline ${current === '0' ? 'font-semibold text-automotive-900' : ''}">Declaradas</a>
            </div>
        </div>
    `;
}

export function renderProvenanceLegend() {
    return `
        <div class="flex flex-wrap items-center gap-6 text-sm text-automotive-700">
            <div class="flex items-center gap-2">
                <div class="prov-marker prov-marker--verified prov-verified w-6 h-6 text-[10px]">OF</div>
                <span>Selo da oficina <span class="text-automotive-500">(verificada)</span></span>
            </div>
            <div class="flex items-center gap-2">
                <div class="prov-marker prov-marker--declared prov-declared w-6 h-6 text-[10px]">PR</div>
                <span>Declarada <span class="text-automotive-500">(não verificada)</span></span>
            </div>
        </div>
    `;
}
