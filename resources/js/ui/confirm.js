import { buttonClass, setButtonVariant } from './button';
import { openDialog } from './dialog';

/**
 * Confirmação com o diálogo do app (<x-ui.confirm-dialog>), no lugar da caixa nativa do navegador.
 *
 * Declarativo, com data-attributes no elemento que dispara a ação:
 * - <form data-confirm="..."> ou <button type="submit" data-confirm="...">: o envio só segue depois
 *   de "Confirmar" (reenvia com requestSubmit, preservando o botão que enviou);
 * - <a data-confirm="..."> e <button type="button" data-confirm="...">: o clique só segue depois
 *   de "Confirmar".
 * Opcionais: data-confirm-title, data-confirm-action-label, data-confirm-cancel-label e
 * data-confirm-variant="danger".
 *
 * Programático: const ok = await confirmAction({ title, message, confirmLabel, cancelLabel, variant }),
 * também em window.revisalogConfirm.
 */

const DEFAULTS = {
    title: 'Confirmar ação',
    message: 'Deseja continuar?',
    confirmLabel: 'Confirmar',
    cancelLabel: 'Cancelar',
    variant: 'default',
};

const CONFIRMED_VALUE = 'confirmar';
const DIALOG_CLASSES = 'theme-default relative m-auto w-[calc(100%-2rem)] max-w-sm max-h-[calc(100dvh-2rem)] overflow-y-auto rounded-overlay border border-border bg-surface p-6 text-left text-foreground shadow-xl backdrop:bg-overlay';

/** Elementos cuja próxima ação já foi confirmada e deve seguir sem nova pergunta. */
const approved = new WeakSet();
let pendingResolve = null;
let listenersReady = false;

/**
 * Cria o diálogo quando o layout não trouxe <x-ui.confirm-dialog />, com a mesma estrutura dele.
 *
 * @returns {HTMLDialogElement}
 */
function buildDialog() {
    const dialog = document.createElement('dialog');
    dialog.id = 'confirmacao';
    dialog.className = DIALOG_CLASSES;
    dialog.setAttribute('role', 'alertdialog');
    dialog.setAttribute('aria-labelledby', 'confirmacao-titulo');
    dialog.setAttribute('aria-describedby', 'confirmacao-descricao');
    dialog.dataset.uiDialog = '';
    dialog.dataset.uiConfirmDialog = '';
    dialog.dataset.dismissible = 'true';
    dialog.dataset.closeOnBackdrop = 'false';
    dialog.innerHTML = `
        <div class="min-w-0">
            <h2 id="confirmacao-titulo" class="text-lg font-semibold leading-snug text-foreground"></h2>
            <p id="confirmacao-descricao" class="mt-1.5 text-sm text-muted-foreground"></p>
        </div>
        <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <button type="button" class="${buttonClass('secondary')}" data-slot="button" data-variant="secondary" data-confirm-cancel data-dialog-close="cancelar" data-dialog-initial-focus><span data-slot="label"></span></button>
            <button type="button" class="${buttonClass('primary')}" data-slot="button" data-variant="primary" data-confirm-accept data-dialog-close="${CONFIRMED_VALUE}"><span data-slot="label"></span></button>
        </div>`;
    document.body.append(dialog);

    return dialog;
}

/**
 * @returns {HTMLDialogElement}
 */
function confirmDialog() {
    const existing = document.querySelector('dialog[data-ui-confirm-dialog]');

    return existing instanceof HTMLDialogElement ? existing : buildDialog();
}

/**
 * Abre o diálogo de confirmação e resolve true só com "Confirmar". Esc, "Cancelar" ou outra
 * confirmação pedida por cima resolvem false.
 *
 * @param {{ title?: string, message?: string, confirmLabel?: string, cancelLabel?: string, variant?: 'default'|'danger', opener?: Element|null }} [options]
 * @returns {Promise<boolean>}
 */
export function confirmAction(options = {}) {
    const settings = { ...DEFAULTS, ...Object.fromEntries(Object.entries(options).filter(([, value]) => value !== undefined && value !== null && value !== '')) };
    const dialog = confirmDialog();
    const title = dialog.querySelector(`#${CSS.escape(dialog.getAttribute('aria-labelledby') ?? '')}`);
    const message = dialog.querySelector(`#${CSS.escape(dialog.getAttribute('aria-describedby') ?? '')}`);
    const accept = dialog.querySelector('[data-confirm-accept]');
    const cancel = dialog.querySelector('[data-confirm-cancel]');
    const isDanger = settings.variant === 'danger';

    if (title) {
        title.textContent = settings.title;
    }

    if (message) {
        message.textContent = settings.message;
    }

    if (accept instanceof HTMLElement) {
        (accept.querySelector('[data-slot="label"]') ?? accept).textContent = settings.confirmLabel;
        setButtonVariant(accept, isDanger ? 'danger' : 'primary');
    }

    if (cancel) {
        (cancel.querySelector('[data-slot="label"]') ?? cancel).textContent = settings.cancelLabel;
    }

    // Uma confirmação pedida com outra aberta substitui a anterior, que resolve false. O diálogo
    // continua aberto (fechar e reabrir faria o fechamento antigo responder à nova pergunta).
    if (pendingResolve) {
        pendingResolve(false);
        pendingResolve = null;
    }

    return new Promise((resolve) => {
        pendingResolve = resolve;

        dialog.addEventListener('ui:dialog-close', (event) => {
            if (pendingResolve === resolve) {
                pendingResolve = null;
            }

            resolve(event.detail?.returnValue === CONFIRMED_VALUE);
        }, { once: true });

        if (dialog.open) {
            (dialog.querySelector('[data-dialog-initial-focus]') ?? cancel)?.focus();
        } else {
            openDialog(dialog, { opener: settings.opener ?? document.activeElement });
        }
    });
}

