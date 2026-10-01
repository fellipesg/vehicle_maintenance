/**
 * Contas do <x-ui.image-cropper> (resources/js/ui/image-cropper.js), sem DOM e sem imports, para
 * rodarem no Node nos testes (tests/Feature/Ui/ImageCropperTest.php).
 *
 * Convenções:
 * - image: { width, height } em pixels da imagem original, já na orientação do EXIF (naturalWidth e
 *   naturalHeight do <img>);
 * - frame: { left, top, width, height } da moldura de recorte, em pixels de tela, relativos ao palco;
 * - view: { centerX, centerY, zoom }: o ponto da imagem (em pixels da imagem) que fica no centro da
 *   moldura e o zoom. Zoom 1 é a menor escala em que a imagem ainda cobre a moldura inteira; não há
 *   zoom abaixo disso, então o recorte nunca tem sobra vazia.
 *
 * Como a posição fica em pixels da imagem e o zoom é relativo à moldura, redimensionar o palco (girar
 * o celular, abrir o teclado) não muda o recorte.
 */

export const MIN_ZOOM = 1;
export const MAX_ZOOM = 4;

/**
 * "16:9" (ou "16/9", "16x9") em { width, height, ratio }. Valor inválido devolve null.
 *
 * @param {unknown} value
 * @returns {{ width: number, height: number, ratio: number }|null}
 */
export function parseAspect(value) {
    const match = /^\s*(\d+(?:\.\d+)?)\s*[:/x]\s*(\d+(?:\.\d+)?)\s*$/.exec(String(value ?? ''));

    if (!match) {
        return null;
    }

    const width = Number(match[1]);
    const height = Number(match[2]);

    if (!(width > 0) || !(height > 0)) {
        return null;
    }

    return { width, height, ratio: width / height };
}

/**
 * Maior moldura na proporção pedida que cabe no palco com a margem dada, centralizada.
 *
 * @param {number} stageWidth
 * @param {number} stageHeight
 * @param {number} ratio largura / altura
 * @param {number} [padding] margem mínima em cada lado
 * @returns {{ left: number, top: number, width: number, height: number }}
 */
export function fitFrame(stageWidth, stageHeight, ratio, padding = 0) {
    const availableWidth = Math.max(1, stageWidth - padding * 2);
    const availableHeight = Math.max(1, stageHeight - padding * 2);
    let width = availableWidth;
    let height = width / ratio;

    if (height > availableHeight) {
        height = availableHeight;
        width = height * ratio;
    }

    return {
        left: (stageWidth - width) / 2,
        top: (stageHeight - height) / 2,
        width,
        height,
    };
}

/**
 * Menor escala (pixels de tela por pixel da imagem) em que a imagem cobre a moldura.
 *
 * @param {{ width: number, height: number }} image
 * @param {{ width: number, height: number }} frame
 * @returns {number}
 */
export function coverScale(image, frame) {
    return Math.max(frame.width / image.width, frame.height / image.height);
}

/**
 * Escala da imagem na tela para o zoom da view.
 *
 * @param {{ zoom: number }} view
 * @param {{ width: number, height: number }} image
 * @param {{ width: number, height: number }} frame
 * @returns {number}
 */
export function scaleOf(view, image, frame) {
    return coverScale(image, frame) * view.zoom;
}

/**
 * Enquadramento inicial: imagem centralizada, sem zoom.
 *
 * @param {{ width: number, height: number }} image
 * @returns {{ centerX: number, centerY: number, zoom: number }}
 */
export function initialView(image) {
    return { centerX: image.width / 2, centerY: image.height / 2, zoom: MIN_ZOOM };
}

/**
 * Mantém o zoom entre MIN_ZOOM e MAX_ZOOM e o recorte dentro da imagem.
 *
 * @param {{ centerX: number, centerY: number, zoom: number }} view
 * @param {{ width: number, height: number }} image
 * @param {{ width: number, height: number }} frame
 * @returns {{ centerX: number, centerY: number, zoom: number }}
 */
export function clampView(view, image, frame) {
    const zoom = clamp(Number.isFinite(view.zoom) ? view.zoom : MIN_ZOOM, MIN_ZOOM, MAX_ZOOM);
    const scale = coverScale(image, frame) * zoom;
    const halfWidth = Math.min(image.width / 2, frame.width / scale / 2);
    const halfHeight = Math.min(image.height / 2, frame.height / scale / 2);

    return {
        centerX: clamp(Number.isFinite(view.centerX) ? view.centerX : image.width / 2, halfWidth, image.width - halfWidth),
        centerY: clamp(Number.isFinite(view.centerY) ? view.centerY : image.height / 2, halfHeight, image.height - halfHeight),
        zoom,
    };
}

/**
 * Arrasta a imagem deltaX/deltaY pixels de tela (positivo: para a direita e para baixo).
 *
 * @param {{ centerX: number, centerY: number, zoom: number }} view
 * @param {number} deltaX
 * @param {number} deltaY
 * @param {{ width: number, height: number }} image
 * @param {{ width: number, height: number }} frame
 * @returns {{ centerX: number, centerY: number, zoom: number }}
 */
