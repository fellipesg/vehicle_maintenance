/**
 * Procedência (contrato de .ai/rules/theme.md): Selo da oficina × Declarada.
 *
 * As telas são renderizadas no servidor (x-vehicle.detail, x-provenance-card, x-provenance-strip,
 * x-maintenance.list). Este módulo só acrescenta interatividade, sem montar HTML:
 * initProvenanceFilters() faz o filtro Todas / Selo da oficina / Declaradas da ficha filtrar no
 * lugar (atributo hidden, aria-pressed e contadores). Sem JS, o mesmo filtro é um GET (?verified=).
 */

const FILTER_ALL = '';
const FILTER_SEALED = '1';
const FILTER_DECLARED = '0';

/**
 * '1' (Selo da oficina), '0' (Declaradas) ou '' (Todas).
 *
 * @param {unknown} value
 * @returns {''|'1'|'0'}
 */
export function normalizeProvenanceFilter(value) {
    const text = value === null || value === undefined ? '' : String(value);

    return text === FILTER_SEALED || text === FILTER_DECLARED ? text : FILTER_ALL;
}

/**
 * @param {boolean|string} verified true/'1' para Selo da oficina
 * @param {unknown} filter
 */
export function matchesProvenanceFilter(verified, filter) {
    const isVerified = verified === true || verified === '1';
    const normalized = normalizeProvenanceFilter(filter);

    if (normalized === FILTER_SEALED) {
        return isVerified;
    }

    if (normalized === FILTER_DECLARED) {
        return !isVerified;
    }

    return true;
}

/**
 * Texto do contador da lista ("4 manutenções" ou "Mostrando 2 de 4 manutenções").
 *
 * @param {{ shown: number, filter: string, templateAll: string, templateFiltered: string }} options
 */
export function provenanceCountText({ shown, filter, templateAll, templateFiltered }) {
    return normalizeProvenanceFilter(filter) === FILTER_ALL
        ? templateAll
        : templateFiltered.replace('{shown}', String(shown));
}

/**
 * Índice visível mais próximo de fromIndex (primeiro à esquerda, depois à direita), ou -1.
 *
 * @param {boolean[]} visibility
 * @param {number} fromIndex
 */
export function nearestVisibleIndex(visibility, fromIndex) {
    if (visibility[fromIndex]) {
        return fromIndex;
    }

    for (let distance = 1; distance < visibility.length; distance += 1) {
        if (visibility[fromIndex - distance]) {
            return fromIndex - distance;
        }

        if (visibility[fromIndex + distance]) {
            return fromIndex + distance;
        }
    }

    return -1;
}

/**
 * @param {ParentNode} root
 * @param {string} selector
 * @returns {Element[]}
 */
function within(root, selector) {
    const found = typeof root?.querySelectorAll === 'function' ? Array.from(root.querySelectorAll(selector)) : [];

    if (typeof root?.matches === 'function' && root.matches(selector)) {
        found.unshift(root);
    }

    return found;
}

function replaceFilterInUrl(filter) {
    const url = new URL(window.location.href);

    if (filter === FILTER_ALL) {
        url.searchParams.delete('verified');
    } else {
        url.searchParams.set('verified', filter);
    }

    url.searchParams.delete('page');
    window.history.replaceState(window.history.state, '', url);
}

/**
 * Aplica o filtro na ficha: cards do histórico, colunas da linha do tempo, contadores, estado
 * vazio, botões (aria-pressed) e a URL.
 *
 * @param {HTMLElement} root [data-provenance-filter-root]
 * @param {unknown} value
 */
