import apiClient from './api/client';

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

function vehicleCoverHtml(vehicle) {
    const url = vehicle.cover_photo_url;
    if (url) {
        return `<img src="${escapeHtml(url)}" alt="" class="h-full w-full object-cover">`;
    }

    return '<div class="flex h-full w-full items-center justify-center bg-automotive-100 text-3xl">🚗</div>';
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
                    <div class="aspect-video overflow-hidden">${vehicleCoverHtml(vehicle)}</div>
                    <div class="p-6">
                        <div class="mb-3 flex items-start justify-between gap-3">
                            <div>
                                <h2 class="text-lg font-semibold">${escapeHtml(vehicle.brand)} ${escapeHtml(vehicle.model)}</h2>
                                <p class="text-sm text-automotive-600">${escapeHtml(vehicle.year)} · ${escapeHtml(vehicle.color ?? '—')}</p>
                            </div>
                            <span class="badge badge-blue">${escapeHtml(vehicle.license_plate)}</span>
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
    }
}

async function pollPdfExport(exportId, button) {
    const maxAttempts = 60;
    let attempts = 0;

    while (attempts < maxAttempts) {
        attempts += 1;
        const response = await apiClient.getVehiclePdfExportStatus(exportId);
        const data = response.data.data ?? {};

        if (data.status === 'completed' && data.download_url) {
            window.location.href = data.download_url;
            if (button) {
                button.disabled = false;
                button.textContent = '📄 Exportar PDF';
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
        const [vehicleRes, maintenancesRes, timelineRes] = await Promise.all([
            apiClient.getVehicle(vehicleId),
            apiClient.getVehicleMaintenances(vehicleId, { per_page: 50 }),
            apiClient.getVehicleTimeline(vehicleId),
        ]);

        const vehicle = vehicleRes.data.data;
        const maintenances = maintenancesRes.data.data ?? [];
        const timeline = timelineRes.data.data ?? {};

        if (content) {
            content.innerHTML = `
                <div class="mb-6 overflow-hidden rounded-xl border border-automotive-200">
                    <div class="aspect-[21/9] max-h-72">${vehicleCoverHtml(vehicle)}</div>
                </div>
                <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 class="text-3xl font-bold">${escapeHtml(vehicle.brand)} ${escapeHtml(vehicle.model)}</h1>
                        <p class="text-automotive-600">${escapeHtml(vehicle.year)} · ${escapeHtml(vehicle.color ?? '—')} · ${escapeHtml(vehicle.license_plate)}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" data-export-pdf class="btn-secondary">📄 Exportar PDF</button>
                        <a href="/usuario/veiculos/${vehicle.id}/editar" class="btn-secondary">Editar</a>
                        <a href="/usuario/manutencoes/criar?vehicle_id=${vehicle.id}" class="btn-primary">+ Manutenção</a>
                    </div>
                </div>
                <div class="mb-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div class="stat-card"><p class="text-sm text-automotive-600">RENAVAM</p><p class="font-semibold wrap-anywhere">${escapeHtml(vehicle.renavam)}</p></div>
                    <div class="stat-card"><p class="text-sm text-automotive-600">Chassi</p><p class="font-semibold text-sm wrap-anywhere">${escapeHtml(vehicle.chassis ?? '—')}</p></div>
                    <div class="stat-card"><p class="text-sm text-automotive-600">Quilometragem atual</p><p class="text-2xl font-bold">${formatKm(vehicle.current_kilometers)}</p></div>
                    <div class="stat-card"><p class="text-sm text-automotive-600">Manutenções</p><p class="text-2xl font-bold">${maintenances.length}</p></div>
                </div>
                <h2 class="mb-4 text-xl font-semibold">🔧 Histórico de Manutenções</h2>
                <div class="space-y-3">
                    ${maintenances.length === 0
                        ? '<div class="card text-center text-automotive-500">Nenhuma manutenção registrada.</div>'
                        : maintenances.map((maintenance) => `
                            <a href="/usuario/manutencoes/${maintenance.id}" class="card block hover:border-wrench-300">
                                <div class="flex justify-between gap-4">
                                    <div>
                                        <span class="badge badge-orange">${escapeHtml(categories[maintenance.service_category] ?? maintenance.service_category)}</span>
                                        <p class="mt-1 font-semibold">${escapeHtml(maintenance.maintenance_type)}</p>
                                        <p class="text-sm text-automotive-600">${formatKm(maintenance.kilometers)}</p>
                                    </div>
                                    <div class="text-right text-sm text-automotive-500">
                                        <p>${formatDate(maintenance.maintenance_date)}</p>
                                        ${maintenance.workshop_name ? `<p>🔧 ${escapeHtml(maintenance.workshop_name)}</p>` : ''}
                                    </div>
                                </div>
                            </a>
                        `).join('')}
                </div>
            `;
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
        const response = await apiClient.getMaintenances({ per_page: 15 });
        const maintenances = response.data.data ?? [];

        if (!list) {
            return;
        }

        list.innerHTML = maintenances.length === 0
            ? '<div class="card text-center text-automotive-500"><p>Nenhuma manutenção registrada.</p></div>'
            : maintenances.map((maintenance) => `
                <a href="/usuario/manutencoes/${maintenance.id}" class="card mb-3 block hover:border-wrench-300">
                    <div class="flex justify-between">
                        <div>
                            <span class="badge badge-orange">${escapeHtml(categories[maintenance.service_category] ?? '')}</span>
                            <p class="mt-1 font-semibold">${escapeHtml(maintenance.maintenance_type)}</p>
                            <p class="text-sm text-automotive-600">${escapeHtml(maintenance.vehicle?.brand ?? '')} ${escapeHtml(maintenance.vehicle?.model ?? '')} · ${escapeHtml(maintenance.vehicle?.license_plate ?? '')}</p>
                        </div>
                        <div class="text-right text-sm text-automotive-500">
                            <p>${formatDate(maintenance.maintenance_date)}</p>
                            ${maintenance.workshop_name ? `<p>🔧 ${escapeHtml(maintenance.workshop_name)}</p>` : ''}
                        </div>
                    </div>
                </a>
            `).join('');
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