export function panView(view, deltaX, deltaY, image, frame) {
    const scale = scaleOf(view, image, frame);

    return clampView({
        centerX: view.centerX - deltaX / scale,
        centerY: view.centerY - deltaY / scale,
        zoom: view.zoom,
    }, image, frame);
}

/**
 * Troca o zoom mantendo parado o ponto da imagem sob anchor (cursor da roda, meio da pinça), em
 * pixels do palco. Sem anchor, o centro da moldura.
 *
 * @param {{ centerX: number, centerY: number, zoom: number }} view
 * @param {number} nextZoom
 * @param {{ width: number, height: number }} image
 * @param {{ left: number, top: number, width: number, height: number }} frame
 * @param {{ x: number, y: number }|null} [anchor]
 * @returns {{ centerX: number, centerY: number, zoom: number }}
 */
export function zoomView(view, nextZoom, image, frame, anchor = null) {
    const frameCenterX = frame.left + frame.width / 2;
    const frameCenterY = frame.top + frame.height / 2;
    const point = anchor ?? { x: frameCenterX, y: frameCenterY };
    const scale = scaleOf(view, image, frame);
    const zoom = clamp(Number.isFinite(nextZoom) ? nextZoom : view.zoom, MIN_ZOOM, MAX_ZOOM);
    const nextScale = coverScale(image, frame) * zoom;
    const imageX = view.centerX + (point.x - frameCenterX) / scale;
    const imageY = view.centerY + (point.y - frameCenterY) / scale;

    return clampView({
        centerX: imageX - (point.x - frameCenterX) / nextScale,
        centerY: imageY - (point.y - frameCenterY) / nextScale,
        zoom,
    }, image, frame);
}

/**
 * Transformação CSS da imagem no palco (transform-origin no canto superior esquerdo):
 * translate(x, y) scale(scale).
 *
 * @param {{ centerX: number, centerY: number, zoom: number }} view
 * @param {{ width: number, height: number }} image
 * @param {{ left: number, top: number, width: number, height: number }} frame
 * @returns {{ x: number, y: number, scale: number }}
 */
export function imageTransform(view, image, frame) {
    const scale = scaleOf(view, image, frame);

    return {
        x: frame.left + frame.width / 2 - view.centerX * scale,
        y: frame.top + frame.height / 2 - view.centerY * scale,
        scale,
    };
}

/**
 * Área recortada, em pixels da imagem original. Só depende da proporção da moldura, não do tamanho
 * dela na tela.
 *
 * @param {{ centerX: number, centerY: number, zoom: number }} view
 * @param {{ width: number, height: number }} image
 * @param {{ width: number, height: number }} frame
 * @returns {{ x: number, y: number, width: number, height: number }}
 */
export function cropRect(view, image, frame) {
    const scale = scaleOf(view, image, frame);
    const width = Math.min(image.width, frame.width / scale);
    const height = Math.min(image.height, frame.height / scale);

    return {
        x: clamp(view.centerX - width / 2, 0, image.width - width),
        y: clamp(view.centerY - height / 2, 0, image.height - height),
        width,
        height,
    };
}

/**
 * Tamanho do arquivo final: a proporção exata pedida, com o lado maior limitado a maxSide e sem
 * ampliar um recorte menor que isso.
 *
 * @param {{ width: number, height: number }} crop
 * @param {{ width: number, height: number }} aspect
 * @param {number} maxSide
 * @returns {{ width: number, height: number }}
 */
export function outputSize(crop, aspect, maxSide) {
    const longest = Math.max(1, Math.round(Math.min(maxSide, Math.max(crop.width, crop.height))));
    const shortest = Math.max(1, Math.round((longest * Math.min(aspect.width, aspect.height)) / Math.max(aspect.width, aspect.height)));

    return aspect.width >= aspect.height
        ? { width: longest, height: shortest }
        : { width: shortest, height: longest };
}

/**
 * Onde está o recorte, para o leitor de tela: zoom em % e posição em cada eixo (0 = encostado à
 * esquerda ou em cima; 100 = à direita ou embaixo; null = a imagem não sobra nesse eixo).
 *
 * @param {{ centerX: number, centerY: number, zoom: number }} view
 * @param {{ width: number, height: number }} image
 * @param {{ width: number, height: number }} frame
 * @returns {{ zoom: number, horizontal: number|null, vertical: number|null }}
 */
export function describeView(view, image, frame) {
    const crop = cropRect(view, image, frame);
    const spareWidth = image.width - crop.width;
    const spareHeight = image.height - crop.height;

    return {
        zoom: Math.round(view.zoom * 100),
        horizontal: spareWidth > 0.5 ? Math.round((crop.x / spareWidth) * 100) : null,
        vertical: spareHeight > 0.5 ? Math.round((crop.y / spareHeight) * 100) : null,
    };
}

/**
 * @param {number} value
 * @param {number} min
 * @param {number} max
 * @returns {number}
 */
function clamp(value, min, max) {
    return Math.min(Math.max(value, min), max);
}
