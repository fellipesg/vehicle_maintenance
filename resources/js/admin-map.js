import { elementsWithin, prefersReducedMotion } from './ui/dom';

/**
 * Mapas do admin (Mapa de oficinas, Mapa de proprietários): monta o Leaflet (carregado pela página
 * via CDN em window.L) em cada [data-admin-map] de um [data-admin-map-root].
 *
 * - Os pinos vêm do <script type="application/json" data-admin-map-pins> (id, name, lat, lng, city,
 *   label). O popup é montado com nós DOM e textContent: nome e endereço nunca viram HTML.
 * - O link "Abrir cadastro" do popup copia o href do item da lista ([data-admin-map-pin-link]),
 *   montado no servidor; o JSON não carrega URL nem dado pessoal (.ai/rules/admin.md).
 * - A cor do pino sai dos tokens de resources/css/app.css (data-admin-map-tone): oficina usa
 *   --color-prov-verified e proprietário --color-automotive-800, com borda branca de 2px para não
 *   se confundir com o traçado do mapa.
 * - Com o mapa pronto, cada item da lista mostra "Mostrar no mapa" ([data-admin-map-focus]), que
 *   centraliza o pino e abre o popup. Sem Leaflet (CDN fora do ar), a mensagem de carregamento vira
 *   um aviso de erro e a lista continua servindo de alternativa.
 *
 * Idempotente: cada mapa é montado uma vez (dataset.adminMapReady).
 */

const TONE_TOKENS = {
    workshop: '--color-prov-verified',
    owner: '--color-automotive-800',
};
const TONE_FALLBACKS = {
    workshop: '#0f766e',
    owner: '#243041',
};
const BRAZIL_CENTER = [-14.2, -51.9];
const FOCUS_ZOOM = 14;
const LOAD_ERROR_MESSAGE = 'Não foi possível carregar o mapa. Use a lista ao lado para abrir os cadastros.';

function readPins(root) {
    const source = root.querySelector('script[data-admin-map-pins]');

    if (!source) {
        return [];
    }

    try {
        const pins = JSON.parse(source.textContent || '[]');

        return Array.isArray(pins)
            ? pins.filter((pin) => Number.isFinite(Number(pin?.lat)) && Number.isFinite(Number(pin?.lng)))
            : [];
    } catch {
        return [];
    }
}

function toneColor(tone) {
    const token = TONE_TOKENS[tone] ?? TONE_TOKENS.owner;
    const value = getComputedStyle(document.documentElement).getPropertyValue(token).trim();

    return value !== '' ? value : (TONE_FALLBACKS[tone] ?? TONE_FALLBACKS.owner);
}

function profileHref(root, pinId) {
    const link = root.querySelector(`[data-admin-map-pin-link="${CSS.escape(String(pinId))}"]`);

    return link instanceof HTMLAnchorElement ? link.href : null;
}

/**
 * Popup montado com nós DOM: nome, cidade e endereço são texto livre de cadastro.
 */
function buildPinPopup(pin, href) {
    const box = document.createElement('div');
    const title = document.createElement('strong');
    title.textContent = pin.name || '';
    box.append(title);

    if (pin.label) {
        const label = document.createElement('span');
        label.className = 'block text-xs';
        label.textContent = pin.label;
        box.append(label);
    } else if (pin.city) {
        const city = document.createElement('span');
        city.className = 'block text-xs';
        city.textContent = pin.city;
        box.append(city);
    }

    if (href) {
        const link = document.createElement('a');
        link.href = href;
        link.className = 'link mt-1 inline-block';
        link.textContent = 'Abrir cadastro';
        box.append(link);
    }

    return box;
}

function showStatus(status, message) {
    if (!status) {
        return;
    }

    if (message === null) {
        status.hidden = true;

        return;
    }

    // A região já é role="status": trocar o texto basta para o leitor de tela anunciar.
    status.hidden = false;
    status.replaceChildren(Object.assign(document.createElement('p'), { textContent: message }));
}

function mountMap(container) {
    if (container.dataset.adminMapReady === 'true') {
        return;
    }

    container.dataset.adminMapReady = 'true';

    const root = container.closest('[data-admin-map-root]') ?? document;
    const status = root.querySelector('[data-admin-map-status]');
    const Leaflet = window.L;

    if (!Leaflet || typeof Leaflet.map !== 'function') {
        showStatus(status, LOAD_ERROR_MESSAGE);

        return;
    }

    const pins = readPins(root);
    const color = toneColor(container.dataset.adminMapTone);
    const map = Leaflet.map(container, { scrollWheelZoom: true }).setView(BRAZIL_CENTER, 4);

    Leaflet.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap',
    }).addTo(map);

    const markers = new Map();
    const bounds = [];

    pins.forEach((pin) => {
        const position = [Number(pin.lat), Number(pin.lng)];
        const marker = Leaflet.circleMarker(position, {
            radius: 8,
            fillColor: color,
            color: '#ffffff',
            weight: 2,
            opacity: 1,
            fillOpacity: 0.9,
        }).addTo(map);

        marker.bindPopup(buildPinPopup(pin, profileHref(root, pin.id)));
        markers.set(String(pin.id), marker);
        bounds.push(position);
    });

    if (bounds.length > 0) {
        map.fitBounds(bounds, { padding: [40, 40], maxZoom: 12 });
    }

    elementsWithin(root, '[data-admin-map-focus]').forEach((button) => {
        const marker = markers.get(button.dataset.adminMapFocus ?? '');

        if (!marker) {
            return;
        }

        button.hidden = false;
        button.addEventListener('click', () => {
            map.setView(marker.getLatLng(), Math.max(map.getZoom(), FOCUS_ZOOM), { animate: !prefersReducedMotion() });
            marker.openPopup();
            container.scrollIntoView({ block: 'nearest', behavior: prefersReducedMotion() ? 'auto' : 'smooth' });
        });
    });

    showStatus(status, null);

    // A área do mapa ganha altura depois do layout (grid e dvh): recalcula os tiles.
    window.setTimeout(() => map.invalidateSize(), 100);
}

/**
 * @param {ParentNode} [root]
 */
export function initAdminMaps(root = document) {
    elementsWithin(root, '[data-admin-map]').forEach(mountMap);
}
