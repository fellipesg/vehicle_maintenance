import { elementsWithin } from './dom';

/**
 * Sidebar recolhível do admin (<x-ui.sidebar-toggle>, layouts.admin).
 *
 * - O estado é data-sidebar="collapsed" no <html>, que o layout já aplica antes da pintura a partir
 *   do localStorage. O botão alterna, grava a escolha (try/catch: sem armazenamento a escolha só
 *   não é lembrada) e mantém aria-pressed.
 * - Recolhida (a partir de md), os rótulos ficam só para leitor de tela; aqui entra a dica visual
 *   com o nome do item ao passar o ponteiro (depois de 300ms) ou ao focar pelo teclado. A dica
 *   fica aberta enquanto o ponteiro passa do item para ela, some com Esc e ao sair (WCAG 1.4.13).
 *   É só visual (aria-hidden): o nome acessível do item continua o do link.
 *
 * Idempotente: pode ser chamado de novo com o trecho novo como raiz.
 */

export const SIDEBAR_COLLAPSED = 'collapsed';
export const SIDEBAR_EXPANDED = 'expanded';

const DESKTOP_QUERY = '(min-width: 48rem)';
const HOVER_DELAY_MS = 300;
const HIDE_DELAY_MS = 120;
const TOOLTIP_GAP = 8;
const TOOLTIP_TARGETS = 'a[href], button[aria-label]';

let tooltip = null;
let tooltipAnchor = null;
let pendingAnchor = null;
let showTimer = 0;
let hideTimer = 0;
let documentListenersReady = false;

/**
 * Lê o estado salvo. Qualquer erro (armazenamento bloqueado, modo privado) vale como expandida.
 *
 * @param {Storage|null|undefined} storage
 * @param {string} key
 * @returns {'collapsed'|'expanded'}
 */
export function readSidebarState(storage, key) {
    try {
        return storage?.getItem(key) === SIDEBAR_COLLAPSED ? SIDEBAR_COLLAPSED : SIDEBAR_EXPANDED;
    } catch {
        return SIDEBAR_EXPANDED;
    }
}

/**
 * Grava o estado. Devolve false quando o navegador não deixa gravar.
 *
 * @param {Storage|null|undefined} storage
 * @param {string} key
 * @param {'collapsed'|'expanded'} state
 * @returns {boolean}
 */
export function writeSidebarState(storage, key, state) {
    try {
        if (!storage) {
            return false;
        }

        storage.setItem(key, state === SIDEBAR_COLLAPSED ? SIDEBAR_COLLAPSED : SIDEBAR_EXPANDED);

        return true;
    } catch {
        return false;
    }
}

/**
 * O próprio acesso a window.localStorage pode lançar (cookies bloqueados).
 *
 * @returns {Storage|null}
 */
function browserStorage() {
    try {
        return window.localStorage;
    } catch {
        return null;
    }
}

function isCollapsed() {
    return document.documentElement.dataset.sidebar === SIDEBAR_COLLAPSED;
}

function isDesktop() {
    return typeof window.matchMedia === 'function' && window.matchMedia(DESKTOP_QUERY).matches;
}

/**
 * @param {'collapsed'|'expanded'} state
 */
function applyState(state) {
    if (state === SIDEBAR_COLLAPSED) {
        document.documentElement.dataset.sidebar = SIDEBAR_COLLAPSED;
    } else {
        delete document.documentElement.dataset.sidebar;
    }

    document.querySelectorAll('[data-sidebar-toggle]').forEach((button) => {
        button.setAttribute('aria-pressed', state === SIDEBAR_COLLAPSED ? 'true' : 'false');
    });

    hideTooltip();
}

function ensureTooltip() {
    if (tooltip?.isConnected) {
        return tooltip;
    }

    tooltip = document.createElement('div');
    tooltip.setAttribute('role', 'tooltip');
    tooltip.setAttribute('aria-hidden', 'true');
    tooltip.hidden = true;
    tooltip.dataset.sidebarTooltip = '';
    tooltip.className = 'theme-default fixed z-[70] max-w-60 rounded-control bg-foreground px-2.5 py-1.5 text-xs font-medium text-background shadow-md';
    tooltip.addEventListener('pointerenter', () => window.clearTimeout(hideTimer));
    tooltip.addEventListener('pointerleave', () => scheduleHide());
    document.body.append(tooltip);

    return tooltip;
}

