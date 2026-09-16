import apiClient from './api/client';
import { initProvenanceActions } from './provenance-actions';
import {
    filterMaintenancesByVerifiedQuery,
    initProvenanceStripFilters,
    renderMaintenanceHistoryList,
    renderProvenanceCard,
    renderProvenanceLegend,
    renderProvenanceStrip,
    renderVehicleIdentity,
} from './provenance-ui';
import { initVehicleIdentity } from './vehicle-identity';
import { mountVehicleTimeline, renderVehicleTimeline } from './vehicle-timeline-portal';

const categories = {
    mechanical: 'Mecânica',
    electrical: 'Elétrica',
    suspension: 'Suspensão',
    painting: 'Pintura',
    finishing: 'Acabamento',
    interior: 'Interior',
    other: 'Outros',
};

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

function formatKm(value) {
    if (value === null || value === undefined) {
        return '—';
    }

    return `${Number(value).toLocaleString('pt-BR')} km`;
}

function vehicleCoverHtml(vehicle, options = {}) {
    const variant = typeof options === 'string' ? options : (options.variant ?? 'default');
    const fill = typeof options === 'string' ? false : (options.fill ?? variant !== 'thumb');
    const landscape = vehicle.cover_photo_url;
    const portrait = vehicle.cover_photo_portrait_url || landscape;
    const fallbackLandscape = landscape || portrait;
    const imgClass = fill
        ? 'absolute inset-0 h-full w-full object-cover object-center'
        : 'h-full w-full object-cover object-center';
    const pictureClass = fill ? 'absolute inset-0 block h-full w-full' : 'block h-full w-full';

    if (!fallbackLandscape) {
        const placeholderClass = fill
            ? 'absolute inset-0 flex items-center justify-center bg-automotive-100 text-3xl'
            : 'flex h-full w-full items-center justify-center bg-automotive-100 text-3xl';

        return `<div class="${placeholderClass}">🚗</div>`;
    }

    if (variant === 'thumb' || !landscape || landscape === portrait) {
        return `<img src="${escapeHtml(portrait || fallbackLandscape)}" alt="" class="${imgClass}">`;
    }

    return `<picture class="${pictureClass}">
        <source media="(min-width: 768px)" srcset="${escapeHtml(fallbackLandscape)}">
        <img src="${escapeHtml(portrait || fallbackLandscape)}" alt="" class="${imgClass}">
    </picture>`;
}

async function loadDashboard() {
    const root = document.querySelector('[data-api-page="dashboard"]');
    if (!root) {
        return;
    }

    const vehiclesContainer = root.querySelector('[data-dashboard-vehicles]');
    const maintenancesContainer = root.querySelector('[data-dashboard-maintenances]');
    const vehicleCount = root.querySelector('[data-vehicle-count]');
    const maintenanceCount = root.querySelector('[data-maintenance-count]');

    try {
        const [vehiclesRes, maintenancesRes] = await Promise.all([
            apiClient.getMyVehicles({ per_page: 5 }),
            apiClient.getMaintenances({ per_page: 5 }),
        ]);

        const vehicles = vehiclesRes.data.data ?? [];
        const maintenances = maintenancesRes.data.data ?? [];

        if (vehicleCount) {
            vehicleCount.textContent = String(vehiclesRes.data.meta?.total ?? vehicles.length);
        }

        if (maintenanceCount) {
            maintenanceCount.textContent = String(maintenances.length);
        }

        if (vehiclesContainer) {
            vehiclesContainer.innerHTML = vehicles.length === 0
                ? '<div class="card !p-8 text-center"><p class="text-automotive-600">Nenhum veículo cadastrado ainda.</p></div>'
                : vehicles.map((vehicle) => `
                    <a href="/usuario/veiculos/${vehicle.id}" class="card mb-2 block !p-4 transition hover:border-wrench-300 hover:shadow-sm">
                        <div class="flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-semibold text-automotive-900">${escapeHtml(vehicle.brand)} ${escapeHtml(vehicle.model)}</p>
                                <p class="text-sm text-automotive-600">${escapeHtml(vehicle.year)} · ${escapeHtml(vehicle.license_plate)}</p>
                            </div>
                            <span class="badge badge-blue shrink-0">${escapeHtml(vehicle.maintenances_count ?? 0)} manutenções</span>
                        </div>
                    </a>
                `).join('');
        }

        if (maintenancesContainer) {
            maintenancesContainer.innerHTML = maintenances.length === 0
                ? '<div class="card !p-8 text-center"><p class="text-automotive-600">Nenhuma manutenção registrada.</p></div>'
                : maintenances.map((maintenance) => `
                    <a href="/usuario/manutencoes/${maintenance.id}" class="card mb-2 block !p-4 transition hover:border-wrench-300">
                        <p class="font-semibold text-automotive-900">${escapeHtml(maintenance.maintenance_type)}</p>
                        <p class="text-sm text-automotive-600">${escapeHtml(maintenance.vehicle?.brand ?? '')} ${escapeHtml(maintenance.vehicle?.model ?? '')} · ${formatDate(maintenance.maintenance_date)}</p>
                    </a>
                `).join('');
        }
    } catch (error) {
        console.error('Failed to load dashboard data', error);
    }
}

