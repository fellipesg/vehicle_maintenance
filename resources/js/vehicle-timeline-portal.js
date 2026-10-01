/**
 * Linha do tempo do veículo.
 *
 * A ficha renderiza a linha do tempo no servidor (x-vehicle-timeline): as colunas são abas e cada
 * evento tem o próprio painel, então resources/js/ui/tabs.js já cuida da seleção e do teclado.
 * initVehicleTimelines() só acrescenta: trilho e progresso alinhados ao centro das colunas visíveis,
 * esmaecido nas bordas quando há rolagem e a coluna selecionada centralizada.
 * initVehicleDetails() abre a aba certa da ficha quando a URL aponta para algo dentro dela
 * (#manutencao-{id} no Histórico). Nada aqui monta HTML.
 */

function prefersReducedMotion() {
    return typeof window.matchMedia === 'function' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

/**
 * @param {ParentNode} root
 * @param {string} selector
 * @returns {Element[]}
 */
function within(root, selector) {
    const found = typeof root?.querySelectorAll === 'function' ? Array.from(root.querySelectorAll(selector)) : [];

    if (typeof root?.matches === 'function' && root.matches(selector)) {
        found.unshift(root);
    }

    return found;
}

export function positionTimelineProgress(root, percent) {
    const grid = root.querySelector('[data-timeline-grid]');
    const progress = root.querySelector('[data-timeline-progress]');
    const rail = root.querySelector('[data-timeline-rail]');

    if (!grid || (!progress && !rail)) {
        return;
    }

    const columns = Array.from(root.querySelectorAll('[data-timeline-column]')).filter((column) => !column.hidden);

    if (columns.length < 2) {
        return;
    }

    const first = columns[0];
    const last = columns[columns.length - 1];
    const firstCenter = first.offsetLeft + (first.offsetWidth / 2);
    const lastCenter = last.offsetLeft + (last.offsetWidth / 2);

    if (rail) {
        rail.style.left = `${firstCenter}px`;
        rail.style.width = `${Math.max(0, lastCenter - firstCenter)}px`;
        rail.style.right = 'auto';
    }

    if (progress) {
        const ratio = Math.min(1, Math.max(0, Number(percent ?? progress.dataset.trackPercent ?? 0) / 100));

        progress.style.left = `${firstCenter}px`;
        progress.style.width = `${Math.max(0, lastCenter - firstCenter) * ratio}px`;
        progress.style.right = 'auto';
    }
}

/**
 * Rola a faixa de colunas para deixar a coluna selecionada no centro (só na horizontal: a página
 * não pula). Sem animação com prefers-reduced-motion.
 *
 * @param {HTMLElement} timeline [data-vehicle-timeline]
 * @param {{ smooth?: boolean }} [options]
 */
function centerSelectedColumn(timeline, { smooth = false } = {}) {
    const scroller = timeline.querySelector('[data-timeline-scroller]');
    const selected = timeline.querySelector('[data-timeline-column][aria-selected="true"]');

    if (!scroller || !selected || scroller.scrollWidth <= scroller.clientWidth) {
        return;
    }

    const left = selected.offsetLeft - ((scroller.clientWidth - selected.offsetWidth) / 2);

    if (typeof scroller.scrollTo === 'function') {
        scroller.scrollTo({ left: Math.max(0, left), behavior: smooth && !prefersReducedMotion() ? 'smooth' : 'auto' });
    } else {
        scroller.scrollLeft = Math.max(0, left);
    }
}

/**
 * Marca a faixa com data-overflow="true" quando há colunas fora da tela (liga o esmaecido nas bordas).
 *
 * @param {HTMLElement} timeline
 */
function markOverflow(timeline) {
    const scroller = timeline.querySelector('[data-timeline-scroller]');

    if (scroller) {
        scroller.dataset.overflow = scroller.scrollWidth > scroller.clientWidth + 1 ? 'true' : 'false';
    }
}

/**
 * Liga as linhas do tempo renderizadas no servidor. Idempotente.
 *
 * @param {ParentNode} [root]
 */
export function initVehicleTimelines(root = document) {
    within(root, '[data-vehicle-timeline]').forEach((timeline) => {
        if (!(timeline instanceof HTMLElement) || timeline.dataset.timelineReady === 'true') {
            return;
        }

        timeline.dataset.timelineReady = 'true';

        const layout = () => {
            positionTimelineProgress(timeline);
            markOverflow(timeline);
        };

        let frame = 0;
        const scheduleLayout = () => {
            window.cancelAnimationFrame(frame);
            frame = window.requestAnimationFrame(layout);
        };

        window.requestAnimationFrame(() => {
            layout();
            centerSelectedColumn(timeline);
        });
        window.addEventListener('resize', scheduleLayout);

        timeline.addEventListener('ui:tab-change', (event) => {
            if (event.target instanceof Element && event.target.matches('[data-timeline-tabs]')) {
                centerSelectedColumn(timeline, { smooth: true });
            }
        });

        // O filtro de procedência esconde colunas: o trilho volta a ligar a primeira à última visível.
        document.addEventListener('provenance:filter-change', (event) => {
            if (event.target instanceof Node && event.target.contains(timeline)) {
                scheduleLayout();
            }
        });

        // A aba "Linha do tempo" pode nascer escondida (hidden): mede de novo quando aparecer.
        document.addEventListener('ui:tab-change', (event) => {
            if (event.target instanceof Node && event.target.contains(timeline)) {
                scheduleLayout();
            }
        });
    });
}

/**
 * Na ficha (x-vehicle.detail), abre a aba que contém o alvo do fragmento da URL (#manutencao-{id}),
 * no carregamento e em cada troca de fragmento. As abas continuam com o próprio #id.
 *
 * @param {ParentNode} [root]
 */
export function initVehicleDetails(root = document) {
    within(root, '[data-vehicle-detail]').forEach((detail) => {
        if (!(detail instanceof HTMLElement) || detail.dataset.vehicleDetailReady === 'true') {
            return;
        }

        detail.dataset.vehicleDetailReady = 'true';

        const revealHashTarget = () => {
            const id = decodeURIComponent(window.location.hash.replace(/^#/, ''));
            const target = id !== '' ? document.getElementById(id) : null;

            if (!target || !detail.contains(target)) {
                return;
            }

            const panel = target.closest('[role="tabpanel"][hidden]');
            const tab = panel ? detail.querySelector(`[role="tab"][aria-controls="${CSS.escape(panel.id)}"]`) : null;

            if (tab instanceof HTMLElement) {
                tab.click();
                target.scrollIntoView({ block: 'start', behavior: prefersReducedMotion() ? 'auto' : 'smooth' });
            }
        };

        revealHashTarget();
        window.addEventListener('hashchange', revealHashTarget);
    });
}
