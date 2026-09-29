import { elementsWithin } from './dom';

/**
 * Botões de <x-ui.copy-button> ([data-copy-button] com data-copy-value).
 *
 * - O botão nasce com hidden (sem JS não há como copiar) e aparece aqui.
 * - Copiou: data-copied no botão por 1,5s (o CSS troca o ícone pelo check) e a região
 *   role="status" do componente recebe "Copiado" (data-copied-label), que o leitor de tela anuncia.
 *   O nome do botão não muda.
 * - Sem acesso à área de transferência (página sem HTTPS, permissão negada): o campo só de leitura
 *   do componente aparece com o texto selecionado e a região de status pede Ctrl+C (⌘C no Mac).
 *
 * Idempotente: pode ser chamado de novo com o trecho novo como raiz.
 */

export const COPIED_FEEDBACK_MS = 1500;

/**
 * @returns {boolean}
 */
function isApplePlatform() {
    const platform = navigator.userAgentData?.platform || navigator.platform || navigator.userAgent || '';

    return /mac|iphone|ipad|ipod/i.test(platform);
}

/**
 * Copia pelo Clipboard API. Falha (false) quando a API não existe ou o navegador recusa.
 *
 * @param {string} text
 * @returns {Promise<boolean>}
 */
export async function writeToClipboard(text) {
    if (typeof navigator === 'undefined' || typeof navigator.clipboard?.writeText !== 'function') {
        return false;
    }

    try {
        await navigator.clipboard.writeText(text);

        return true;
    } catch {
        return false;
    }
}

/**
 * Aviso curto na região de status: limpa antes, para o mesmo texto ser anunciado de novo.
 *
 * @param {HTMLElement|null} status
 * @param {string} message
 */
function announce(status, message) {
    if (!status) {
        return;
    }

    status.textContent = '';
    window.requestAnimationFrame(() => {
        status.textContent = message;
    });
}

/**
 * @param {HTMLButtonElement} button
 */
function bindCopyButton(button) {
    const root = button.closest('[data-slot="copy-button"]') ?? button.parentElement;
    const status = root?.querySelector('[data-copy-status]') ?? null;
    const fallback = root?.querySelector('[data-copy-fallback]') ?? null;
    let resetTimer = 0;

    button.hidden = false;

    button.addEventListener('click', async () => {
        const value = button.dataset.copyValue ?? '';

        if (value === '') {
            return;
        }

        if (await writeToClipboard(value)) {
            if (fallback instanceof HTMLInputElement) {
                fallback.hidden = true;
            }

            button.dataset.copied = '';
            announce(status, button.dataset.copiedLabel || 'Copiado');
            window.clearTimeout(resetTimer);
            resetTimer = window.setTimeout(() => {
                delete button.dataset.copied;

                if (status) {
                    status.textContent = '';
                }
            }, COPIED_FEEDBACK_MS);

            return;
        }

        if (fallback instanceof HTMLInputElement) {
            fallback.value = value;
            fallback.hidden = false;
            fallback.focus();
            fallback.select();
        }

        announce(status, `Não foi possível copiar sozinho. O texto está selecionado: pressione ${isApplePlatform() ? '⌘C' : 'Ctrl+C'} para copiar.`);
    });
}

/**
 * @param {ParentNode} [root]
 */
export function initCopyButtons(root = document) {
    elementsWithin(root, 'button[data-copy-button]').forEach((button) => {
        if (!(button instanceof HTMLButtonElement) || button.dataset.copyButtonReady === 'true') {
            return;
        }

        button.dataset.copyButtonReady = 'true';
        bindCopyButton(button);
    });
}