export function applyProvenanceFilter(root, value) {
    const filter = normalizeProvenanceFilter(value);
    const items = within(root, '[data-provenance-list] > [data-verified]');
    let shown = 0;

    // Liga o fade de 150ms dos cards que voltam à lista (app.css), só depois do primeiro filtro.
    root.setAttribute('data-provenance-filtered', '');

    items.forEach((item) => {
        const visible = matchesProvenanceFilter(item.getAttribute('data-verified'), filter);
        item.hidden = !visible;
        shown += visible ? 1 : 0;
    });

    within(root, '[data-timeline-grid]').forEach((grid) => {
        const columns = Array.from(grid.querySelectorAll('[data-timeline-column]'));
        const selectedIndex = columns.findIndex((column) => column.getAttribute('aria-selected') === 'true');
        const visibility = columns.map((column) => !column.hasAttribute('data-verified')
            || matchesProvenanceFilter(column.getAttribute('data-verified'), filter));

        columns.forEach((column, index) => {
            column.hidden = !visibility[index];
            column.disabled = !visibility[index];
        });

        const visibleCount = Math.max(1, visibility.filter(Boolean).length);
        grid.setAttribute('data-visible-count', String(visibleCount));

        grid.querySelectorAll('[data-timeline-rail], [data-timeline-progress]').forEach((line) => {
            line.style.left = `calc(100% / (2 * ${visibleCount}))`;
            if (line.hasAttribute('data-timeline-rail')) {
                line.style.right = `calc(100% / (2 * ${visibleCount}))`;
            }
        });
        grid.querySelectorAll('[data-timeline-progress]').forEach((progress) => {
            progress.hidden = filter !== FILTER_ALL;
        });

        // A coluna aberta saiu do filtro: abre a visível mais próxima (o clique passa por ui/tabs.js).
        if (selectedIndex !== -1 && !visibility[selectedIndex]) {
            const nextIndex = nearestVisibleIndex(visibility, selectedIndex);

            if (nextIndex !== -1) {
                columns[nextIndex].click();
            }
        }
    });

    within(root, 'form[data-provenance-filter-form] button[name="verified"]').forEach((button) => {
        button.setAttribute('aria-pressed', normalizeProvenanceFilter(button.value) === filter ? 'true' : 'false');
    });

    within(root, '[data-provenance-count]').forEach((counter) => {
        counter.textContent = provenanceCountText({
            shown,
            filter,
            templateAll: counter.getAttribute('data-template-all') ?? '',
            templateFiltered: counter.getAttribute('data-template-filtered') ?? '{shown}',
        });
    });

    within(root, '[data-provenance-empty]').forEach((empty) => {
        empty.hidden = !(filter !== FILTER_ALL && shown === 0 && items.length > 0);
        const title = empty.querySelector('[data-slot="empty-state-title"]');
        const text = filter === FILTER_DECLARED ? empty.getAttribute('data-title-declared') : empty.getAttribute('data-title-sealed');

        if (title && text) {
            title.textContent = text;
        }
    });

    replaceFilterInUrl(filter);
    root.dispatchEvent(new CustomEvent('provenance:filter-change', { bubbles: true, detail: { filter, shown, total: items.length } }));

    return shown;
}

/**
 * Liga o filtro de procedência da ficha (x-vehicle.detail). Idempotente.
 *
 * @param {ParentNode} [root]
 */
export function initProvenanceFilters(root = document) {
    within(root, '[data-provenance-filter-root]').forEach((filterRoot) => {
        if (filterRoot.dataset.provenanceFilterReady === 'true') {
            return;
        }

        filterRoot.dataset.provenanceFilterReady = 'true';

        filterRoot.addEventListener('click', (event) => {
            const target = event.target instanceof Element ? event.target : null;
            const button = target?.closest('form[data-provenance-filter-form] button[name="verified"]');
            const clear = target?.closest('[data-provenance-clear]');

            if (button && filterRoot.contains(button)) {
                event.preventDefault();
                applyProvenanceFilter(filterRoot, button.value);

                return;
            }

            if (clear && filterRoot.contains(clear)) {
                event.preventDefault();
                applyProvenanceFilter(filterRoot, FILTER_ALL);
                filterRoot.querySelector('form[data-provenance-filter-form] button[name="verified"][value=""]')?.focus();
            }
        });

        // Envio sem clique no botão (Enter): mantém o filtro no lugar.
        filterRoot.addEventListener('submit', (event) => {
            const form = event.target instanceof HTMLFormElement ? event.target : null;

            if (form?.matches('[data-provenance-filter-form]')) {
                event.preventDefault();
                applyProvenanceFilter(filterRoot, event.submitter?.value ?? FILTER_ALL);
            }
        });
    });
}