async function loadVehiclesIndex() {
    const root = document.querySelector('[data-api-page="vehicles-index"]');
    if (!root) {
        return;
    }

    const grid = root.querySelector('[data-vehicles-grid]');

    try {
        const response = await apiClient.getMyVehicles({ per_page: 50 });
        const vehicles = response.data.data ?? [];

        if (!grid) {
            return;
        }

        grid.innerHTML = vehicles.length === 0
            ? '<div class="card col-span-full text-center"><p class="text-automotive-500">Nenhum veículo cadastrado ainda.</p></div>'
            : vehicles.map((vehicle) => `
                <div class="card !p-0 overflow-hidden">
                    <div class="relative aspect-video overflow-hidden max-md:aspect-[9/16] max-md:max-h-64">${vehicleCoverHtml(vehicle, { fill: true })}</div>
                    <div class="p-6">
                        <div class="mb-3">
                            <h2 class="text-lg font-semibold">${escapeHtml(vehicle.brand)} ${escapeHtml(vehicle.model)}</h2>
                            <p class="text-sm text-automotive-600">${escapeHtml(vehicle.year)} · ${escapeHtml(vehicle.color ?? '—')}</p>
                            <div class="mt-2">${renderVehicleIdentity(vehicle, { editUrl: `/usuario/veiculos/${vehicle.id}/editar` })}</div>
                        </div>
                        <p class="mb-4 text-sm text-automotive-500">${escapeHtml(vehicle.maintenances_count ?? 0)} manutenções registradas</p>
                        <div class="flex gap-2">
                            <a href="/usuario/veiculos/${vehicle.id}" class="btn-primary !py-1.5 !text-xs flex-1 text-center">Ver</a>
                            <a href="/usuario/veiculos/${vehicle.id}/editar" class="btn-secondary !py-1.5 !text-xs">Editar</a>
                        </div>
                    </div>
                </div>
            `).join('');
    } catch (error) {
        console.error('Failed to load vehicles', error);
        if (grid) {
            grid.innerHTML = '<div class="card col-span-full text-center text-red-600">Não foi possível carregar os veículos. Recarregue a página ou faça login novamente.</div>';
        }
    }
}

function pdfDownloadFilename(name) {
    const fallback = 'historico_manutencoes.pdf';
    const raw = String(name || fallback).trim() || fallback;

    return raw.toLowerCase().endsWith('.pdf') ? raw : `${raw}.pdf`;
}

async function pollPdfExport(exportId, button) {
    const maxAttempts = 60;
    let attempts = 0;

    while (attempts < maxAttempts) {
        attempts += 1;
        const response = await apiClient.getVehiclePdfExportStatus(exportId);
        const data = response.data.data ?? {};

        if (data.status === 'completed') {
            const filename = pdfDownloadFilename(data.filename);
            const portalUrl = data.download_portal_url
                || `/usuario/exportacoes-pdf/${exportId}/${encodeURIComponent(filename)}`;

            if (button) {
                const link = document.createElement('a');
                // Last path segment MUST be *.pdf. Do not set the download attribute:
                // Chromium then fetches as blob: and the download shelf shows a UUID.
                link.href = portalUrl;
                link.className = button.className || 'btn-secondary';
                link.textContent = 'Baixar PDF';
                button.replaceWith(link);
            }

            return;
        }

        if (data.status === 'failed') {
            throw new Error(data.error_message || 'PDF export failed');
        }

        await new Promise((resolve) => setTimeout(resolve, 2000));
    }

    throw new Error('PDF export timed out');
}

