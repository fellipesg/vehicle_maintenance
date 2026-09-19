const ACTIVE_FILTER_CLASS = '!bg-wrench-100';

function verifiedFromUrl(url) {
    const parsed = new URL(url, window.location.origin);
    const value = parsed.searchParams.get('verified');

    if (value === '1' || value === '0') {
        return value;
    }

    return '';
}

function buildFilterUrl(baseUrl, verified) {
    const url = new URL(baseUrl, window.location.origin);

    url.searchParams.delete('page');
    url.searchParams.delete('verified');

    if (verified === '1' || verified === '0') {
        url.searchParams.set('verified', verified);
    }

    return url.toString();
}

function setActiveFilterButton(root, verified) {
    root.querySelectorAll('[data-maintenance-filter]').forEach((button) => {
        const isActive = button.dataset.maintenanceFilter === verified;
        button.classList.toggle(ACTIVE_FILTER_CLASS, isActive);
        button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    });
    root.dataset.verified = verified;
}

async function loadMaintenances(root, url) {
    const results = root.querySelector('[data-admin-maintenances-results]');
    if (!results) {
        return;
    }

    results.setAttribute('aria-busy', 'true');
    results.classList.add('opacity-60', 'pointer-events-none');

    try {
        const response = await fetch(url, {
            headers: {
                Accept: 'text/html',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            return;
        }

        results.innerHTML = await response.text();
        const verified = verifiedFromUrl(url);
        setActiveFilterButton(root, verified);
        window.history.pushState({ adminMaintenancesVerified: verified }, '', url);
    } finally {
        results.setAttribute('aria-busy', 'false');
        results.classList.remove('opacity-60', 'pointer-events-none');
    }
}

export function initAdminMaintenancesFilters() {
    const root = document.querySelector('[data-admin-maintenances]');
    if (!root) {
        return;
    }

    const baseUrl = root.dataset.baseUrl;
    if (!baseUrl) {
        return;
    }

    setActiveFilterButton(root, root.dataset.verified ?? verifiedFromUrl(window.location.href));

    root.querySelectorAll('[data-maintenance-filter]').forEach((button) => {
        button.addEventListener('click', () => {
            const verified = button.dataset.maintenanceFilter ?? '';
            const url = buildFilterUrl(baseUrl, verified);
            loadMaintenances(root, url);
        });
    });

    root.addEventListener('click', (event) => {
        const link = event.target.closest('a');
        if (!link || !link.closest('[data-admin-maintenances-pagination]')) {
            return;
        }

        event.preventDefault();
        loadMaintenances(root, link.href);
    });

    window.addEventListener('popstate', () => {
        loadMaintenances(root, window.location.href);
    });
}
