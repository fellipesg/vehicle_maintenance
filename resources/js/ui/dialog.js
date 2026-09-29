import { elementsWithin } from './dom';

/**
 * Diálogos <dialog data-ui-dialog> de <x-ui.dialog> e <x-ui.confirm-dialog>.
 *
 * O <dialog> nativo com showModal() já deixa a página inerte, prende o foco, fecha com Esc e fica
 * no top layer. Aqui entram só as ligações:
 * - [data-dialog-open="id"] abre o diálogo (clique delegado, vale para HTML inserido depois);
 * - [data-dialog-close] dentro do diálogo fecha; o valor do atributo vira o returnValue;
 * - clique no fundo fecha quando data-close-on-backdrop="true";
 * - data-dismissible="false" impede o Esc;
 * - foco inicial em [data-dialog-initial-focus] ou [autofocus], senão no primeiro controle (o "x"
 *   de fechar, [data-dialog-close-button], só quando não há outro);
 * - ao fechar, o foco volta a quem abriu e a rolagem da página é liberada;
 * - data-dialog-open-on-load abre o diálogo na carga, uma vez só (formulário dentro do diálogo que
 *   voltou do servidor com erro), com o foco voltando ao gatilho [data-dialog-open="id"] ao fechar.
 *
 * Eventos (com bubbles): ui:dialog-open e ui:dialog-close (detail.returnValue) no <dialog>. Use
 * ui:dialog-close, não o close nativo: o Chrome 154 deixa de disparar close depois que um diálogo
 * foi fechado com Esc. Aqui o fechamento é detectado pelo atributo open (MutationObserver), que
 * vale para qualquer caminho: Esc, botão, form method="dialog" ou dialog.close() de outro script.
 */

const FOCUSABLE_SELECTOR = [
    'a[href]',
    'button:not([disabled])',
    'input:not([disabled]):not([type="hidden"])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])',
].join(', ');

const boundDialogs = new WeakSet();
/** Diálogos abertos por openDialog(), com quem devolve o foco ao fechar. */
const openers = new WeakMap();
let delegatedListenersReady = false;
let previousRootOverflow = null;

/**
 * @param {HTMLDialogElement|string|null|undefined} target Elemento, id ou "#id".
 * @returns {HTMLDialogElement|null}
 */
