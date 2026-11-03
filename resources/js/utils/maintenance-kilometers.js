/**
 * Faixa de quilometragem aceita para uma manutenção, com a mesma regra do
 * App\Services\Vehicle\VehicleMileageService::assertMaintenanceKilometers() (o servidor continua
 * sendo quem valida; aqui é só a dica do formulário):
 *
 * - piso: a maior quilometragem registrada até a data do serviço; a partir da data do cadastro,
 *   também o hodômetro do cadastro;
 * - teto: a menor quilometragem registrada depois da data; antes da data do cadastro, também o
 *   hodômetro do cadastro; e sempre o teto de plausibilidade derivado do ano do veículo
 *   (PLAUSIBILITY_BASE_KILOMETERS + PLAUSIBILITY_KILOMETERS_PER_YEAR por ano de vida, e nunca menos
 *   de um ano de folga acima do que o veículo já registra), que é o único teto quando não há
 *   registro posterior. Veículo sem ano conhecido não tem esse teto.
 *
 * Datas no formato AAAA-MM-DD (comparadas como texto, como o whereDate do servidor).
 *
 * @typedef {{ date: string, kilometers: number }} MileageRecord
 * @typedef {{ year: number|null, current_kilometers: number|null, registration_kilometers: number|null, registered_on: string|null, records: MileageRecord[] }} MileageVehicle
 * @typedef {{ type: 'record'|'registration'|'plausibility', date: string|null, year?: number }} BoundSource
 * @typedef {{ floor: number, floorSource: BoundSource|null, ceiling: number|null, ceilingSource: BoundSource|null }} KilometerBounds
 */

const NUMBER_FORMAT = new Intl.NumberFormat('pt-BR');

// Espelham VehicleMileageService::PLAUSIBILITY_BASE_KILOMETERS e ::PLAUSIBILITY_KILOMETERS_PER_YEAR.
const PLAUSIBILITY_BASE_KILOMETERS = 50_000;
const PLAUSIBILITY_KILOMETERS_PER_YEAR = 100_000;

/**
 * @param {string|null|undefined} value
 * @returns {string|null}
 */
function normalizeDate(value) {
    return typeof value === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(value) ? value : null;
}

/**
 * @param {string} date AAAA-MM-DD
 * @returns {string} DD/MM/AAAA
 */
export function formatDate(date) {
    const [year, month, day] = date.split('-');

    return `${day}/${month}/${year}`;
}

/**
 * @param {number} kilometers
 * @returns {string}
 */
export function formatKilometers(kilometers) {
    return `${NUMBER_FORMAT.format(kilometers)} km`;
}

/**
 * Espelha VehicleMileageService::plausibilityCeiling(): null quando o ano é desconhecido, e sempre
 * um ano de folga acima do que o veículo já registra.
 *
 * @param {MileageVehicle} vehicle
 * @param {number} floor
 * @returns {number|null}
 */
function plausibilityCeiling(vehicle, floor) {
    const year = Number(vehicle?.year);

    if (!Number.isFinite(year) || year < 1900) {
        return null;
    }

    const yearsOfLife = Math.max(0, new Date().getFullYear() - year) + 1;
    const alreadyRun = Math.max(
        floor,
        Number(vehicle?.current_kilometers) || 0,
        Number(vehicle?.registration_kilometers) || 0,
    );

    return Math.max(
        PLAUSIBILITY_BASE_KILOMETERS + (yearsOfLife * PLAUSIBILITY_KILOMETERS_PER_YEAR),
        alreadyRun + PLAUSIBILITY_KILOMETERS_PER_YEAR,
    );
}

/**
 * @param {MileageVehicle} vehicle
 * @param {string|null} maintenanceDate
 * @returns {KilometerBounds}
 */
