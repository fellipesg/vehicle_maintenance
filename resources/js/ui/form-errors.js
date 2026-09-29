import { elementsWithin, prefersReducedMotion } from './dom';

/**
 * Resumo de erros (<x-ui.form-errors>). Com data-autofocus, o foco vai para o resumo na carga,
 * se o atributo autofocus nativo ainda não o levou e nada mais estiver focado. Os links do resumo
 * rolam até o campo e põem o foco no controle (um link âncora comum só rolaria).
 */

const SUMMARY_SELECTOR = '[data-slot="form-errors"]';

/**
 * Liga os resumos de root (e o próprio root). Idempotente.
 *
 * @param {ParentNode} [root]
 */
export function initFormErrors(root = document) {
    const summaries = elementsWithin(root, SUMMARY_SELECTOR).filter((summary) => summary instanceof HTMLElement);

    summaries.forEach(bindSummaryLinks);

    const summaryToFocus = summaries.find((summary) => summary.hasAttribute('data-autofocus'));
    const activeElement = document.activeElement;

    if (summaryToFocus && (activeElement === null || activeElement === document.body)) {
        summaryToFocus.focus();
    }
}

/**
 * @param {HTMLElement} summary
 */
function bindSummaryLinks(summary) {
    if (summary.dataset.formErrorsReady === 'true') {
        return;
    }

    summary.dataset.formErrorsReady = 'true';

    summary.addEventListener('click', (event) => {
        const link = event.target instanceof Element ? event.target.closest('a[data-form-errors-link]') : null;

        if (!(link instanceof HTMLAnchorElement) || link.hash.length < 2) {
            return;
        }

        const control = document.getElementById(decodeURIComponent(link.hash.slice(1)));

        if (!(control instanceof HTMLElement)) {
            return;
        }

        event.preventDefault();

        const scrollTarget = control.closest('[data-slot="field"], [data-slot="fieldset"]') ?? control;

        scrollTarget.scrollIntoView({ block: 'center', behavior: prefersReducedMotion() ? 'auto' : 'smooth' });
        control.focus({ preventScroll: true });
    });
}