async function loadVehicleShow() {
    const root = document.querySelector('[data-api-page="vehicle-show"]');
    if (!root) {
        return;
    }

    const vehicleId = root.dataset.vehicleId;
    const content = root.querySelector('[data-vehicle-content]');
    const exportButton = root.querySelector('[data-export-pdf]');

    try {
        const [vehicleRes, timelineRes] = await Promise.all([
            apiClient.getVehicle(vehicleId),
            apiClient.getVehicleTimeline(vehicleId),
        ]);

        const vehicle = vehicleRes.data.data;
        const timeline = timelineRes.data.data ?? {};
        const maintenances = (timeline.events ?? [])
            .filter((event) => event.type === 'maintenance')
            .sort((left, right) => (right.date ?? '').localeCompare(left.date ?? ''))
            .map((event) => ({
                id: event.id,
                maintenance_type: event.label,
                kilometers: event.kilometers,
                maintenance_date: event.date,
                workshop_name: event.workshop_name,
                service_category: event.service_category,
                is_verified: event.is_verified,
                registered_by_type: event.registered_by_type,
                provenance_label: event.provenance_label,
                provenance_card_label: event.provenance_label,
                provenance_meta: event.is_verified ? 'verificada' : 'não verificada',
            }));

        const filteredMaintenances = filterMaintenancesByVerifiedQuery(maintenances);

        const plateRows = (vehicle.plate_history ?? [])
            .map((row) => `<tr class="border-t border-automotive-100"><td class="py-2 font-mono">${escapeHtml(row.plate)}</td><td class="py-2">${formatDate(row.started_at)}</td><td class="py-2">${row.ended_at ? formatDate(row.ended_at) : 'Vigente'}</td><td class="py-2">${escapeHtml(row.source)}</td></tr>`)
            .join('');

        if (content) {
            content.innerHTML = `
                <div class="relative mb-6 w-full overflow-hidden rounded-xl border border-automotive-200 aspect-[9/16] max-h-80 md:aspect-[21/9] md:max-h-72">
                    ${vehicleCoverHtml(vehicle, { fill: true })}
                </div>
                <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
                    <div class="space-y-3">
                        <h1 class="text-3xl font-bold">${escapeHtml(vehicle.brand)} ${escapeHtml(vehicle.model)}</h1>
                        <p class="text-automotive-600">${escapeHtml(vehicle.year)} · ${escapeHtml(vehicle.color ?? '—')}</p>
                        ${renderVehicleIdentity(vehicle, { size: 'hero', editUrl: `/usuario/veiculos/${vehicle.id}/editar` })}
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" data-export-pdf class="btn-secondary">📄 Exportar PDF</button>
                        <a href="/usuario/veiculos/${vehicle.id}/editar" class="btn-secondary">Editar</a>
                        <a href="/usuario/manutencoes/criar?vehicle_id=${vehicle.id}" class="btn-primary">+ Manutenção</a>
                    </div>
                </div>
                ${renderProvenanceStrip(vehicle, { basePath: '/usuario', interactiveFilters: true })}
                ${plateRows ? `<details class="card mb-6"><summary class="cursor-pointer font-semibold text-automotive-900">Histórico de placas</summary><table class="mt-4 w-full text-sm"><thead><tr class="text-left text-automotive-500"><th>Placa</th><th>De</th><th>Até</th><th>Origem</th></tr></thead><tbody>${plateRows}</tbody></table></details>` : ''}
                <div class="mb-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div class="stat-card"><p class="text-sm text-automotive-600">RENAVAM</p><p class="font-semibold wrap-anywhere">${escapeHtml(vehicle.renavam)}</p></div>
                    <div class="stat-card"><p class="text-sm text-automotive-600">Chassi</p><p class="font-semibold text-sm wrap-anywhere">${escapeHtml(vehicle.chassis ?? '—')}</p></div>
                    <div class="stat-card"><p class="text-sm text-automotive-600">Quilometragem atual</p><p class="text-2xl font-bold">${formatKm(vehicle.current_kilometers)}</p></div>
                    <div class="stat-card"><p class="text-sm text-automotive-600">Manutenções</p><p class="text-2xl font-bold">${maintenances.length}</p></div>
                </div>
                ${renderVehicleTimeline(timeline)}
                <h2 class="mb-4 text-xl font-semibold" data-maintenance-list-title>🔧 Histórico de Manutenções (${filteredMaintenances.length})</h2>
                <div class="space-y-3" data-maintenance-list>
                    ${renderMaintenanceHistoryList(filteredMaintenances, { basePath: '/usuario' })}
                </div>
            `;

            mountVehicleTimeline(content, timeline);
            initVehicleIdentity(content);
            initProvenanceActions(content);
            initProvenanceStripFilters(content, maintenances, { basePath: '/usuario' });
        }

        const newExportButton = root.querySelector('[data-export-pdf]');
        if (newExportButton) {
            newExportButton.addEventListener('click', async () => {
                newExportButton.disabled = true;
                newExportButton.textContent = 'Gerando PDF...';

                try {
                    const exportResponse = await apiClient.requestVehiclePdfExport(vehicleId);
                    const exportId = exportResponse.data.data?.export_id;
                    await pollPdfExport(exportId, newExportButton);
                } catch (error) {
                    console.error(error);
                    alert('Não foi possível exportar o PDF. Tente novamente.');
                    newExportButton.disabled = false;
                    newExportButton.textContent = '📄 Exportar PDF';
                }
            });
        }
    } catch (error) {
        console.error('Failed to load vehicle', error);
        if (content) {
            content.innerHTML = '<div class="card text-center text-red-600">Não foi possível carregar o veículo.</div>';
        }
    }

    if (exportButton) {
        exportButton.remove();
    }
}