/**
 * @param {Element} element
 */
function optionsFrom(element) {
    return {
        title: element.getAttribute('data-confirm-title'),
        message: element.getAttribute('data-confirm'),
        confirmLabel: element.getAttribute('data-confirm-action-label'),
        cancelLabel: element.getAttribute('data-confirm-cancel-label'),
        variant: element.getAttribute('data-confirm-variant') === 'danger' ? 'danger' : 'default',
    };
}

/**
 * Reenvia o formulário depois da confirmação e, se o envio seguiu, marca o botão como ocupado
 * para evitar um segundo clique.
 *
 * @param {HTMLFormElement} form
 * @param {HTMLElement|null} submitter
 */
function resubmit(form, submitter) {
    let submitEvent = null;
    const watchSubmit = (event) => {
        if (event.target === form) {
            submitEvent = event;
        }
    };

    approved.add(form);
    window.addEventListener('submit', watchSubmit);

    try {
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit(submitter && 'form' in submitter && submitter.form === form ? submitter : undefined);
        } else {
            form.submit();
        }
    } finally {
        window.removeEventListener('submit', watchSubmit);
        approved.delete(form);
    }

    if (submitEvent && !submitEvent.defaultPrevented && submitter instanceof HTMLButtonElement) {
        submitter.setAttribute('aria-busy', 'true');
        submitter.setAttribute('data-confirm-busy', '');
        // Depois do envio montar os dados: botão desabilitado não entraria no formulário.
        window.setTimeout(() => {
            submitter.disabled = true;
        });
    }
}

/**
 * Voltar para a página pelo histórico (bfcache) traz o botão ainda desabilitado: libera de novo.
 *
 * @param {PageTransitionEvent} event
 */
function releaseBusyButtons(event) {
    if (!event.persisted) {
        return;
    }

    document.querySelectorAll('[data-confirm-busy]').forEach((button) => {
        button.removeAttribute('data-confirm-busy');
        button.removeAttribute('aria-busy');
        button.disabled = false;
    });
}

/**
 * @param {SubmitEvent} event
 */
function onSubmit(event) {
    const form = event.target;

    if (!(form instanceof HTMLFormElement) || approved.has(form)) {
        return;
    }

    const submitter = event.submitter instanceof HTMLElement ? event.submitter : null;
    const source = submitter?.hasAttribute('data-confirm') ? submitter : (form.hasAttribute('data-confirm') ? form : null);

    if (!source) {
        return;
    }

    event.preventDefault();
    event.stopImmediatePropagation();

    confirmAction({ ...optionsFrom(source), opener: submitter ?? document.activeElement }).then((confirmed) => {
        if (confirmed) {
            resubmit(form, submitter);
        }
    });
}

/**
 * Links e botões comuns com data-confirm. Botão de envio fica para onSubmit, que conhece o form.
 *
 * @param {MouseEvent} event
 */
function onClick(event) {
    const trigger = event.target instanceof Element ? event.target.closest('[data-confirm]') : null;

    if (!trigger || trigger instanceof HTMLFormElement) {
        return;
    }

    const isSubmitButton = (trigger instanceof HTMLButtonElement || trigger instanceof HTMLInputElement)
        && trigger.type === 'submit'
        && trigger.form !== null;

    if (isSubmitButton) {
        return;
    }

    if (approved.has(trigger)) {
        approved.delete(trigger);

        return;
    }

    event.preventDefault();
    event.stopImmediatePropagation();

    confirmAction({ ...optionsFrom(trigger), opener: trigger }).then((confirmed) => {
        if (confirmed && trigger.isConnected) {
            approved.add(trigger);
            trigger.click();
        }
    });
}

/**
 * Liga a confirmação por data-attributes. Idempotente.
 */
export function initConfirm() {
    window.revisalogConfirm = confirmAction;

    if (listenersReady) {
        return;
    }

    listenersReady = true;

    // Captura no document: roda antes dos outros ouvintes de submit e de clique da página.
    document.addEventListener('submit', onSubmit, true);
    document.addEventListener('click', onClick, true);
    window.addEventListener('pageshow', releaseBusyButtons);
}
