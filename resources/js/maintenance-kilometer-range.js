import {
    describeKilometerBounds,
    isWithinKilometerBounds,
    kilometerBounds,
} from './utils/maintenance-kilometers';

/**
 * Dica da faixa de quilometragem nos formulários de manutenção do Proprietário
 * (user/maintenances/_form) e do Lojista (garage/maintenances/create).
 *
 * O formulário leva data-mileage (App\Support\Vehicle\MaintenanceMileageContext) e os campos
 * [data-maintenance-vehicle] (ou data-vehicle-id no formulário, quando o veículo é fixo),
 * [data-maintenance-date], [data-maintenance-km] e o parágrafo [data-maintenance-km-range]
 * (aria-live). A faixa é a mesma do servidor (resources/js/utils/maintenance-kilometers.js).
 *
 * Não preenche o campo nem trava o envio: um serviço antigo pode ter km menor que o hodômetro de
 * hoje, e quem decide é o servidor, que devolve a mensagem no campo. Fora da faixa, só avisa.
 */

/**
 * @param {HTMLElement} form
 * @returns {Array<Record<string, any>>}
 */
function mileageOf(form) {
    try {
        const parsed = JSON.parse(form.dataset.mileage || '[]');

        return Array.isArray(parsed) ? parsed : [];
    } catch {
        return [];
    }
}

/**
 * Liga a dica ao formulário e devolve a função que a recalcula.
 *
 * @param {HTMLElement} form
 * @returns {() => void}
 */
export function bindKilometerRange(form) {
    const vehicles = mileageOf(form);
    const vehicleSelect = form.querySelector('[data-maintenance-vehicle]');
    const dateInput = form.querySelector('[data-maintenance-date]');
    const kmInput = form.querySelector('[data-maintenance-km]');
    const range = form.querySelector('[data-maintenance-km-range]');

    const currentVehicle = () => {
        const id = vehicleSelect ? vehicleSelect.value : form.dataset.vehicleId;

        return vehicles.find((vehicle) => String(vehicle.id) === String(id)) ?? null;
    };

    const sync = () => {
        if (!range) {
            return;
        }

        const vehicle = currentVehicle();

        if (!vehicle) {
            range.textContent = '';
            range.hidden = true;

            return;
        }

        const bounds = kilometerBounds(vehicle, dateInput?.value || null);
        const description = describeKilometerBounds(bounds);
        const typed = kmInput && kmInput.value !== '' ? Number(kmInput.value) : null;
        const outside = typed !== null && Number.isFinite(typed) && !isWithinKilometerBounds(bounds, typed);

        range.textContent = outside
            ? `Atenção: a quilometragem digitada fica fora da faixa desta data. ${description}`
            : description;
        range.hidden = range.textContent === '';
        range.classList.toggle('text-warning', outside);
        range.classList.toggle('font-medium', outside);
        range.classList.toggle('text-muted-foreground', !outside);
    };

    vehicleSelect?.addEventListener('change', sync);
    dateInput?.addEventListener('change', sync);
    dateInput?.addEventListener('input', sync);
    kmInput?.addEventListener('input', sync);
    sync();

    return sync;
}
