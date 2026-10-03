import { confirmAction } from './ui/confirm';
import { initWorkshopMaintenanceForm } from './workshop-maintenance-form';

/**
 * Lista de peças e serviços da OS (portal da oficina): adicionar, remover e reindexar linhas, e
 * somar "Total do item" e "Total dos itens" enquanto a pessoa digita. O markup vem de
 * partials/maintenance-items-form.blade.php e partials/maintenance-item-row.blade.php.
 *
 * Os ids seguem App\Support\FormField::controlId (items[0][name] -> items_0_name), para o resumo de
 * erros levar ao campo; as mensagens da linha ([data-item-message="name-error"]) ganham o id
 * items_0_name-error e voltam ao aria-describedby do campo depois de reindexar.
 * Remover item com garantia emitida pede confirmação no diálogo do app (<x-ui.confirm-dialog>).
 *
 * initMaintenanceItems também liga o resto do formulário de OS (resources/js/workshop-maintenance-form.js).
 */
const ROW_SELECTOR = '[data-item-row]';
const MONEY = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });

function rowsOf(list) {
    return Array.from(list.querySelectorAll(ROW_SELECTOR));
}

function rowId(prefix, index, key) {
    return `${prefix}_${index}_${key}`;
}

/**
 * Valor numérico de um campo (aceita vírgula decimal); null quando vazio ou inválido.
 *
 * @param {HTMLInputElement|null} input
 * @returns {number|null}
 */
function numberFrom(input) {
    const raw = String(input?.value ?? '').trim().replace(/\s/g, '');

    if (raw === '') {
        return null;
    }

    const normalized = raw.includes(',') ? raw.replace(/\./g, '').replace(',', '.') : raw;
    const value = Number(normalized);

    return Number.isFinite(value) ? value : null;
}

/**
 * @param {number} value
 * @returns {string}
 */
export function formatMoney(value) {
    return MONEY.format(value).replace(/ /g, ' ');
}

/**
 * Total da linha: preço unitário × quantidade, ou null sem preço.
 *
 * @param {Element} row
 * @returns {number|null}
 */
function lineTotalOf(row) {
    const price = numberFrom(row.querySelector('[data-item-field="unit_price"]'));
    const quantity = numberFrom(row.querySelector('[data-item-field="quantity"]'));

    if (price === null || quantity === null) {
        return null;
    }

    return price * Math.max(0, Math.trunc(quantity));
}

function reindexRow(row, index, prefix) {
    const number = index + 1;

    row.dataset.index = String(index);

    row.querySelectorAll('[data-item-message]').forEach((message) => {
        message.id = rowId(prefix, index, message.dataset.itemMessage);
    });

    row.querySelectorAll('[data-item-field]').forEach((field) => {
        const key = field.dataset.itemField;
        field.name = `${prefix}[${index}][${key}]`;
        field.id = rowId(prefix, index, key);

        const describedBy = Array.from(row.querySelectorAll(`[data-item-message^="${key}-"]`)).map((message) => message.id);

        if (describedBy.length > 0) {
            field.setAttribute('aria-describedby', describedBy.join(' '));
        } else {
            field.removeAttribute('aria-describedby');
        }
    });

    row.querySelectorAll('[data-item-label]').forEach((label) => {
        label.htmlFor = rowId(prefix, index, label.dataset.itemLabel);
    });

    const title = row.querySelector('[data-item-title]');
    if (title) {
        title.textContent = `Item ${number}`;
        title.id = rowId(prefix, index, 'title');
        row.setAttribute('aria-labelledby', title.id);
    }

    row.querySelector('[data-remove-item]')?.setAttribute('aria-label', `Remover item ${number}`);
}

function bindMaintenanceItems(root) {
    if (root.dataset.maintenanceItemsReady === 'true') {
        return;
    }

    const list = root.querySelector('[data-items-list]');
    const template = root.querySelector('template[data-item-template]');
    const addButton = root.querySelector('[data-add-item]');

    if (!list || !template || !addButton) {
        return;
    }

    root.dataset.maintenanceItemsReady = 'true';

    const prefix = root.dataset.fieldPrefix || 'items';
    const emptyState = root.querySelector('[data-items-empty]');
    const status = root.querySelector('[data-items-status]');
    const totalWrapper = root.querySelector('[data-items-total-wrapper]');
    const totalOutput = root.querySelector('[data-items-total]');

    const announce = (message) => {
        if (!status) {
            return;
        }

        // Limpa antes para o leitor de tela repetir mensagens iguais em sequência.
        status.textContent = '';
        window.setTimeout(() => {
            status.textContent = message;
        }, 100);
    };

    const refreshTotals = () => {
        const rows = rowsOf(list);
        let sum = 0;

        rows.forEach((row) => {
            const lineTotal = lineTotalOf(row);
            const output = row.querySelector('[data-item-line-total]');

            if (output) {
                output.textContent = lineTotal === null ? '—' : formatMoney(lineTotal);
            }

            sum += lineTotal ?? 0;
        });

        if (totalOutput) {
            totalOutput.textContent = formatMoney(sum);
        }

        if (totalWrapper) {
            totalWrapper.hidden = rows.length === 0;
        }
    };

    const refresh = () => {
        const rows = rowsOf(list);
        rows.forEach((row, index) => reindexRow(row, index, prefix));

        if (emptyState) {
            emptyState.hidden = rows.length > 0;
        }

        refreshTotals();

        return rows;
    };

    const focusNameField = (row) => {
        const field = row.querySelector('[data-item-field="name"]');
        (field ?? row).focus();
    };

    addButton.addEventListener('click', () => {
        const row = template.content.firstElementChild?.cloneNode(true);
        if (!row) {
            return;
        }

        list.appendChild(row);
        const rows = refresh();
        focusNameField(row);
        announce(`Item ${rows.length} adicionado.`);
    });

    list.addEventListener('input', (event) => {
        if (event.target instanceof HTMLInputElement && ['unit_price', 'quantity'].includes(event.target.dataset.itemField ?? '')) {
            refreshTotals();
        }
    });

    list.addEventListener('click', async (event) => {
        const removeButton = event.target.closest('[data-remove-item]');
        if (!removeButton || !list.contains(removeButton)) {
            return;
        }

        const row = removeButton.closest(ROW_SELECTOR);
        if (!row) {
            return;
        }

        if (row.hasAttribute('data-has-warranty')) {
            const confirmed = await confirmAction({
                title: 'Remover o item com garantia?',
                message: 'Este item tem garantia emitida. Ao salvar a OS, a garantia também será removida.',
                confirmLabel: 'Remover item',
                variant: 'danger',
                opener: removeButton,
            });

            if (!confirmed || !row.isConnected) {
                return;
            }
        }

        const removedIndex = rowsOf(list).indexOf(row);
        row.remove();
        const rows = refresh();

        const nextFocus = rows[removedIndex - 1] ?? rows[0] ?? null;
        if (nextFocus) {
            focusNameField(nextFocus);
        } else {
            addButton.focus();
        }

        announce(
            rows.length === 0
                ? `Item ${removedIndex + 1} removido. Nenhuma peça ou serviço na lista.`
                : `Item ${removedIndex + 1} removido.`,
        );
    });

    refresh();
}

export function initMaintenanceItems(scope = document) {
    scope.querySelectorAll('[data-maintenance-items]').forEach(bindMaintenanceItems);
    initWorkshopMaintenanceForm(scope);
}
