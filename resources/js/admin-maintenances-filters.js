/**
 * Filtro de procedência, ordenação e paginação da lista de manutenções do admin sem recarregar a
 * página. Os botões do segmentado são type="submit" name="verified" dentro do formulário de filtros
 * (sem JS, o envio recarrega com ?verified=); aqui o clique é interceptado e só a tabela é trocada,
 * mantendo a busca, o período e os filtros de contexto (usuario, veiculo, oficina) da URL atual.
 * O botão ativo é marcado só por aria-pressed: o visual (fundo de destaque, negrito e ícone de
 * check) vem das variantes aria-pressed: e group-aria-pressed: no Blade.
 */
const LOADING_CLASSES = ['opacity-60', 'pointer-events-none'];
const LOAD_ERROR_MESSAGE = 'Não foi possível carregar as manutenções.';

function verifiedFromUrl(url) {
    const parsed = new URL(url, window.location.origin);
    const value = parsed.searchParams.get('verified');

    if (value === '1' || value === '0') {
        return value;
    }

    return '';
}

/**
 * A URL da lista com outra procedência: parte da URL atual da tabela (busca, período, ordenação e
 * contexto continuam) e volta para a primeira página.
 */
function buildFilterUrl(listUrl, verified) {
    const url = new URL(listUrl, window.location.origin);

    url.searchParams.delete('page');
    url.searchParams.delete('verified');

    if (verified === '1' || verified === '0') {
        url.searchParams.set('verified', verified);
    }

    return url.toString();
}

function withoutHash(url) {
    const parsed = new URL(url, window.location.origin);
    parsed.hash = '';

    return parsed.toString();
}

function prefersReducedMotion() {
    return window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
}

function isModifiedClick(event) {
    return event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey;
}

function setActiveFilterButton(root, verified) {
    root.querySelectorAll('[data-maintenance-filter]').forEach((button) => {
        const isActive = button.dataset.maintenanceFilter === verified;
        button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    });
    root.dataset.verified = verified;

    // O "Filtrar" do formulário (busca e período) leva a procedência escolhida no campo oculto.
    const hiddenVerified = root.querySelector('[data-admin-maintenances-verified]');
    if (hiddenVerified) {
        hiddenVerified.value = verified;
        hiddenVerified.disabled = verified === '';
    }
}

/**
 * A ordenação trocada no lugar (link do cabeçalho da tabela) segue no próximo envio do formulário
 * de busca e período, pelos campos ocultos ordenar/direcao.
 */
function syncSortInputs(root, url) {
    const form = root.querySelector('[data-admin-maintenances-form]');
    if (!form) {
        return;
    }

    const params = new URL(url, window.location.origin).searchParams;

    ['ordenar', 'direcao'].forEach((name) => {
        let input = form.querySelector(`input[type="hidden"][name="${name}"]`);
        const value = params.get(name);

        if (value === null) {
            input?.remove();

            return;
        }

        if (!input) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            form.prepend(input);
        }

        input.value = value;
    });
}

function isListNavigationLink(link) {
    return Boolean(link.closest('[data-admin-maintenances-pagination]') || link.matches('[data-slot="table-sort"]'));
}

function historyState(verified) {
    const currentState = window.history.state;
    const baseState = currentState !== null && typeof currentState === 'object' ? currentState : {};

    return { ...baseState, adminMaintenances: true, adminMaintenancesVerified: verified };
}

