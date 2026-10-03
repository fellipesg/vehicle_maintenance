import { openDialog } from './dialog';
import { elementsWithin, prefersReducedMotion } from './dom';

/**
 * Carrossel de <x-ui.lightbox> (dialog[data-ui-lightbox]).
 *
 * - Gatilhos: links a[data-lightbox-open="{id}"][data-lightbox-index="n"]. O clique simples abre o
 *   diálogo na foto n; Ctrl/Cmd/Shift ou o botão do meio seguem o link (a foto em nova aba). O
 *   "(abre em nova aba)" do link ([data-lightbox-new-tab-hint]) some, e o link ganha
 *   aria-haspopup="dialog".
 * - A trilha usa scroll-snap: deslizar no celular é o rolar nativo; aqui só lemos a posição para
 *   atualizar o contador, o "Abrir original", as pontas (aria-disabled) e o aria-hidden dos slides
 *   fora da tela, e a região aria-live recebe o alt da foto que entrou.
 * - Teclado dentro do diálogo: setas para os lados, Home e End. Esc e o foco preso são do <dialog>
 *   (resources/js/ui/dialog.js), que também devolve o foco à miniatura ao fechar.
 * - Troca suave só sem prefers-reduced-motion; com ela, instantânea.
 *
 * Idempotente: pode ser chamado de novo com o trecho novo como raiz.
 */

/** Tempo máximo de uma rolagem programada antes de voltar a ouvir a posição. */
const PROGRAMMATIC_SCROLL_MS = 700;
const ANNOUNCE_AFTER_OPEN_MS = 120;

const lightboxes = new WeakMap();
let delegatedListenersReady = false;

/**
 * @param {number} index
 * @param {number} total
 * @returns {number}
 */
export function clampIndex(index, total) {
    return Math.min(Math.max(Math.trunc(index) || 0, 0), Math.max(total - 1, 0));
}

/**
 * Foto visível a partir da rolagem da trilha (cada slide ocupa a largura toda).
 *
 * @param {number} scrollLeft
 * @param {number} slideWidth
 * @param {number} total
 * @returns {number}
 */
export function indexFromScroll(scrollLeft, slideWidth, total) {
    if (!(slideWidth > 0)) {
        return 0;
    }

    return clampIndex(Math.round(Math.abs(scrollLeft) / slideWidth), total);
}

/**
 * @param {MouseEvent} event
 * @returns {boolean}
 */
function isPlainClick(event) {
    return event.button === 0 && !event.metaKey && !event.ctrlKey && !event.shiftKey && !event.altKey;
}

/**
 * @param {HTMLDialogElement} dialog
 */
