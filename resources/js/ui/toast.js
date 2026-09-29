import { icon } from './icons';
import { escapeHtml } from '../utils/html';
import { prefersReducedMotion } from './dom';

/**
 * Toasts de <x-ui.toaster> (referência: sonner do ObsidianUI), em JS puro.
 *
 * - toast({ title, description, variant, duration }) ou window.revisalogToast(...), também com um
 *   texto só: toast('Link copiado').
 * - variant: success (o padrão) | info | warning | error ('danger' vale como error).
 * - duration em ms: success e info fecham em 5000; warning e error ficam (0 = até fechar).
 * - O tempo pausa com ponteiro ou foco sobre a pilha e com a aba em segundo plano.
 * - Leitor de tela: o texto vai para a região aria-live do toaster (polite; error na assertiva).
 * - Os toasts do servidor chegam em data-toasts no toaster (session('toast')).
 */

const VARIANTS = {
    success: { icon: 'check-circle', iconClass: 'text-success', label: 'Sucesso', live: 'polite', duration: 5000 },
    info: { icon: 'information-circle', iconClass: 'text-info', label: 'Informação', live: 'polite', duration: 5000 },
    warning: { icon: 'exclamation-triangle', iconClass: 'text-warning', label: 'Atenção', live: 'polite', duration: 0 },
    error: { icon: 'exclamation-circle', iconClass: 'text-danger', label: 'Erro', live: 'assertive', duration: 0 },
};

const MAX_VISIBLE = 3;
const EXIT_FALLBACK_MS = 300;
const TOAST_CLASSES = [
    'pointer-events-auto flex w-full items-start gap-3 rounded-card border border-border bg-surface p-4 text-sm text-foreground shadow-lg',
    'opacity-0 motion-safe:translate-y-2 data-[state=open]:opacity-100 data-[state=open]:translate-y-0',
    'transition-[opacity,translate] duration-base ease-smooth-out data-[state=closed]:duration-fast motion-reduce:transition-none',
].join(' ');

/** @type {Set<{ element: HTMLElement, remaining: number, startedAt: number, timer: number|null }>} */
const activeToasts = new Set();
let paused = false;
let listenersReady = false;

function toaster() {
    return document.querySelector('[data-ui-toaster]');
}

/**
 * Cria o toaster quando o layout não trouxe <x-ui.toaster />, com a mesma estrutura dele.
 *
 * @returns {HTMLElement}
 */
function ensureToaster() {
    const existing = toaster();

    if (existing) {
        return existing;
    }

    const region = document.createElement('div');
    region.className = 'theme-default pointer-events-none fixed inset-x-4 bottom-4 z-[60] flex flex-col items-stretch sm:inset-x-auto sm:right-4 sm:w-96';
    region.dataset.uiToaster = '';
    region.innerHTML = `
        <ol class="flex flex-col gap-2" data-ui-toast-list></ol>
        <div class="sr-only" role="status" aria-live="polite" aria-atomic="true" data-ui-toast-announcer="polite"></div>
        <div class="sr-only" role="alert" aria-live="assertive" aria-atomic="true" data-ui-toast-announcer="assertive"></div>`;
    document.body.append(region);
    bindRegion(region);

    return region;
}

/**
 * Esvazia e preenche a região viva no quadro seguinte, para o mesmo texto repetido ser lido de novo.
 *
 * @param {HTMLElement} region
 * @param {'polite'|'assertive'} politeness
 * @param {string} text
 */
function announce(region, politeness, text) {
    const announcer = region.querySelector(`[data-ui-toast-announcer="${politeness}"]`);

    if (!announcer) {
        return;
    }

    announcer.textContent = '';
    window.setTimeout(() => {
        announcer.textContent = text;
    }, 100);
}

function startTimer(entry) {
    if (paused || entry.remaining <= 0 || entry.timer !== null) {
        return;
    }

    entry.startedAt = Date.now();
    entry.timer = window.setTimeout(() => dismiss(entry), entry.remaining);
}

function stopTimer(entry) {
    if (entry.timer === null) {
        return;
    }

    window.clearTimeout(entry.timer);
    entry.timer = null;
    entry.remaining = Math.max(0, entry.remaining - (Date.now() - entry.startedAt));
}

function setPaused(value) {
    paused = value;
    activeToasts.forEach(value ? stopTimer : startTimer);
}

/**
 * @param {{ element: HTMLElement, remaining: number, startedAt: number, timer: number|null }} entry
 */
