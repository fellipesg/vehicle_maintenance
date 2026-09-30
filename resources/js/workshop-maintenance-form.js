import { escapeHtml } from './utils/html';

/**
 * Formulário de OS da oficina (partials/workshop-maintenance-form): o que acontece enquanto a
 * pessoa preenche, sem sair da página. O servidor continua validando tudo.
 *
 * - Garantia: cada <select data-warranty-select> mostra "Válida até dd/mm/aaaa" no
 *   [data-warranty-until] do seu bloco, a partir da data do serviço ([data-maintenance-date]) e do
 *   data-duration-days da opção; muda junto com a data.
 * - Fotos: cada [data-photo-group] conta as fotos que ficam (as salvas menos as marcadas em
 *   "Remover") mais as novas escolhidas, atualiza "N de 4 fotos" e, acima do limite, avisa no
 *   próprio grupo e segura o envio, levando o foco ao aviso.
 *
 * initWorkshopMaintenanceForm(root) é idempotente; resources/js/maintenance-items.js o chama.
 */

const FORM_SELECTOR = 'form[data-maintenance-os-form]';
const DATE_FORMAT = new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', timeZone: 'UTC' });

/**
 * Data de fim da garantia: data do serviço + duração em dias (como MaintenanceWarranty::computeDates).
 *
 * @param {string} serviceDate Y-m-d
 * @param {number} durationDays
 * @returns {string|null} dd/mm/aaaa
 */
export function warrantyEndLabel(serviceDate, durationDays) {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(serviceDate ?? ''));

    if (!match || !Number.isFinite(durationDays) || durationDays <= 0) {
        return null;
    }

    const end = new Date(Date.UTC(Number(match[1]), Number(match[2]) - 1, Number(match[3]) + durationDays));

    return Number.isNaN(end.getTime()) ? null : DATE_FORMAT.format(end);
}

/**
 * Texto do contador de um grupo de fotos.
 *
 * @param {number} total fotos que ficam + novas
 * @param {number} max
 * @returns {string}
 */
export function photoCountLabel(total, max) {
    const room = max - total;

    if (room > 0) {
        return `${total} de ${max} fotos · você pode adicionar ${total === 0 ? 'até' : 'mais'} ${room}`;
    }

    if (room === 0) {
        return `${total} de ${max} fotos · limite atingido: remova uma para trocar`;
    }

    return `${total} de ${max} fotos · ${-room === 1 ? '1 foto passa' : `${-room} fotos passam`} do limite`;
}

/**
 * @param {HTMLFormElement} form
 * @param {HTMLSelectElement} select
 */
function refreshWarrantyUntil(form, select) {
    const scope = select.closest('[data-item-row], [data-warranty-section]');
    const output = scope?.querySelector('[data-warranty-until]');

    if (!output) {
        return;
    }

    const option = select.selectedOptions[0];
    const days = Number(option?.dataset.durationDays);
    const serviceDate = form.querySelector('[data-maintenance-date]')?.value ?? '';
    const label = option && option.value !== '' ? warrantyEndLabel(serviceDate, days) : null;

    output.innerHTML = label
        ? `Válida até <span class="font-medium text-foreground tabular-nums">${escapeHtml(label)}</span>`
        : '';
}

/**
 * @param {HTMLElement} group
 * @returns {{ total: number, max: number, over: number }}
 */
function photoGroupState(group) {
    const max = Number(group.dataset.max) || 4;
    const saved = group.querySelectorAll('[data-photo-item]').length;
    const removed = group.querySelectorAll('input[data-photo-remove]:checked').length;
    const input = group.querySelector('input[type="file"][data-photo-input]');
    const added = input?.files?.length ?? 0;
    const total = saved - removed + added;

    return { total, max, over: Math.max(0, total - max) };
}

/**
 * @param {HTMLElement} group
 * @returns {boolean} true quando o grupo passa do limite
 */
function refreshPhotoGroup(group) {
    const { total, max, over } = photoGroupState(group);
    const count = group.querySelector('[data-photo-count]');
    const limit = group.querySelector('[data-photo-limit]');

    if (count) {
        count.textContent = photoCountLabel(total, max);
    }

    if (limit) {
        limit.textContent = over > 0
            ? `${group.dataset.label ?? 'Este grupo'}: são até ${max} fotos. Tire ${over === 1 ? '1 foto nova' : `${over} fotos novas`} ou marque ${over === 1 ? 'uma salva' : `${over} salvas`} em "Remover".`
            : '';
    }

    return over > 0;
}

/**
 * @param {HTMLFormElement} form
 */
function bindForm(form) {
    if (form.dataset.workshopMaintenanceFormReady === 'true') {
        return;
    }

    form.dataset.workshopMaintenanceFormReady = 'true';

    const refreshAllWarranties = () => {
        form.querySelectorAll('select[data-warranty-select]').forEach((select) => refreshWarrantyUntil(form, select));
    };

    form.addEventListener('change', (event) => {
        const target = event.target;

        if (target instanceof HTMLSelectElement && target.matches('[data-warranty-select]')) {
            refreshWarrantyUntil(form, target);

            return;
        }

        if (target instanceof HTMLInputElement && target.matches('[data-maintenance-date]')) {
            refreshAllWarranties();

            return;
        }

        const group = target instanceof Element ? target.closest('[data-photo-group]') : null;

        if (group instanceof HTMLElement) {
            refreshPhotoGroup(group);
        }
    });

    form.addEventListener('input', (event) => {
        if (event.target instanceof HTMLInputElement && event.target.matches('[data-maintenance-date]')) {
            refreshAllWarranties();
        }
    });

    form.addEventListener('submit', (event) => {
        const overLimit = Array.from(form.querySelectorAll('[data-photo-group]'))
            .filter((group) => group instanceof HTMLElement && refreshPhotoGroup(group));

        if (overLimit.length === 0) {
            return;
        }

        event.preventDefault();
        const message = overLimit[0].querySelector('[data-photo-limit]');

        overLimit[0].scrollIntoView({ block: 'center', behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
        (message ?? overLimit[0]).focus({ preventScroll: true });
    });

    form.querySelectorAll('[data-photo-group]').forEach((group) => {
        if (group instanceof HTMLElement) {
            refreshPhotoGroup(group);
        }
    });
}

/**
 * Liga os formulários de OS de root (e o próprio root). Idempotente.
 *
 * @param {ParentNode} [root]
 */
export function initWorkshopMaintenanceForm(root = document) {
    if (root instanceof HTMLFormElement && root.matches(FORM_SELECTOR)) {
        bindForm(root);
    }

    root.querySelectorAll?.(FORM_SELECTOR).forEach(bindForm);
}