function resolveDialog(target) {
    if (target instanceof HTMLDialogElement) {
        return target;
    }

    const id = String(target ?? '').replace(/^#/, '');
    const element = id !== '' ? document.getElementById(id) : null;

    return element instanceof HTMLDialogElement ? element : null;
}

function syncScrollLock() {
    const hasOpenModal = document.querySelector('dialog[data-ui-dialog][open]') !== null;
    const rootStyle = document.documentElement.style;

    if (hasOpenModal && previousRootOverflow === null) {
        previousRootOverflow = rootStyle.overflow;
        rootStyle.overflow = 'hidden';
    } else if (!hasOpenModal && previousRootOverflow !== null) {
        rootStyle.overflow = previousRootOverflow;
        previousRootOverflow = null;
    }
}

/**
 * @param {HTMLDialogElement} dialog
 * @param {MouseEvent|PointerEvent} event
 * @returns {boolean}
 */
function isOnBackdrop(dialog, event) {
    if (event.target !== dialog) {
        return false;
    }

    const rect = dialog.getBoundingClientRect();

    return event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom;
}

/**
 * @param {HTMLDialogElement} dialog
 */
function focusInitialControl(dialog) {
    const preferred = dialog.querySelector('[data-dialog-initial-focus], [autofocus]');
    const firstControl = Array.from(dialog.querySelectorAll(FOCUSABLE_SELECTOR))
        .find((element) => !element.hasAttribute('data-dialog-close-button') && element.getClientRects().length > 0);
    const target = preferred ?? firstControl ?? dialog.querySelector('[data-dialog-close-button]');

    if (target instanceof HTMLElement) {
        target.focus();

        return;
    }

    dialog.setAttribute('tabindex', '-1');
    dialog.focus();
}

/**
 * @param {HTMLDialogElement} dialog
 */
function bindDialog(dialog) {
    if (boundDialogs.has(dialog)) {
        return;
    }

    boundDialogs.add(dialog);

    let pressStartedOnBackdrop = false;

    dialog.addEventListener('pointerdown', (event) => {
        pressStartedOnBackdrop = isOnBackdrop(dialog, event);
    });

    dialog.addEventListener('click', (event) => {
        const closer = event.target instanceof Element ? event.target.closest('[data-dialog-close]') : null;

        if (closer && dialog.contains(closer)) {
            event.preventDefault();
            closeDialog(dialog, closer.getAttribute('data-dialog-close') ?? '');

            return;
        }

        const startedOnBackdrop = pressStartedOnBackdrop;
        pressStartedOnBackdrop = false;

        if (dialog.dataset.closeOnBackdrop === 'true' && startedOnBackdrop && isOnBackdrop(dialog, event)) {
            closeDialog(dialog, '');
        }
    });

    dialog.addEventListener('cancel', (event) => {
        if (dialog.dataset.dismissible === 'false') {
            event.preventDefault();
        }
    });

    dialog.addEventListener('close', () => finishClosing(dialog));
    new MutationObserver(() => finishClosing(dialog)).observe(dialog, { attributes: true, attributeFilter: ['open'] });
}

/**
 * Roda uma vez por abertura, quando o diálogo aberto por openDialog() fecha: libera a rolagem,
 * devolve o foco e avisa com ui:dialog-close.
 *
 * @param {HTMLDialogElement} dialog
 */
function finishClosing(dialog) {
    if (dialog.open || !openers.has(dialog)) {
        return;
    }

    const opener = openers.get(dialog);
    openers.delete(dialog);
    syncScrollLock();

    if (opener instanceof HTMLElement && opener.isConnected) {
        opener.focus({ preventScroll: true });
    }

    dialog.dispatchEvent(new CustomEvent('ui:dialog-close', { bubbles: true, detail: { returnValue: dialog.returnValue } }));
}

/**
 * @param {Element} opener
 */
function describeOpener(opener) {
    const targetId = String(opener.getAttribute('data-dialog-open') ?? '').replace(/^#/, '');

    if (targetId === '') {
        return;
    }

    if (!opener.hasAttribute('aria-haspopup')) {
        opener.setAttribute('aria-haspopup', 'dialog');
    }

    if (!opener.hasAttribute('aria-controls')) {
        opener.setAttribute('aria-controls', targetId);
    }
}

/**
 * Quem recebe o foco quando o diálogo fecha. Item de <x-ui.dropdown> some com o menu fechado, então
 * o foco volta ao gatilho do menu.
 *
 * @param {Element|null} opener
 * @returns {HTMLElement|null}
 */
function returnFocusTarget(opener) {
    if (!(opener instanceof HTMLElement)) {
        return null;
    }

    const menuToggle = opener.closest('.hs-dropdown-menu')?.closest('.hs-dropdown')?.querySelector(':scope > .hs-dropdown-toggle');

    return menuToggle instanceof HTMLElement ? menuToggle : opener;
}

/**
 * Abre um diálogo de <x-ui.dialog>.
 *
 * @param {HTMLDialogElement|string} target Elemento, id ou "#id".
 * @param {{ opener?: Element|null }} [options] Quem recebe o foco de volta ao fechar (padrão: o
 *   elemento focado agora).
 * @returns {HTMLDialogElement|null}
 */
export function openDialog(target, { opener = document.activeElement } = {}) {
    const dialog = resolveDialog(target);

    if (!dialog) {
        console.warn(`Diálogo "${target}" não encontrado.`);

        return null;
    }

    bindDialog(dialog);

    if (dialog.open) {
        return dialog;
    }

    openers.set(dialog, returnFocusTarget(opener));
    dialog.returnValue = '';
    dialog.showModal();
    syncScrollLock();
    focusInitialControl(dialog);
    dialog.dispatchEvent(new CustomEvent('ui:dialog-open', { bubbles: true }));

    return dialog;
}

/**
 * Fecha um diálogo aberto.
 *
 * @param {HTMLDialogElement|string} target
 * @param {string} [returnValue]
 */
export function closeDialog(target, returnValue = '') {
    const dialog = resolveDialog(target);

    if (dialog?.open) {
        dialog.close(returnValue);
    }
}

/**
 * Abre na carga o diálogo marcado com data-dialog-open-on-load (uma vez só: dataset.dialogOpenedOnLoad).
 *
 * @param {Element} dialog
 */
function openOnLoad(dialog) {
    if (!(dialog instanceof HTMLDialogElement) || dialog.dataset.dialogOpenedOnLoad === 'true' || dialog.open) {
        return;
    }

    dialog.dataset.dialogOpenedOnLoad = 'true';

    const opener = dialog.id !== '' ? document.querySelector(`[data-dialog-open="${CSS.escape(dialog.id)}"]`) : null;
    openDialog(dialog, { opener });
}

/**
 * Liga os gatilhos [data-dialog-open], prepara os diálogos da página e abre os marcados com
 * data-dialog-open-on-load. Idempotente.
 *
 * @param {ParentNode} [root]
 */
export function initDialogs(root = document) {
    elementsWithin(root, 'dialog[data-ui-dialog]').forEach(bindDialog);
    elementsWithin(root, '[data-dialog-open]').forEach(describeOpener);
    elementsWithin(root, 'dialog[data-dialog-open-on-load]').forEach(openOnLoad);

    if (delegatedListenersReady) {
        return;
    }

    delegatedListenersReady = true;

    document.addEventListener('click', (event) => {
        const opener = event.target instanceof Element ? event.target.closest('[data-dialog-open]') : null;

        if (!opener || opener.matches('[aria-disabled="true"], :disabled')) {
            return;
        }

        event.preventDefault();
        describeOpener(opener);
        openDialog(opener.getAttribute('data-dialog-open'), { opener });
    });
}
