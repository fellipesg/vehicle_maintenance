import { bindKilometerRange } from './maintenance-kilometer-range';

/**
 * Formulário de manutenção do proprietário (resources/views/user/maintenances/_form.blade.php).
 *
 * - Quilometragem: mostra a faixa aceita para o veículo e a data e avisa quando o valor digitado
 *   sai dela (resources/js/maintenance-kilometer-range.js, o mesmo do Lojista).
 * - Oficina: com uma oficina da rede escolhida, o campo "Nome da oficina" some (o nome vem do
 *   cadastro) e o aviso da nota fiscal diz que ela passa a ser obrigatória (aria-live).
 *
 * Idempotente: pode ser chamado de novo com o trecho novo como raiz.
 */

const INVOICE_OPTIONAL = 'Opcional sem oficina da rede; obrigatória ao escolher uma. O XML da NF-e importa as peças com mais precisão.';
const INVOICE_REQUIRED = 'Obrigatória com uma oficina da rede: envie o PDF (DANFE) ou o XML da NF-e do serviço.';

/**
 * @param {HTMLElement} form
 */
function bindForm(form) {
    const workshopSelect = form.querySelector('[data-maintenance-workshop]');
    const workshopNameField = form.querySelector('[data-workshop-name-field]');
    const invoiceRequirement = form.querySelector('[data-invoice-requirement]');
    const existingInvoices = Number(form.dataset.existingInvoices || 0);

    const syncWorkshop = () => {
        const linked = Boolean(workshopSelect?.value);

        if (workshopNameField) {
            workshopNameField.hidden = linked;
            workshopNameField.querySelectorAll('input').forEach((input) => {
                input.disabled = linked;
            });
        }

        if (invoiceRequirement && existingInvoices === 0) {
            invoiceRequirement.textContent = linked ? INVOICE_REQUIRED : INVOICE_OPTIONAL;
        }
    };

    workshopSelect?.addEventListener('change', syncWorkshop);

    bindKilometerRange(form);
    syncWorkshop();
}

/**
 * @param {ParentNode} [root]
 */
export function initOwnerMaintenanceForms(root = document) {
    root.querySelectorAll('[data-owner-maintenance-form]').forEach((form) => {
        if (form.dataset.ownerMaintenanceFormReady === 'true') {
            return;
        }

        form.dataset.ownerMaintenanceFormReady = 'true';
        bindForm(form);
    });
}
