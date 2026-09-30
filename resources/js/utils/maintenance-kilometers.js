/**
 * Faixa de quilometragem aceita para uma manutenção, com a mesma regra do
 * App\Services\Vehicle\VehicleMileageService::assertMaintenanceKilometers() (o servidor continua
 * sendo quem valida; aqui é só a dica do formulário):
 *
 * - piso: a maior quilometragem registrada até a data do serviço; a partir da data do cadastro,
 *   também o hodômetro do cadastro;
 * - teto: a menor quilometragem registrada depois da data; antes da data do cadastro, também o
 *   hodômetro do cadastro.
 *
 * Datas no formato AAAA-MM-DD (comparadas como texto, como o whereDate do servidor).
 *
 * @typedef {{ date: string, kilometers: number }} MileageRecord
 * @typedef {{ registration_kilometers: number|null, registered_on: string|null, records: MileageRecord[] }} MileageVehicle
 * @typedef {{ type: 'record'|'registration', date: string|null }} BoundSource
 * @typedef {{ floor: number, floorSource: BoundSource|null, ceiling: number|null, ceilingSource: BoundSource|null }} KilometerBounds
 */

const NUMBER_FORMAT = new Intl.NumberFormat('pt-BR');

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