export function initAdminMaintenancesFilters() {
    const root = document.querySelector('[data-admin-maintenances]');
    if (!root) {
        return;
    }

    const baseUrl = root.dataset.baseUrl;
    const results = root.querySelector('[data-admin-maintenances-results]');
    if (!baseUrl || !results) {
        return;
    }

    const basePath = new URL(baseUrl, window.location.origin).pathname;
    const statusRegion = root.querySelector('[data-admin-maintenances-status]');
    const errorTemplate = root.querySelector('template[data-admin-maintenances-error-template]');

    let currentUrl = withoutHash(window.location.href);
    let activeRequest = null;
    let failedRequest = null;
    let announceTimer = null;

    function announce(message) {
        if (!statusRegion) {
            return;
        }

        window.clearTimeout(announceTimer);
        statusRegion.textContent = '';

        if (message === '') {
            return;
        }

        // Clearing first and writing on the next tick makes screen readers repeat identical summaries.
        announceTimer = window.setTimeout(() => {
            statusRegion.textContent = message;
        }, 100);
    }

    function setLoading(isLoading) {
        results.setAttribute('aria-busy', isLoading ? 'true' : 'false');
        LOADING_CLASSES.forEach((className) => results.classList.toggle(className, isLoading));
    }

    function updateHistory(url, verified, push) {
        if (!push) {
            return;
        }

        if (url === withoutHash(window.location.href)) {
            window.history.replaceState(historyState(verified), '', url);

            return;
        }

        window.history.pushState(historyState(verified), '', url);
    }

    function revealResults(focusTarget) {
        focusTarget?.focus({ preventScroll: true });
        results.scrollIntoView({ block: 'start', behavior: prefersReducedMotion() ? 'auto' : 'smooth' });
    }

    function showError(url, push, moveFocus) {
        failedRequest = { url, push };
        announce('');

        if (errorTemplate) {
            results.replaceChildren(errorTemplate.content.cloneNode(true));
        } else {
            const message = document.createElement('p');
            message.setAttribute('role', 'alert');
            message.textContent = LOAD_ERROR_MESSAGE;
            results.replaceChildren(message);
        }

        if (moveFocus) {
            results.querySelector('[data-admin-maintenances-retry]')?.focus();
        }
    }

    async function loadMaintenances(url, { push = true } = {}) {
        const targetUrl = withoutHash(url);
        const focusWasInResults = results.contains(document.activeElement);

        activeRequest?.abort();
        const controller = new AbortController();
        activeRequest = controller;
        setLoading(true);

        try {
            const response = await fetch(targetUrl, {
                headers: {
                    Accept: 'text/html',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                // The fragment shares the page URL; never let the HTTP cache serve it on a later full load.
                cache: 'no-store',
                signal: controller.signal,
            });

            if (response.redirected) {
                // Expired session or similar: let the browser follow the redirect as a full page.
                window.location.assign(targetUrl);

                return;
            }

            if (!response.ok) {
                throw new Error(`Admin maintenances request failed with status ${response.status}`);
            }

            const html = await response.text();

            results.innerHTML = html;
            failedRequest = null;
            currentUrl = targetUrl;

            const verified = verifiedFromUrl(targetUrl);
            setActiveFilterButton(root, verified);
            syncSortInputs(root, targetUrl);
            updateHistory(targetUrl, verified, push);

            const summary = results.querySelector('[data-admin-maintenances-summary]');
            announce(summary?.dataset.statusText ?? '');

            if (focusWasInResults) {
                revealResults(summary);
            }
        } catch (error) {
            if (controller.signal.aborted) {
                return;
            }

            showError(targetUrl, push, focusWasInResults);
        } finally {
            if (activeRequest === controller) {
                activeRequest = null;
                setLoading(false);
            }
        }
    }

    setActiveFilterButton(root, root.dataset.verified ?? verifiedFromUrl(window.location.href));
    window.history.replaceState(historyState(root.dataset.verified), '', window.location.href);

    root.querySelectorAll('[data-maintenance-filter]').forEach((button) => {
        button.addEventListener('click', (event) => {
            // O botão também é submit do formulário (fallback sem JS): aqui a troca é no lugar.
            event.preventDefault();
            const verified = button.dataset.maintenanceFilter ?? '';
            loadMaintenances(buildFilterUrl(currentUrl, verified));
        });
    });

    root.addEventListener('click', (event) => {
        const retryButton = event.target.closest('[data-admin-maintenances-retry]');
        if (retryButton) {
            if (failedRequest) {
                loadMaintenances(failedRequest.url, { push: failedRequest.push });
            }

            return;
        }

        const link = event.target.closest('a');
        if (!link || !results.contains(link) || !isListNavigationLink(link) || isModifiedClick(event)) {
            return;
        }

        event.preventDefault();
        loadMaintenances(link.href);
    });

    window.addEventListener('popstate', () => {
        const url = withoutHash(window.location.href);

        if (new URL(url).pathname !== basePath) {
            return;
        }

        // A request started before Back/Forward must not push its URL over the entry the user moved to.
        activeRequest?.abort();

        // Hash-only entries (e.g. the skip link) keep the same list; nothing to reload.
        if (url === currentUrl && failedRequest === null) {
            return;
        }

        // The browser already moved through history, so restore without pushing a new entry.
        loadMaintenances(url, { push: false });
    });
}
