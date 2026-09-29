/**
 * Estado de envio dos formulários: depois do submit, o botão que enviou fica ocupado até a página
 * trocar, e um segundo envio do mesmo formulário é barrado (clique duplo em "Criar conta").
 *
 * No botão que enviou (ou no primeiro botão de envio, no Enter de um campo):
 * - disabled e aria-busy="true";
 * - o rótulo ([data-slot="label"] de <x-ui.button>) vira o data-loading-label ("Entrando…");
 * - o ícone dá lugar ao mesmo círculo de <x-ui.spinner>, que só gira com motion-safe.
 *
 * Fica de fora o envio cancelado por outro script (event.defaultPrevented, como o data-confirm antes
 * de "Confirmar" ou uma busca feita por fetch), o formulário com target de outra janela, o
 * method="dialog" e o formulário com data-submit-busy="off" (ex.: resposta que baixa um arquivo e
 * não troca de página). Voltar pelo histórico (bfcache) libera tudo de novo. Idempotente.
 */

const BUSY_ATTRIBUTE = 'data-submit-busy';
const SUBMIT_BUTTONS = 'button[type="submit"], button:not([type]), input[type="submit"]';
const SPINNER_SVG = '<svg class="motion-safe:animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4Z"></path></svg>';

/** Rótulo e ícone de cada botão ocupado, para devolver no pageshow. */
const originals = new WeakMap();
let listenersReady = false;

/**
 * @param {HTMLFormElement} form
 * @returns {boolean}
 */
function tracksForm(form) {
    const target = (form.getAttribute('target') ?? '').toLowerCase();

    return form.getAttribute(BUSY_ATTRIBUTE) !== 'off'
        && (form.getAttribute('method') ?? '').toLowerCase() !== 'dialog'
        && ['', '_self', '_top', '_parent'].includes(target);
}

/**
 * @param {HTMLFormElement} form
 * @param {HTMLElement|null} submitter
 * @returns {HTMLButtonElement|HTMLInputElement|null}
 */
function busyButtonFor(form, submitter) {
    if ((submitter instanceof HTMLButtonElement || submitter instanceof HTMLInputElement) && submitter.form === form) {
        return submitter;
    }

    return Array.from(form.elements).find((element) => element.matches?.(SUBMIT_BUTTONS)) ?? null;
}

/**
 * @param {HTMLElement} icon ícone do botão, cujo tamanho o círculo copia
 * @returns {HTMLSpanElement}
 */
function spinnerLike(icon) {
    const spinner = document.createElement('span');
    spinner.className = 'inline-flex shrink-0 items-center justify-center';
    spinner.dataset.slot = 'spinner';
    spinner.dataset.submitBusySpinner = '';
    spinner.setAttribute('aria-hidden', 'true');
    spinner.innerHTML = SPINNER_SVG;

    const size = Array.from(icon?.classList ?? []).find((name) => /^size-\d+$/.test(name)) ?? 'size-5';
    spinner.firstElementChild?.classList.add(size);

    return spinner;
}

/**
 * @param {HTMLFormElement} form
 * @param {HTMLButtonElement|HTMLInputElement|null} button
 */
function markBusy(form, button) {
    if (!form.isConnected || !button || button.hasAttribute(BUSY_ATTRIBUTE)) {
        return;
    }

    const label = button instanceof HTMLButtonElement ? button.querySelector('[data-slot="label"]') : null;
    const icon = button instanceof HTMLButtonElement ? button.querySelector(':scope > [data-slot="icon"]') : null;
    const loadingLabel = button.getAttribute('data-loading-label');

    originals.set(button, { labelText: label?.textContent ?? null, icon });

    button.setAttribute(BUSY_ATTRIBUTE, '');
    button.setAttribute('aria-busy', 'true');
    button.disabled = true;

    if (label && loadingLabel) {
        label.textContent = loadingLabel;
    }

    if (button instanceof HTMLButtonElement) {
        const spinner = spinnerLike(icon);

        if (icon) {
            icon.setAttribute('hidden', '');
            icon.before(spinner);
        } else {
            button.prepend(spinner);
        }
    }
}

/**
 * Devolve os botões e formulários ao estado de antes do envio. O botão que enviou estava habilitado
 * (desabilitado ele não envia), então volta habilitado e sem aria-busy.
 */
function releaseAll() {
    document.querySelectorAll(`form[${BUSY_ATTRIBUTE}="submitting"]`).forEach((form) => {
        form.removeAttribute(BUSY_ATTRIBUTE);
    });

    document.querySelectorAll(`button[${BUSY_ATTRIBUTE}], input[${BUSY_ATTRIBUTE}]`).forEach((button) => {
        const original = originals.get(button);
        const label = button.querySelector('[data-slot="label"]');

        button.removeAttribute(BUSY_ATTRIBUTE);
        button.removeAttribute('aria-busy');
        button.disabled = false;
        button.querySelectorAll('[data-submit-busy-spinner]').forEach((spinner) => spinner.remove());

        if (label && original?.labelText != null) {
            label.textContent = original.labelText;
        }

        original?.icon?.removeAttribute('hidden');

        originals.delete(button);
    });
}

/**
 * @param {SubmitEvent} event
 */
function onSubmit(event) {
    const form = event.target;

    if (event.defaultPrevented || !(form instanceof HTMLFormElement) || !tracksForm(form)) {
        return;
    }

    // Segundo envio enquanto o primeiro ainda carrega (clique duplo, outro botão, Enter num campo).
    if (form.getAttribute(BUSY_ATTRIBUTE) === 'submitting') {
        event.preventDefault();

        return;
    }

    const button = busyButtonFor(form, event.submitter ?? null);

    form.setAttribute(BUSY_ATTRIBUTE, 'submitting');

    // Depois do despacho: outro ouvinte pode ter cancelado o envio (aí o formulário volta a aceitar
    // envio), e o botão só pode ser desabilitado depois de o navegador montar os dados (desabilitado,
    // o name/value dele sairia do envio).
    window.setTimeout(() => {
        if (event.defaultPrevented) {
            form.removeAttribute(BUSY_ATTRIBUTE);

            return;
        }

        markBusy(form, button);
    });
}

export function initSubmitBusy() {
    if (listenersReady) {
        return;
    }

    listenersReady = true;

    document.addEventListener('submit', onSubmit);
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            releaseAll();
        }
    });
}