/**
 * @param {Element} anchor
 * @returns {string}
 */
function labelFor(anchor) {
    return (anchor.getAttribute('aria-label') || anchor.textContent || '').replace(/\s+/g, ' ').trim();
}

/**
 * @param {Element} anchor
 */
function showTooltip(anchor) {
    const label = labelFor(anchor);

    if (!isCollapsed() || !isDesktop() || label === '') {
        return;
    }

    const element = ensureTooltip();
    window.clearTimeout(hideTimer);
    tooltipAnchor = anchor;
    pendingAnchor = null;
    element.textContent = label;
    element.hidden = false;

    const rect = anchor.getBoundingClientRect();
    const height = element.offsetHeight;
    element.style.left = `${Math.round(rect.right + TOOLTIP_GAP)}px`;
    element.style.top = `${Math.round(rect.top + (rect.height - height) / 2)}px`;
}

function hideTooltip() {
    window.clearTimeout(showTimer);
    window.clearTimeout(hideTimer);
    tooltipAnchor = null;
    pendingAnchor = null;

    if (tooltip) {
        tooltip.hidden = true;
    }
}

function scheduleHide() {
    window.clearTimeout(showTimer);
    window.clearTimeout(hideTimer);
    pendingAnchor = null;
    hideTimer = window.setTimeout(hideTooltip, HIDE_DELAY_MS);
}

/**
 * @param {HTMLElement} sidebar
 */
function bindTooltips(sidebar) {
    if (sidebar.dataset.sidebarTooltipsReady === 'true') {
        return;
    }

    sidebar.dataset.sidebarTooltipsReady = 'true';

    sidebar.addEventListener('pointerover', (event) => {
        const anchor = event.target instanceof Element ? event.target.closest(TOOLTIP_TARGETS) : null;

        if (!anchor || !sidebar.contains(anchor)) {
            return;
        }

        if (anchor === tooltipAnchor || anchor === pendingAnchor) {
            window.clearTimeout(hideTimer);

            return;
        }

        pendingAnchor = anchor;
        window.clearTimeout(showTimer);
        window.clearTimeout(hideTimer);
        showTimer = window.setTimeout(() => showTooltip(anchor), tooltipAnchor ? 0 : HOVER_DELAY_MS);
    });

    sidebar.addEventListener('pointerout', (event) => {
        const leaving = event.target instanceof Element ? event.target.closest(TOOLTIP_TARGETS) : null;

        if (leaving && !(event.relatedTarget instanceof Node && leaving.contains(event.relatedTarget))) {
            scheduleHide();
        }
    });

    sidebar.addEventListener('focusin', (event) => {
        const anchor = event.target instanceof Element ? event.target.closest(TOOLTIP_TARGETS) : null;

        if (anchor && anchor.matches(':focus-visible')) {
            showTooltip(anchor);
        }
    });

    sidebar.addEventListener('focusout', () => hideTooltip());
    sidebar.addEventListener('scroll', () => hideTooltip(), { capture: true, passive: true });
}

/**
 * @param {ParentNode} [root]
 */
export function initSidebarToggles(root = document) {
    elementsWithin(root, '[data-sidebar-toggle]').forEach((button) => {
        if (!(button instanceof HTMLElement) || button.dataset.sidebarToggleReady === 'true') {
            return;
        }

        button.dataset.sidebarToggleReady = 'true';

        const key = button.dataset.sidebarStorageKey || 'revisalog:admin-sidebar';
        const sidebar = document.getElementById(button.dataset.sidebarToggle || '');

        applyState(isCollapsed() ? SIDEBAR_COLLAPSED : readSidebarState(browserStorage(), key));

        if (sidebar instanceof HTMLElement) {
            bindTooltips(sidebar);
        }

        button.addEventListener('click', () => {
            const next = isCollapsed() ? SIDEBAR_EXPANDED : SIDEBAR_COLLAPSED;

            applyState(next);
            writeSidebarState(browserStorage(), key, next);
        });
    });

    if (documentListenersReady) {
        return;
    }

    documentListenersReady = true;

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && tooltip && !tooltip.hidden) {
            hideTooltip();
        }
    });
}