async function loadMaintenancesIndex() {
    const root = document.querySelector('[data-api-page="maintenances-index"]');
    if (!root) {
        return;
    }

    const list = root.querySelector('[data-maintenances-list]');

    try {
        const verified = new URLSearchParams(window.location.search).get('verified');
        const response = await apiClient.getMaintenances({
            per_page: 15,
            ...(verified === '1' || verified === '0' ? { verified } : {}),
        });
        const maintenances = response.data.data ?? [];

        if (!list) {
            return;
        }

        list.innerHTML = maintenances.length === 0
            ? '<div class="card text-center text-automotive-500"><p>Nenhuma manutenção registrada.</p></div>'
            : maintenances.map((maintenance) => renderProvenanceCard(maintenance, {
                href: `/usuario/manutencoes/${maintenance.id}`,
            })).join('');
    } catch (error) {
        console.error('Failed to load maintenances', error);
    }
}

async function loadWorkshopsIndex() {
    const root = document.querySelector('[data-api-page="workshops-index"]');
    if (!root) {
        return;
    }

    const grid = root.querySelector('[data-workshops-grid]');
    const form = root.querySelector('[data-workshops-search]');

    const load = async (search = '') => {
        try {
            const response = await apiClient.getWorkshops({
                search: search || undefined,
                per_page: 50,
            });
            const workshops = response.data.data ?? [];

            if (!grid) {
                return;
            }

            grid.innerHTML = workshops.length === 0
                ? '<div class="card col-span-full text-center text-automotive-500">Nenhuma oficina encontrada.</div>'
                : workshops.map((workshop) => `
                    <div class="card">
                        <h2 class="text-lg font-semibold">🔧 ${escapeHtml(workshop.name)}</h2>
                        <p class="mt-1 text-sm text-automotive-600">${escapeHtml(workshop.neighborhood)}, ${escapeHtml(workshop.city)}/${escapeHtml(workshop.state)}</p>
                        <p class="mt-2 text-sm">📞 ${escapeHtml(workshop.phone)}</p>
                        ${workshop.email ? `<p class="text-sm">✉️ ${escapeHtml(workshop.email)}</p>` : ''}
                    </div>
                `).join('');
        } catch (error) {
            console.error('Failed to load workshops', error);
        }
    };

    if (form) {
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            const search = form.querySelector('input[name="search"]')?.value ?? '';
            load(search);
        });
    }

    await load(new URLSearchParams(window.location.search).get('search') ?? '');
}

export function initUserPortalApi() {
    loadDashboard();
    loadVehiclesIndex();
    loadVehicleShow();
    loadMaintenancesIndex();
    loadWorkshopsIndex();
}
