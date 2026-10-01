import { elementsWithin } from './dom';

/**
 * Abas de <x-ui.tabs> (padrão WAI-ARIA Tabs com ativação automática).
 *
 * - Clique ou seta esquerda/direita, Home e End trocam a aba; só a ativa fica com tabindex="0".
 * - A aba ativa tem aria-selected="true" e o painel dela fica sem o atributo hidden.
 * - Com data-ui-tabs-sync-url, a aba vai para o fragmento da URL (#id-do-painel) por
 *   history.replaceState, sem rolar, e o fragmento abre a aba ao carregar e no hashchange.
 * - Evento ui:tab-change (bubbles) no contêiner, com detail.panelId.
 *
 * O HSTabs do Preline não é carregado; as listas levam --prevent-on-load-init mesmo assim.
 */

const readyContainers = new WeakSet();

/**
 * @param {HTMLElement} container
 * @returns {HTMLElement[]}
 */
function tabsOf(container) {
    return Array.from(container.querySelectorAll('[role="tab"][data-ui-tab]'))
        .filter((tab) => tab.closest('[data-ui-tabs]') === container);
}

/**
 * @param {HTMLElement} tab
 */
function isEnabled(tab) {
    return !tab.hasAttribute('disabled') && tab.getAttribute('aria-disabled') !== 'true';
}

/**
 * @param {HTMLElement} container
 * @param {HTMLElement} selectedTab
 * @param {{ focus?: boolean, updateUrl?: boolean }} [options]
 */
function select(container, selectedTab, { focus = false, updateUrl = false } = {}) {
    tabsOf(container).forEach((tab) => {
        const isSelected = tab === selectedTab;
        const panel = document.getElementById(tab.getAttribute('aria-controls') ?? '');

        tab.setAttribute('aria-selected', isSelected ? 'true' : 'false');
        tab.tabIndex = isSelected ? 0 : -1;

        if (panel) {
            panel.hidden = !isSelected;
        }
    });

    if (focus) {
        selectedTab.focus();
    }

    const panelId = selectedTab.getAttribute('aria-controls') ?? '';

    if (updateUrl && container.hasAttribute('data-ui-tabs-sync-url') && panelId !== '') {
        const url = new URL(window.location.href);
        url.hash = panelId;
        window.history.replaceState(window.history.state, '', url);
    }

    container.dispatchEvent(new CustomEvent('ui:tab-change', { bubbles: true, detail: { panelId } }));
}

/**
 * @param {HTMLElement} container
 * @returns {HTMLElement|null}
 */
function tabFromHash(container) {
    const panelId = decodeURIComponent(window.location.hash.replace(/^#/, ''));

    if (panelId === '') {
        return null;
    }

    return tabsOf(container).find((tab) => tab.getAttribute('aria-controls') === panelId && isEnabled(tab)) ?? null;
}

/**
 * @param {HTMLElement} container
 */
function setupTabs(container) {
    if (readyContainers.has(container)) {
        return;
    }

    const tabs = tabsOf(container);
    const tablist = container.querySelector('[role="tablist"]');

    if (tabs.length === 0 || !tablist) {
        return;
    }

    readyContainers.add(container);

    const syncsUrl = container.hasAttribute('data-ui-tabs-sync-url');
    const hashTab = syncsUrl ? tabFromHash(container) : null;
    const initialTab = hashTab
        ?? tabs.find((tab) => tab.getAttribute('aria-selected') === 'true' && isEnabled(tab))
        ?? tabs.find(isEnabled);

    if (initialTab) {
        select(container, initialTab);
    }

    if (hashTab) {
        container.scrollIntoView({ block: 'start' });
    }

    tablist.addEventListener('click', (event) => {
        const tab = event.target instanceof Element ? event.target.closest('[role="tab"][data-ui-tab]') : null;

        if (tab && tabsOf(container).includes(tab) && isEnabled(tab)) {
            select(container, tab, { updateUrl: true });
        }
    });

    tablist.addEventListener('keydown', (event) => {
        if (event.altKey || event.ctrlKey || event.metaKey) {
            return;
        }

        const enabledTabs = tabsOf(container).filter(isEnabled);
        const currentIndex = enabledTabs.indexOf(document.activeElement);

        if (currentIndex === -1 || enabledTabs.length === 0) {
            return;
        }

        const nextIndex = {
            ArrowRight: (currentIndex + 1) % enabledTabs.length,
            ArrowLeft: (currentIndex - 1 + enabledTabs.length) % enabledTabs.length,
            Home: 0,
            End: enabledTabs.length - 1,
        }[event.key];

        if (nextIndex === undefined) {
            return;
        }

        event.preventDefault();
        select(container, enabledTabs[nextIndex], { focus: true, updateUrl: true });
    });

    if (syncsUrl) {
        window.addEventListener('hashchange', () => {
            const tab = tabFromHash(container);

            if (tab) {
                select(container, tab);
            }
        });
    }
}

/**
 * Liga as abas da página (ou de um trecho inserido depois). Idempotente.
 *
 * @param {ParentNode} [root]
 */
export function initTabs(root = document) {
    elementsWithin(root, '[data-ui-tabs]').forEach(setupTabs);
}
