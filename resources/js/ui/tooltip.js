import { elementsWithin } from './dom';

/**
 * Complemento das dicas de <x-ui.tooltip>, que abrem e fecham em CSS (hover e foco):
 * - Esc fecha a dica aberta sem mover o foco (WCAG 1.4.13) e não fecha o diálogo em volta; ela
 *   volta a abrir quando o ponteiro sai e entra de novo ou quando o foco sai e volta;
 * - a dica que sairia da lateral da tela é deslocada para caber, com 8px de margem.
 */

const VIEWPORT_MARGIN = 8;
const readyTooltips = new WeakSet();
let escapeListenerReady = false;

/**
 * @param {HTMLElement} wrapper
 */
function fitInViewport(wrapper) {
    const content = wrapper.querySelector('[data-ui-tooltip-content]');

    if (!(content instanceof HTMLElement)) {
        return;
    }

    content.style.marginLeft = '';

    const rect = content.getBoundingClientRect();
    const viewportWidth = document.documentElement.clientWidth;

    if (rect.left < VIEWPORT_MARGIN) {
        content.style.marginLeft = `${VIEWPORT_MARGIN - rect.left}px`;
    } else if (rect.right > viewportWidth - VIEWPORT_MARGIN) {
        content.style.marginLeft = `${viewportWidth - VIEWPORT_MARGIN - rect.right}px`;
    }
}

/**
 * @param {HTMLElement} wrapper
 */
function setupTooltip(wrapper) {
    if (readyTooltips.has(wrapper)) {
        return;
    }

    readyTooltips.add(wrapper);

    const reset = () => {
        delete wrapper.dataset.tooltipDismissed;
    };

    wrapper.addEventListener('mouseenter', () => fitInViewport(wrapper));
    wrapper.addEventListener('focusin', () => fitInViewport(wrapper));
    wrapper.addEventListener('mouseleave', () => {
        if (!wrapper.matches(':focus-within')) {
            reset();
        }
    });
    wrapper.addEventListener('focusout', (event) => {
        if (!wrapper.contains(event.relatedTarget) && !wrapper.matches(':hover')) {
            reset();
        }
    });
}

/**
 * @param {KeyboardEvent} event
 */
function dismissOnEscape(event) {
    if (event.key !== 'Escape') {
        return;
    }

    const openTooltips = Array.from(document.querySelectorAll('[data-ui-tooltip]'))
        .filter((wrapper) => {
            const content = wrapper.querySelector('[data-ui-tooltip-content]');

            return !wrapper.hasAttribute('data-tooltip-dismissed')
                && content instanceof HTMLElement
                && window.getComputedStyle(content).visibility === 'visible';
        });

    if (openTooltips.length === 0) {
        return;
    }

    openTooltips.forEach((wrapper) => {
        wrapper.dataset.tooltipDismissed = '';
    });
    event.preventDefault();
    event.stopPropagation();
}

/**
 * Liga as dicas da página (ou de um trecho inserido depois). Idempotente.
 *
 * @param {ParentNode} [root]
 */
export function initTooltips(root = document) {
    elementsWithin(root, '[data-ui-tooltip]').forEach(setupTooltip);

    if (escapeListenerReady) {
        return;
    }

    escapeListenerReady = true;

    // Captura: a dica fecha antes do Esc chegar ao diálogo, ao dropdown ou ao sheet em volta.
    document.addEventListener('keydown', dismissOnEscape, true);
}
