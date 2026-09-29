import { elementsWithin } from './dom';

/**
 * <x-ui.switch> é um checkbox nativo com role="switch": o navegador já expõe o estado marcado. O
 * aria-checked que o servidor escreve acompanha aqui cada mudança (e o reset do formulário), para
 * não ficar valendo o estado da carga.
 */

const SWITCH_SELECTOR = 'input[type="checkbox"][role="switch"]';

/**
 * Liga os switches de root (e o próprio root). Idempotente.
 *
 * @param {ParentNode} [root]
 */
export function initSwitches(root = document) {
    elementsWithin(root, SWITCH_SELECTOR).forEach(bindSwitch);
}

/**
 * @param {Element} input
 */
function bindSwitch(input) {
    if (!(input instanceof HTMLInputElement) || input.dataset.switchReady === 'true') {
        return;
    }

    input.dataset.switchReady = 'true';

    const sync = () => {
        input.setAttribute('aria-checked', input.checked ? 'true' : 'false');
    };

    input.addEventListener('change', sync);
    input.form?.addEventListener('reset', () => {
        // O reset troca o checked depois do evento.
        window.setTimeout(sync);
    });
    sync();
}