export function kilometerBounds(vehicle, maintenanceDate) {
    const date = normalizeDate(maintenanceDate);
    const registeredOn = normalizeDate(vehicle?.registered_on);
    const registration = Number.isFinite(Number(vehicle?.registration_kilometers)) && vehicle?.registration_kilometers !== null
        ? Number(vehicle.registration_kilometers)
        : null;
    const records = (Array.isArray(vehicle?.records) ? vehicle.records : [])
        .filter((record) => normalizeDate(record?.date) !== null && Number.isFinite(Number(record?.kilometers)))
        .map((record) => ({ date: record.date, kilometers: Number(record.kilometers) }));

    // Sem data de cadastro, o servidor trata a manutenção como posterior ao cadastro.
    const afterRegistration = date === null || registeredOn === null || date >= registeredOn;

    let floor = 0;
    /** @type {BoundSource|null} */
    let floorSource = null;

    records
        .filter((record) => date === null || record.date <= date)
        .forEach((record) => {
            if (record.kilometers > floor || (record.kilometers === floor && floorSource !== null && record.date > (floorSource.date ?? ''))) {
                floor = record.kilometers;
                floorSource = { type: 'record', date: record.date };
            }
        });

    if (afterRegistration && registration !== null && registration > 0 && registration >= floor) {
        floor = registration;
        floorSource = { type: 'registration', date: registeredOn };
    }

    /** @type {number|null} */
    let ceiling = null;
    /** @type {BoundSource|null} */
    let ceilingSource = null;

    if (date !== null) {
        records
            .filter((record) => record.date > date)
            .forEach((record) => {
                if (ceiling === null || record.kilometers < ceiling) {
                    ceiling = record.kilometers;
                    ceilingSource = { type: 'record', date: record.date };
                }
            });

        if (!afterRegistration && registration !== null && (ceiling === null || registration <= ceiling)) {
            ceiling = registration;
            ceilingSource = { type: 'registration', date: registeredOn };
        }
    }

    const plausibility = plausibilityCeiling(vehicle, floor);

    if (plausibility !== null && (ceiling === null || plausibility < ceiling)) {
        ceiling = plausibility;
        ceilingSource = { type: 'plausibility', date: null, year: Number(vehicle.year) };
    }

    return { floor, floorSource, ceiling, ceilingSource };
}

/**
 * @param {BoundSource|null} source
 * @returns {string}
 */
function describeSource(source) {
    if (source === null) {
        return '';
    }

    if (source.type === 'plausibility') {
        return `máximo plausível para um veículo ${source.year}`;
    }

    if (source.type === 'registration') {
        return source.date ? `hodômetro no cadastro, em ${formatDate(source.date)}` : 'hodômetro no cadastro';
    }

    return source.date ? `registro de ${formatDate(source.date)}` : 'outro registro';
}

/**
 * "Entre 50.000 km (hodômetro no cadastro, em 10/01/2025) e 60.000 km (registro de 12/06/2025)."
 *
 * @param {KilometerBounds} bounds
 * @returns {string} vazio quando não há limite a mostrar
 */
export function describeKilometerBounds(bounds) {
    const hasFloor = bounds.floor > 0;
    const hasCeiling = bounds.ceiling !== null;

    if (hasFloor && hasCeiling) {
        return `Entre ${formatKilometers(bounds.floor)} (${describeSource(bounds.floorSource)}) e ${formatKilometers(bounds.ceiling)} (${describeSource(bounds.ceilingSource)}).`;
    }

    if (hasFloor) {
        return `A partir de ${formatKilometers(bounds.floor)} (${describeSource(bounds.floorSource)}).`;
    }

    if (hasCeiling) {
        return `Até ${formatKilometers(bounds.ceiling)} (${describeSource(bounds.ceilingSource)}).`;
    }

    return '';
}

/**
 * @param {KilometerBounds} bounds
 * @param {number} kilometers
 * @returns {boolean}
 */
export function isWithinKilometerBounds(bounds, kilometers) {
    return kilometers >= bounds.floor && (bounds.ceiling === null || kilometers <= bounds.ceiling);
}
