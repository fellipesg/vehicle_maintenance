/**
 * Botões que fecham um aviso da página, para o foco não se perder no <body> quando o botão some:
 * - "Fechar aviso" de <x-ui.flash> (data-flash-dismiss): remove o aviso na hora.
 * - "Fechar aviso" de <x-ui.alert dismissible> (data-alert-dismiss): a remoção, com o fade de
 *   hs-removing, é do HSRemoveElement do Preline (resources/js/ui/preline.js); aqui só o foco.
 *
 * O foco vai para o <main id="conteudo">. Dentro de um diálogo ou painel aberto, vai para o primeiro
 * controle dele que continua na tela, para não sair da armadilha de foco. Idempotente.
 *
 * layouts/partials/shell-script.blade.php (Fase 0) ainda trata data-flash-dismiss; se ele agir
 * antes, o aviso já saiu da página e aqui nada acontece.
 */

const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

let listenerReady = false;

/**
 * Tira o foco do aviso que está saindo.
 *
 * @param {Element} notice aviso que vai sair da página
 */
function focusAfterDismiss(notice) {
    const container = notice.closest('dialog[open], .hs-overlay.open, [role="dialog"]');

    if (container instanceof HTMLElement) {
        const next = Array.from(container.querySelectorAll(FOCUSABLE))
            .find((element) => !notice.contains(element) && element.getClientRects().length > 0);

        (next ?? container).focus({ preventScroll: true });

        return;
    }

    document.getElementById('conteudo')?.focus({ preventScroll: true });
}

export function initFlash() {
    if (listenerReady) {
        return;
    }

    listenerReady = true;

    document.addEventListener('click', (event) => {
        const target = event.target instanceof Element ? event.target : null;
        const flash = target?.closest('[data-flash-dismiss]')?.closest('[data-flash]');

        if (flash && flash.isConnected) {
            focusAfterDismiss(flash);
            flash.remove();

            return;
        }

        const alert = target?.closest('[data-alert-dismiss]')?.closest('[data-slot="alert"]');

        if (alert && alert.isConnected) {
            focusAfterDismiss(alert);
        }
    });
}
