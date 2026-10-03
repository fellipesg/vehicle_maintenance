import { elementsWithin } from './dom';

/**
 * Contador de caracteres de <x-ui.textarea counter maxlength="...">.
 *
 * O textarea aponta para o contador em data-textarea-counter="{id}-contador". A cada tecla, o
 * número visível ("120/4.000") e o texto do aria-describedby ("120 de 4.000 caracteres") mudam, e
 * data-state vira near (90% ou mais) ou limit (no máximo), que o CSS pinta de text-warning e
 * text-danger. Leitor de tela não ouve cada tecla: a região aria-live polite avisa só quando o texto
 * cruza uma faixa de 10% do limite ("50% do limite: 2.000 de 4.000 caracteres"), com uma pausa
 * curta para não falar enquanto a pessoa segura a tecla de apagar.
 *
 * Idempotente: pode ser chamado de novo com o trecho novo como raiz.
 */

const ANNOUNCE_DELAY_MS = 400;

/**
 * @param {number} value
 * @returns {string}
 */
export function formatCount(value) {
    return Math.max(0, Math.trunc(value)).toLocaleString('pt-BR');
}

/**
 * Faixa de 10% (0 a 10) e estado visual do contador.
 *
 * @param {number} length
 * @param {number} max
 * @returns {{ bucket: number, state: 'ok'|'near'|'limit' }}
 */
export function counterState(length, max) {
    if (!(max > 0)) {
        return { bucket: 0, state: 'ok' };
    }

    const ratio = Math.min(Math.max(length, 0) / max, 1);
    const state = length >= max ? 'limit' : (ratio >= 0.9 ? 'near' : 'ok');

    return { bucket: Math.floor(ratio * 10), state };
}

/**
 * Frase da região aria-live para a faixa atual.
 *
 * @param {number} length
 * @param {number} max
 * @returns {string}
 */
export function counterAnnouncement(length, max) {
    if (length >= max) {
        return `Limite de ${formatCount(max)} caracteres atingido.`;
    }

    const { bucket } = counterState(length, max);

    return `${bucket * 10}% do limite: ${formatCount(length)} de ${formatCount(max)} caracteres.`;
}

/**
 * @param {HTMLTextAreaElement} textarea
 */
function bindCounter(textarea) {
    const counter = document.getElementById(textarea.dataset.textareaCounter || '');

    if (!(counter instanceof HTMLElement)) {
        return;
    }

    const max = Number(counter.dataset.max || textarea.maxLength || 0);
    const visual = counter.querySelector('[data-textarea-counter-visual]');
    const text = counter.querySelector('[data-textarea-counter-text]');
    const live = counter.parentElement?.querySelector('[data-textarea-counter-live]') ?? null;
    let announcedBucket = counterState(textarea.value.length, max).bucket;
    let announceTimer = 0;

    const update = () => {
        const length = textarea.value.length;
        const { bucket, state } = counterState(length, max);

        if (visual) {
            visual.textContent = `${formatCount(length)}/${formatCount(max)}`;
        }

        if (text) {
            text.textContent = `${formatCount(length)} de ${formatCount(max)} caracteres`;
        }

        counter.dataset.state = state;

        if (!live || bucket === announcedBucket) {
            return;
        }

        window.clearTimeout(announceTimer);
        announceTimer = window.setTimeout(() => {
            const current = counterState(textarea.value.length, max).bucket;

            if (current === announcedBucket) {
                return;
            }

            announcedBucket = current;
            live.textContent = counterAnnouncement(textarea.value.length, max);
        }, ANNOUNCE_DELAY_MS);
    };

    textarea.addEventListener('input', update);
    update();
}

/**
 * @param {ParentNode} [root]
 */
export function initTextareaCounters(root = document) {
    elementsWithin(root, 'textarea[data-textarea-counter]').forEach((textarea) => {
        if (!(textarea instanceof HTMLTextAreaElement) || textarea.dataset.textareaCounterReady === 'true') {
            return;
        }

        textarea.dataset.textareaCounterReady = 'true';
        bindCounter(textarea);
    });
}