function dismiss(entry) {
    if (!activeToasts.has(entry)) {
        return;
    }

    activeToasts.delete(entry);
    stopTimer(entry);

    const node = entry.element;
    const hadFocus = node.contains(document.activeElement);
    let removed = false;
    const remove = () => {
        if (removed) {
            return;
        }

        removed = true;
        node.remove();

        if (hadFocus) {
            document.getElementById('conteudo')?.focus({ preventScroll: true });
        }

        if (activeToasts.size === 0) {
            setPaused(false);
        }
    };

    node.dataset.state = 'closed';

    if (prefersReducedMotion()) {
        remove();

        return;
    }

    node.addEventListener('transitionend', remove, { once: true });
    window.setTimeout(remove, EXIT_FALLBACK_MS);
}

/**
 * @param {HTMLElement} region
 */
function bindRegion(region) {
    if (region.dataset.uiToasterReady === 'true') {
        return;
    }

    region.dataset.uiToasterReady = 'true';

    region.addEventListener('mouseenter', () => setPaused(true));
    region.addEventListener('mouseleave', () => {
        if (!region.contains(document.activeElement)) {
            setPaused(false);
        }
    });
    region.addEventListener('focusin', () => setPaused(true));
    region.addEventListener('focusout', (event) => {
        if (!region.contains(event.relatedTarget) && !region.matches(':hover')) {
            setPaused(false);
        }
    });
}

/**
 * @param {string} value
 * @returns {'success'|'info'|'warning'|'error'}
 */
function normalizeVariant(value) {
    if (value === 'danger') {
        return 'error';
    }

    return Object.hasOwn(VARIANTS, value) ? value : 'success';
}

/**
 * Mostra um toast.
 *
 * @param {string|{ title?: string, description?: string, variant?: string, duration?: number }} options
 * @returns {{ dismiss: () => void }}
 */
export function toast(options = {}) {
    const settings = typeof options === 'string' ? { title: options } : { ...options };
    const title = String(settings.title ?? '').trim();
    const description = String(settings.description ?? '').trim();

    if (title === '' && description === '') {
        return { dismiss: () => {} };
    }

    const variantName = normalizeVariant(settings.variant);
    const variant = VARIANTS[variantName];
    const duration = Number.isFinite(settings.duration) ? Math.max(0, settings.duration) : variant.duration;
    const region = ensureToaster();
    const list = region.querySelector('[data-ui-toast-list]');
    const element = document.createElement('li');

    element.className = TOAST_CLASSES;
    element.dataset.uiToast = '';
    element.dataset.variant = variantName;
    element.dataset.state = 'closed';
    element.innerHTML = `
        ${icon(variant.icon, { className: `mt-0.5 size-5 ${variant.iconClass}` })}
        <div class="min-w-0 flex-1 self-center">
            ${title !== '' ? `<p class="font-semibold">${escapeHtml(title)}</p>` : ''}
            ${description !== '' ? `<p class="${title !== '' ? 'mt-0.5 ' : ''}text-muted-foreground">${escapeHtml(description)}</p>` : ''}
        </div>
        <button type="button" data-toast-dismiss aria-label="Fechar aviso" class="-my-2 -mr-2.5 inline-flex size-10 shrink-0 items-center justify-center rounded-control text-muted-foreground transition-colors duration-fast hover:bg-surface-muted hover:text-foreground motion-reduce:transition-none">
            ${icon('x-mark', { className: 'size-4' })}
        </button>`;

    const entry = { element, remaining: duration, startedAt: Date.now(), timer: null };

    element.querySelector('[data-toast-dismiss]')?.addEventListener('click', () => dismiss(entry));
    list?.append(element);
    activeToasts.add(entry);

    window.requestAnimationFrame(() => {
        window.requestAnimationFrame(() => {
            element.dataset.state = 'open';
        });
    });

    const overflow = Array.from(activeToasts).slice(0, Math.max(0, activeToasts.size - MAX_VISIBLE));
    overflow.forEach(dismiss);

    announce(region, variant.live, [`${variant.label}:`, title, description].filter(Boolean).join(' '));
    startTimer(entry);

    return { dismiss: () => dismiss(entry) };
}

/**
 * Liga o toaster da página, mostra os toasts vindos do servidor e expõe window.revisalogToast.
 * Idempotente: os toasts do servidor aparecem uma vez só.
 */
export function initToasts() {
    window.revisalogToast = toast;

    const region = toaster();

    if (region) {
        bindRegion(region);

        const initial = region.getAttribute('data-toasts');

        if (initial) {
            region.removeAttribute('data-toasts');

            try {
                const entries = JSON.parse(initial);

                (Array.isArray(entries) ? entries : [entries]).forEach((entry) => toast(entry));
            } catch (error) {
                console.warn('Toast do servidor com JSON inválido em data-toasts.', error);
            }
        }
    }

    if (listenersReady) {
        return;
    }

    listenersReady = true;

    document.addEventListener('visibilitychange', () => setPaused(document.hidden));
}