function createController(dialog) {
    const track = dialog.querySelector('[data-lightbox-track]');
    const slides = Array.from(dialog.querySelectorAll('[data-lightbox-slide]'));
    const counter = dialog.querySelector('[data-lightbox-counter]');
    const status = dialog.querySelector('[data-lightbox-status]');
    const original = dialog.querySelector('[data-lightbox-original]');
    const previous = dialog.querySelector('[data-lightbox-prev]');
    const next = dialog.querySelector('[data-lightbox-next]');
    const total = slides.length;
    let current = 0;
    let programmaticTarget = null;
    let programmaticTimer = 0;
    let frame = 0;

    if (!(track instanceof HTMLElement) || total === 0) {
        return null;
    }

    const render = (index, { announce = true } = {}) => {
        current = clampIndex(index, total);

        const slide = slides[current];

        if (counter) {
            counter.textContent = `Foto ${current + 1} de ${total}`;
        }

        if (original instanceof HTMLAnchorElement && slide.dataset.lightboxSrc) {
            original.href = slide.dataset.lightboxSrc;
        }

        previous?.setAttribute('aria-disabled', current === 0 ? 'true' : 'false');
        next?.setAttribute('aria-disabled', current === total - 1 ? 'true' : 'false');

        slides.forEach((item, itemIndex) => {
            if (itemIndex === current) {
                item.removeAttribute('aria-hidden');
            } else {
                item.setAttribute('aria-hidden', 'true');
            }
        });

        if (announce && status) {
            status.textContent = slide.dataset.lightboxAlt || '';
        }
    };

    const goTo = (index, { instant = false } = {}) => {
        const target = clampIndex(index, total);

        if (target === current && !instant) {
            return;
        }

        programmaticTarget = target;
        window.clearTimeout(programmaticTimer);
        programmaticTimer = window.setTimeout(() => {
            programmaticTarget = null;
        }, PROGRAMMATIC_SCROLL_MS);

        track.scrollTo({ left: target * track.clientWidth, behavior: instant || prefersReducedMotion() ? 'instant' : 'smooth' });
        render(target, { announce: !instant });
    };

    const syncFromScroll = () => {
        frame = 0;

        const index = indexFromScroll(track.scrollLeft, track.clientWidth, total);

        if (programmaticTarget !== null) {
            if (index !== programmaticTarget) {
                return;
            }

            programmaticTarget = null;
            window.clearTimeout(programmaticTimer);
        }

        if (index !== current) {
            render(index);
        }
    };

    track.addEventListener('scroll', () => {
        if (frame === 0) {
            frame = window.requestAnimationFrame(syncFromScroll);
        }
    }, { passive: true });

    previous?.addEventListener('click', () => {
        if (previous.getAttribute('aria-disabled') !== 'true') {
            goTo(current - 1);
        }
    });

    next?.addEventListener('click', () => {
        if (next.getAttribute('aria-disabled') !== 'true') {
            goTo(current + 1);
        }
    });

    dialog.addEventListener('keydown', (event) => {
        if (total < 2 || event.altKey || event.ctrlKey || event.metaKey) {
            return;
        }

        const targets = { ArrowLeft: current - 1, ArrowRight: current + 1, Home: 0, End: total - 1 };

        if (!(event.key in targets)) {
            return;
        }

        event.preventDefault();
        goTo(targets[event.key]);
    });

    window.addEventListener('resize', () => {
        if (dialog.open) {
            track.scrollTo({ left: current * track.clientWidth, behavior: 'instant' });
        }
    });

    return {
        /**
         * @param {number} index
         * @param {Element|null} opener
         */
        open(index, opener) {
            openDialog(dialog, { opener });
            goTo(index, { instant: true });
            window.setTimeout(() => render(current), ANNOUNCE_AFTER_OPEN_MS);
        },
    };
}

/**
 * @param {Element} dialog
 */
function bindLightbox(dialog) {
    if (!(dialog instanceof HTMLDialogElement) || dialog.dataset.lightboxReady === 'true') {
        return;
    }

    const controller = createController(dialog);

    if (controller === null) {
        return;
    }

    dialog.dataset.lightboxReady = 'true';
    lightboxes.set(dialog, controller);
}

/**
 * @param {Element} trigger
 */
function describeTrigger(trigger) {
    if (!(trigger instanceof HTMLElement) || trigger.dataset.lightboxTriggerReady === 'true') {
        return;
    }

    trigger.dataset.lightboxTriggerReady = 'true';
    trigger.setAttribute('aria-haspopup', 'dialog');
    trigger.querySelectorAll('[data-lightbox-new-tab-hint]').forEach((hint) => hint.remove());
}

/**
 * Abre a lightbox pelo id, na foto da posição index (a partir de 0).
 *
 * @param {string} id
 * @param {number} [index]
 * @param {{ opener?: Element|null }} [options]
 * @returns {boolean}
 */
export function openLightbox(id, index = 0, { opener = document.activeElement } = {}) {
    const dialog = document.getElementById(String(id).replace(/^#/, ''));

    if (!(dialog instanceof HTMLDialogElement) || !dialog.hasAttribute('data-ui-lightbox')) {
        return false;
    }

    bindLightbox(dialog);

    const controller = lightboxes.get(dialog);

    if (!controller) {
        return false;
    }

    controller.open(index, opener);

    return true;
}

/**
 * @param {ParentNode} [root]
 */
export function initLightboxes(root = document) {
    elementsWithin(root, 'dialog[data-ui-lightbox]').forEach(bindLightbox);
    elementsWithin(root, '[data-lightbox-open]').forEach(describeTrigger);

    if (delegatedListenersReady) {
        return;
    }

    delegatedListenersReady = true;

    document.addEventListener('click', (event) => {
        const trigger = event.target instanceof Element ? event.target.closest('[data-lightbox-open]') : null;

        if (!trigger || event.defaultPrevented || !isPlainClick(event)) {
            return;
        }

        const index = Number(trigger.getAttribute('data-lightbox-index') || 0);

        if (openLightbox(trigger.getAttribute('data-lightbox-open') || '', index, { opener: trigger })) {
            event.preventDefault();
        }
    });
}
