import { bindKilometerRange } from './maintenance-kilometer-range';

/**
 * "Registrar manutenção" do Lojista (resources/views/garage/maintenances/create.blade.php,
 * form[data-garage-maintenance-form]).
 *
 * - Quilometragem: a faixa aceita para o veículo e a data (resources/js/maintenance-kilometer-range.js,
 *   a mesma do Proprietário). O campo não é preenchido com o hodômetro atual nem ganha min: um
 *   serviço antigo tem km menor que o de hoje, e quem valida é o servidor (VehicleMileageService).
 * - Oficina: a sugestão escolhida na lista (datalist #oficinas-da-rede) liga a oficina da rede
 *   (workshop_id); texto livre fica só como nome. O servidor refaz a correspondência sem JS.
 *
 * Idempotente: pode ser chamado de novo com o trecho novo como raiz.
 */

/**
 * @param {HTMLFormElement} form
 */
function bindWorkshopCombobox(form) {
    const workshopInput = form.querySelector('[data-workshop-combobox]');
    const workshopId = form.querySelector('[data-workshop-id]');
    const workshopStatus = form.querySelector('[data-workshop-status]');
    const listId = workshopInput?.getAttribute('list');
    const workshopOptions = listId ? Array.from(document.querySelectorAll(`#${CSS.escape(listId)} option`)) : [];

    if (!workshopInput || !workshopId || !workshopStatus) {
        return;
    }

    const sync = () => {
        const typed = workshopInput.value.trim().toLocaleLowerCase('pt-BR');
        const match = workshopOptions.find((option) => option.value.toLocaleLowerCase('pt-BR') === typed);

        workshopId.value = match ? match.dataset.workshopId : '';
        workshopStatus.textContent = typed === ''
            ? ''
            : (match ? 'Oficina da rede.' : 'Oficina fora da rede: fica registrado só o nome.');
    };

    workshopInput.addEventListener('input', sync);
    workshopInput.addEventListener('change', sync);
    sync();
}

/**
 * @param {ParentNode} [root]
 */
export function initGarageMaintenanceForms(root = document) {
    root.querySelectorAll('form[data-garage-maintenance-form]').forEach((form) => {
        if (form.dataset.garageMaintenanceFormReady === 'true') {
            return;
        }

        form.dataset.garageMaintenanceFormReady = 'true';
        bindKilometerRange(form);
        bindWorkshopCombobox(form);
    });
}
