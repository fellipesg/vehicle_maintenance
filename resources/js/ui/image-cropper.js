import { elementsWithin, prefersReducedMotion } from './dom';
import { fileMatchesAccept, formatFileSize } from './file-input';
import { icon } from './icons';
import { escapeHtml } from '../utils/html';
import {
    MAX_ZOOM,
    MIN_ZOOM,
    clampView,
    cropRect,
    describeView,
    fitFrame,
    imageTransform,
    initialView,
    outputSize,
    panView,
    parseAspect,
    zoomView,
} from './image-cropper-geometry';

/**
 * <x-ui.image-cropper>: recorte de imagem na proporção fixa (capa 16:9 e 9:16 do veículo), em canvas,
 * sem dependência. Sem JS (ou sem canvas.toBlob e DataTransfer), fica o <input type="file"> comum.
 *
 * Com JS o componente tem quatro estados (data-state na raiz):
 * - empty: área de soltar (o <label> do input) com "Escolher imagem ou arraste até aqui";
 * - current: a imagem que já existe (prop current), inteira (object-contain), e "Trocar imagem";
 * - cropping: o palco com a moldura na proporção. Arrastar (mouse ou toque), setas do teclado, roda
 *   e pinça posicionam e aproximam; o zoom também tem controle deslizante e botões − e +. "Usar este
 *   recorte" gera um JPEG (qualidade 0.85, lado maior até 1920 px) e o coloca no input via
 *   DataTransfer; "Cancelar" volta ao que valia antes (.ai/rules/vehicles.md: cancelar mantém a foto
 *   anterior daquela orientação);
 * - done: a prévia do recorte, inteira, com "Ajustar recorte", "Trocar imagem" e "Descartar".
 *
 * O arquivo original nunca sobe: se o formulário for enviado com o palco aberto, o envio espera o
 * recorte do enquadramento atual e segue depois (requestSubmit com o mesmo botão). O servidor
 * continua validando tipo e tamanho.
 *
 * Acessibilidade: o palco é focável (role="application", porque as setas movem a imagem) e descrito
 * pelas instruções visíveis; uma região aria-live conta o que mudou (imagem aberta, zoom e posição,
 * recorte pronto) e os erros vão para a mensagem role="alert". Nada é animado, então
 * prefers-reduced-motion só muda a rolagem até o palco.
 */

const ROOT_SELECTOR = '[data-image-cropper]';

/** Foto maior que isto nem é aberta: decodificar consome memória demais no celular. */
const SOURCE_MAX_BYTES = 40 * 1024 * 1024;

/** Passo das setas, em pixels de tela; com Shift, o maior. */
const KEY_STEP = 10;
const KEY_STEP_LARGE = 50;

/** Passo do zoom nos botões − e + e nas teclas + e −. */
const ZOOM_STEP = 0.25;

/** Qualidades tentadas depois da configurada quando o JPEG passa do limite de tamanho. */
const FALLBACK_QUALITIES = [0.75, 0.65, 0.5];

/** Espera depois do último movimento antes de anunciar zoom e posição. */
const ANNOUNCE_DELAY = 700;

/** @type {WeakMap<HTMLFormElement, Set<{ needsFlush: () => boolean, flush: () => Promise<boolean>, reset: () => void }>>} */
const formControllers = new WeakMap();

/**
 * Liga os recortadores de root (e o próprio root). Idempotente.
 *
 * @param {ParentNode} [root]
 */
export function initImageCroppers(root = document) {
    if (!supportsCropping()) {
        return;
    }

    elementsWithin(root, ROOT_SELECTOR).forEach(enhanceImageCropper);
}

function supportsCropping() {
    try {
        const canvas = document.createElement('canvas');

        return typeof new DataTransfer().items?.add === 'function'
            && typeof canvas.toBlob === 'function'
            && typeof canvas.getContext === 'function'
            && typeof URL.createObjectURL === 'function';
    } catch {
        return false;
    }
}

/**
 * @param {Element} container
 */
function enhanceImageCropper(container) {
    if (!(container instanceof HTMLElement) || container.dataset.imageCropperReady === 'true') {
        return;
    }

    const part = (name) => container.querySelector(`[data-image-cropper-${name}]`);
    const input = container.querySelector('input[type="file"][data-image-cropper-input]');
    const previewImage = part('preview-image');
    const sourceImage = part('image');
    const zoomInput = part('zoom');
    const elements = {
        dropzone: part('dropzone'),
        preview: part('preview'),
        previewCaption: part('preview-caption'),
        actions: part('actions'),
        changeButton: part('change'),
        adjustButton: part('adjust'),
        discardButton: part('discard'),
        editor: part('editor'),
        stage: part('stage'),
        frame: part('frame'),
        zoomInButton: part('zoom-in'),
        zoomOutButton: part('zoom-out'),
        applyButton: part('apply'),
        cancelButton: part('cancel'),
        feedback: part('feedback'),
        status: part('status'),
    };
    const aspect = parseAspect(container.dataset.aspect);

    if (
        !(input instanceof HTMLInputElement)
        || !(previewImage instanceof HTMLImageElement)
        || !(sourceImage instanceof HTMLImageElement)
        || !(zoomInput instanceof HTMLInputElement)
        || aspect === null
        || Object.values(elements).some((element) => !(element instanceof HTMLElement))
    ) {
        return;
    }

    container.dataset.imageCropperReady = 'true';
    container.dataset.enhanced = '';

    const {
        dropzone, preview, previewCaption, actions, changeButton, adjustButton, discardButton, editor,
        stage, frame: frameElement, zoomInButton, zoomOutButton, applyButton, cancelButton, feedback, status,
    } = elements;

    const settings = {
        aspect,
        maxSide: Math.max(1, Number(container.dataset.maxSide) || 1920),
        quality: Math.min(1, Math.max(0.1, Number(container.dataset.quality) || 0.85)),
        maxBytes: Number(container.dataset.maxBytes) || null,
        maxLabel: container.dataset.maxLabel || '',
        typesLabel: container.dataset.typesLabel || '',
        label: container.dataset.label || '',
    };
    const initialPreview = {
        src: previewImage.getAttribute('src') ?? '',
        alt: previewImage.getAttribute('alt') ?? '',
        caption: previewCaption.textContent.trim(),
    };
    const initialState = () => (initialPreview.src !== '' ? 'current' : 'empty');
    const serverInvalid = input.getAttribute('aria-invalid') === 'true';
    const applyLabel = applyButton.querySelector('[data-slot="label"]');
    const applyText = applyLabel?.textContent ?? '';
    const pointers = new Map();

    /** @type {'empty'|'current'|'cropping'|'done'} */
    let state = initialState();
    /** Imagem aberta no palco: { file, url, width, height }. */
    let source = null;
    let view = null;
    let frame = null;
    /** Último recorte aplicado: { file, url, width, height, source, view }. */
    let committed = null;
    /** @type {Promise<boolean>|null} */
    let applying = null;
    let loadToken = 0;
    let isSyncing = false;
    let dragDepth = 0;
    let pinch = null;
    let announceTimer = 0;

    const release = (url) => {
        if (url) {
            URL.revokeObjectURL(url);
        }
    };

    const announce = (message) => {
        window.clearTimeout(announceTimer);
        status.textContent = message;
    };

    const setInputFile = (file) => {
        const transfer = new DataTransfer();

        if (file) {
            transfer.items.add(file);
        }

        input.files = transfer.files;
    };

    /** Arquivo que o input deve ter fora do palco: o recorte aplicado ou nada. */
    const settledFile = () => (committed ? committed.file : null);

    const notifyChange = () => {
        isSyncing = true;
        input.dispatchEvent(new Event('change', { bubbles: true }));
        isSyncing = false;
    };

    const setFeedback = (message) => {
        const describedBy = (input.getAttribute('aria-describedby') ?? '').split(/\s+/).filter((id) => id && id !== feedback.id);

        if (!message) {
            feedback.replaceChildren();

            if (serverInvalid) {
                input.setAttribute('aria-invalid', 'true');
            } else {
                input.removeAttribute('aria-invalid');
            }
        } else {
            feedback.innerHTML = `${icon('exclamation-circle', { variant: 'solid', className: 'mt-0.5 size-4' })}<span><span class="sr-only">Erro: </span>${escapeHtml(message)}</span>`;
            input.setAttribute('aria-invalid', 'true');

            if (feedback.id) {
                describedBy.push(feedback.id);
            }
        }

        if (describedBy.length > 0) {
            input.setAttribute('aria-describedby', describedBy.join(' '));
        } else {
            input.removeAttribute('aria-describedby');
        }
    };

    const setBusy = (isBusy, { applyingCrop = false } = {}) => {
        if (isBusy) {
            container.setAttribute('aria-busy', 'true');
        } else {
            container.removeAttribute('aria-busy');
        }

        if (!applyingCrop && isBusy) {
            return;
        }

        applyButton.disabled = isBusy;
        cancelButton.disabled = isBusy;

        if (isBusy) {
            applyButton.setAttribute('aria-busy', 'true');
        } else {
            applyButton.removeAttribute('aria-busy');
        }

        const loadingLabel = applyButton.getAttribute('data-loading-label');

        if (applyLabel && loadingLabel) {
            applyLabel.textContent = isBusy ? loadingLabel : applyText;
        }
    };

    const setState = (next) => {
        state = next;
        container.dataset.state = next;
        dropzone.hidden = next !== 'empty';
        preview.hidden = next !== 'current' && next !== 'done';
        actions.hidden = next !== 'current' && next !== 'done';
        editor.hidden = next !== 'cropping';
        adjustButton.hidden = next !== 'done';
        discardButton.hidden = next !== 'done';

        // Fora do estado vazio o input sai da ordem do Tab: quem abre o seletor é "Trocar imagem".
        if (next === 'empty') {
            input.removeAttribute('tabindex');
        } else {
            input.tabIndex = -1;
        }

        if (next === 'done' && committed) {
            previewImage.src = committed.url;
            previewImage.alt = settings.label ? `Novo recorte: ${settings.label}` : 'Novo recorte';
            previewCaption.textContent = `Novo recorte, ainda não salvo · ${committed.width} × ${committed.height} px · ${formatFileSize(committed.file.size)}`;
        } else if (next === 'current') {
            previewImage.src = initialPreview.src;
            previewImage.alt = initialPreview.alt;
            previewCaption.textContent = initialPreview.caption;
        }

        if (next !== 'cropping') {
            pointers.clear();
            pinch = null;
            delete stage.dataset.panning;
        }
    };

    const render = () => {
        if (!source || !frame || !view) {
            return;
        }

        const { x, y, scale } = imageTransform(view, source, frame);
        const percent = Math.round(view.zoom * 100);

        sourceImage.style.transform = `translate3d(${x}px, ${y}px, 0) scale(${scale})`;
        zoomInput.value = String(percent);
        zoomInput.setAttribute('aria-valuetext', `${percent}%`);
        setDisabledLook(zoomOutButton, view.zoom <= MIN_ZOOM + 0.001);
        setDisabledLook(zoomInButton, view.zoom >= MAX_ZOOM - 0.001);
    };

    const layout = () => {
        if (state !== 'cropping' || !source) {
            return;
        }

        const width = stage.clientWidth;
        const height = stage.clientHeight;

        if (width === 0 || height === 0) {
            return;
        }

        frame = fitFrame(width, height, settings.aspect.ratio, Math.max(12, Math.round(Math.min(width, height) * 0.06)));
        frameElement.style.left = `${frame.left}px`;
        frameElement.style.top = `${frame.top}px`;
        frameElement.style.width = `${frame.width}px`;
        frameElement.style.height = `${frame.height}px`;
        sourceImage.style.width = `${source.width}px`;
        sourceImage.style.height = `${source.height}px`;
        view = clampView(view ?? initialView(source), source, frame);
        render();
    };

    const describePosition = () => {
        if (!source || !frame || !view) {
            return '';
        }

        const { zoom, horizontal, vertical } = describeView(view, source, frame);
        const place = [];

        if (horizontal !== null) {
            place.push(horizontal === 0 ? 'encostado à esquerda' : horizontal === 100 ? 'encostado à direita' : `a ${horizontal}% da esquerda`);
        }

        if (vertical !== null) {
            place.push(vertical === 0 ? 'encostado em cima' : vertical === 100 ? 'encostado embaixo' : `a ${vertical}% do topo`);
        }

        return place.length > 0 ? `Zoom de ${zoom}%. Recorte ${place.join(' e ')}.` : `Zoom de ${zoom}%.`;
    };

    const scheduleAnnouncement = () => {
        window.clearTimeout(announceTimer);
        announceTimer = window.setTimeout(() => {
            status.textContent = describePosition();
        }, ANNOUNCE_DELAY);
    };

    const updateView = (nextView, { announcePosition = true } = {}) => {
        view = nextView;
        render();

        if (announcePosition) {
            scheduleAnnouncement();
        }
    };

    const openEditor = () => {
        setState('cropping');
        layout();
        editor.scrollIntoView?.({ block: 'nearest', behavior: prefersReducedMotion() ? 'auto' : 'smooth' });
        stage.focus({ preventScroll: true });
    };

    const validateFile = (file) => {
        if (!fileMatchesAccept(file, input.accept)) {
            return `"${file.name}" não é de um tipo aceito.${settings.typesLabel ? ` Envie ${settings.typesLabel}.` : ''}`;
        }

        if (file.size > SOURCE_MAX_BYTES) {
            return `"${file.name}" tem ${formatFileSize(file.size)}. Escolha uma foto de até ${formatFileSize(SOURCE_MAX_BYTES)}.`;
        }

        return null;
    };

    const openFile = async (file, { fromDrop = false, notice = '' } = {}) => {
        const problem = validateFile(file);

        if (problem) {
            setFeedback(problem);

            if (state !== 'cropping') {
                setInputFile(settledFile());
            }

            return;
        }

        const token = ++loadToken;
        const url = URL.createObjectURL(file);

        setFeedback(null);
        setBusy(true);
        announce('Abrindo a imagem…');

        try {
            const size = await loadImage(sourceImage, url);

            if (token !== loadToken) {
                release(url);

                return;
            }

            if (source && source !== committed?.source) {
                release(source.url);
            }

            source = { file, url, width: size.width, height: size.height };
            view = initialView(source);

            if (fromDrop) {
                setInputFile(file);
            }

            openEditor();
            announce(`${notice}Imagem aberta. Arraste ou use as setas para enquadrar e o zoom para aproximar. Depois escolha Usar este recorte.`);
        } catch {
            if (token !== loadToken) {
                return;
            }

            release(url);
            setFeedback(`Não foi possível abrir "${file.name}". Escolha uma foto${settings.typesLabel ? ` em ${settings.typesLabel}` : ''}.`);

            if (state === 'cropping' && source) {
                // A imagem nova não abriu: o palco volta à que estava sendo enquadrada.
                sourceImage.src = source.url;
            } else {
                setInputFile(settledFile());
            }
        } finally {
            if (token === loadToken) {
                setBusy(false);
            }
        }
    };

    const apply = ({ moveFocus = true } = {}) => {
        if (applying) {
            return applying;
        }

        if (state !== 'cropping' || !source || !view) {
            return Promise.resolve(true);
        }

        const editedSource = source;
        const editedView = view;
        const crop = cropRect(editedView, editedSource, frame ?? fitFrame(settings.aspect.ratio * 1000, 1000, settings.aspect.ratio));
        const size = outputSize(crop, settings.aspect, settings.maxSide);

        setBusy(true, { applyingCrop: true });
        announce('Recortando a imagem…');

        applying = renderJpeg(sourceImage, crop, size, settings)
            .then((blob) => {
                const file = new File([blob], croppedFileName(editedSource.file.name), { type: 'image/jpeg', lastModified: Date.now() });

                if (committed) {
                    release(committed.url);

                    if (committed.source !== editedSource) {
                        release(committed.source.url);
                    }
                }

                committed = { file, url: URL.createObjectURL(blob), width: size.width, height: size.height, source: editedSource, view: editedView };
                source = null;
                setInputFile(file);
                setFeedback(null);
                setState('done');
                notifyChange();
                announce(`Recorte pronto: ${size.width} × ${size.height} px, ${formatFileSize(file.size)}. A imagem é enviada quando você salvar.`);

                if (moveFocus) {
                    adjustButton.focus();
                }

                return true;
            })
            .catch((error) => {
                setFeedback(error?.userMessage ?? 'Não foi possível recortar a imagem. Tente de novo ou escolha outra foto.');
                announce('');

                return false;
            })
            .finally(() => {
                applying = null;
                setBusy(false, { applyingCrop: true });
            });

        return applying;
    };

    const cancel = () => {
        if (state !== 'cropping' || applying) {
            return;
        }

        loadToken += 1;

        if (source && source !== committed?.source) {
            release(source.url);
        }

        source = null;
        view = null;
        setInputFile(settledFile());
        setFeedback(null);
        setBusy(false);
        setState(committed ? 'done' : initialState());
        notifyChange();

        if (committed) {
            announce('Edição cancelada. O recorte anterior continua valendo.');
        } else if (initialPreview.src !== '') {
            announce('Edição cancelada. A imagem atual continua valendo.');
        } else {
            announce('Edição cancelada. Nenhuma imagem escolhida.');
        }

        (state === 'empty' ? input : state === 'done' ? adjustButton : changeButton).focus();
    };

    const adjust = async () => {
        if (!committed || applying) {
            return;
        }

        const token = ++loadToken;

        try {
            if (sourceImage.getAttribute('src') !== committed.source.url) {
                await loadImage(sourceImage, committed.source.url);
            }
        } catch {
            setFeedback('Não foi possível reabrir a imagem. Escolha a foto de novo.');

            return;
        }

        if (token !== loadToken || !committed) {
            return;
        }

        source = committed.source;
        view = committed.view;
        openEditor();
        announce(`Ajuste o enquadramento. ${describePosition()}`);
    };

    const discard = () => {
        if (!committed || applying) {
            return;
        }

        release(committed.url);
        release(committed.source.url);
        committed = null;
        setInputFile(null);
        setFeedback(null);
        setState(initialState());
        notifyChange();
        announce(initialPreview.src !== '' ? 'Novo recorte descartado. A imagem atual continua valendo.' : 'Imagem descartada. Nenhuma imagem escolhida.');
        (state === 'empty' ? input : changeButton).focus();
    };

    const reset = () => {
        loadToken += 1;

        if (source && source !== committed?.source) {
            release(source.url);
        }

        if (committed) {
            release(committed.url);
            release(committed.source.url);
        }

        source = null;
        view = null;
        committed = null;
        setFeedback(null);
        setBusy(false);
        setState(initialState());
        announce('');
    };

    const openPicker = () => {
        try {
            if (typeof input.showPicker === 'function') {
                input.showPicker();

                return;
            }
        } catch {
            // showPicker recusado (iframe de outra origem, por exemplo): cai no click().
        }

        input.click();
    };

    input.addEventListener('change', () => {
        if (isSyncing) {
            return;
        }

        const picked = input.files?.[0];

        if (!picked) {
            // Seletor cancelado: alguns navegadores esvaziam o input. Volta o que valia.
            setInputFile(state === 'cropping' ? source?.file : settledFile());

            return;
        }

        openFile(picked);
    });

    changeButton.addEventListener('click', openPicker);
    adjustButton.addEventListener('click', adjust);
    discardButton.addEventListener('click', discard);
    applyButton.addEventListener('click', () => apply());
    cancelButton.addEventListener('click', cancel);

    zoomInput.addEventListener('input', () => {
        if (source && frame && view) {
            updateView(zoomView(view, Number(zoomInput.value) / 100, source, frame), { announcePosition: false });
        }
    });

    const stepZoom = (direction) => {
        if (source && frame && view) {
            updateView(zoomView(view, view.zoom + direction * ZOOM_STEP, source, frame));
        }
    };

    zoomInButton.addEventListener('click', () => {
        if (zoomInButton.getAttribute('aria-disabled') !== 'true') {
            stepZoom(1);
        }
    });

    zoomOutButton.addEventListener('click', () => {
        if (zoomOutButton.getAttribute('aria-disabled') !== 'true') {
            stepZoom(-1);
        }
    });

    stage.addEventListener('keydown', (event) => {
        if (state !== 'cropping' || !source || !frame || !view || event.altKey || event.ctrlKey || event.metaKey) {
            return;
        }

        const step = event.shiftKey ? KEY_STEP_LARGE : KEY_STEP;
        const moves = {
            ArrowLeft: [-step, 0],
            ArrowRight: [step, 0],
            ArrowUp: [0, -step],
            ArrowDown: [0, step],
        };

        if (moves[event.key]) {
            event.preventDefault();
            updateView(panView(view, moves[event.key][0], moves[event.key][1], source, frame));
        } else if (event.key === '+' || event.key === '=') {
            event.preventDefault();
            stepZoom(1);
        } else if (event.key === '-' || event.key === '_') {
            event.preventDefault();
            stepZoom(-1);
        } else if (event.key === '0' || event.key === 'Home') {
            event.preventDefault();
            updateView(clampView(initialView(source), source, frame));
        } else if (event.key === 'Enter') {
            event.preventDefault();
            apply();
        } else if (event.key === 'Escape') {
            // Não deixa o Esc fechar também um diálogo em volta.
            event.preventDefault();
            event.stopPropagation();
            cancel();
        }
    });

    const stagePoint = (clientX, clientY) => {
        const rect = stage.getBoundingClientRect();

        return { x: clientX - rect.left - stage.clientLeft, y: clientY - rect.top - stage.clientTop };
    };

    /** Distância e ponto médio dos dois primeiros dedos (null com menos de dois). */
    const pinchBetween = ([first, second]) => {
        if (!first || !second) {
            return null;
        }

        return {
            distance: Math.hypot(first.x - second.x, first.y - second.y),
            midpoint: stagePoint((first.x + second.x) / 2, (first.y + second.y) / 2),
        };
    };

    stage.addEventListener('pointerdown', (event) => {
        if (state !== 'cropping' || !source || (event.pointerType === 'mouse' && event.button !== 0)) {
            return;
        }

        event.preventDefault();
        stage.focus({ preventScroll: true });

        try {
            stage.setPointerCapture(event.pointerId);
        } catch {
            // Ponteiro já liberado: segue sem captura.
        }

        pointers.set(event.pointerId, { x: event.clientX, y: event.clientY });
        pinch = pinchBetween(Array.from(pointers.values()));
        stage.dataset.panning = '';
    });

    stage.addEventListener('pointermove', (event) => {
        const previous = pointers.get(event.pointerId);

        if (!previous || !source || !frame || !view) {
            return;
        }

        const current = { x: event.clientX, y: event.clientY };

        pointers.set(event.pointerId, current);

        if (pointers.size === 1) {
            updateView(panView(view, current.x - previous.x, current.y - previous.y, source, frame), { announcePosition: false });

            return;
        }

        const next = pinchBetween(Array.from(pointers.values()));

        if (pinch && next && pinch.distance > 0) {
            const zoomed = zoomView(view, view.zoom * (next.distance / pinch.distance), source, frame, next.midpoint);

            updateView(panView(zoomed, next.midpoint.x - pinch.midpoint.x, next.midpoint.y - pinch.midpoint.y, source, frame), { announcePosition: false });
        }

        pinch = next;
    });

    const endPointer = (event) => {
        if (!pointers.delete(event.pointerId)) {
            return;
        }

        pinch = null;

        if (pointers.size === 0) {
            delete stage.dataset.panning;
            scheduleAnnouncement();
        }
    };

    stage.addEventListener('pointerup', endPointer);
    stage.addEventListener('pointercancel', endPointer);
    stage.addEventListener('lostpointercapture', endPointer);

    stage.addEventListener('wheel', (event) => {
        if (state !== 'cropping' || !source || !frame || !view) {
            return;
        }

        event.preventDefault();

        const unit = event.deltaMode === 1 ? 16 : event.deltaMode === 2 ? stage.clientHeight : 1;
        // Pinça no trackpad chega como roda com ctrlKey e deltas pequenos.
        const intensity = event.ctrlKey ? 0.01 : 0.0015;

        updateView(zoomView(view, view.zoom * Math.exp(-event.deltaY * unit * intensity), source, frame, stagePoint(event.clientX, event.clientY)));
    }, { passive: false });

    if (typeof ResizeObserver === 'function') {
        new ResizeObserver(() => layout()).observe(stage);
    } else {
        window.addEventListener('resize', layout);
    }

    const carriesFiles = (event) => Array.from(event.dataTransfer?.types ?? []).includes('Files');

    container.addEventListener('dragenter', (event) => {
        if (!carriesFiles(event) || input.disabled) {
            return;
        }

        event.preventDefault();
        dragDepth += 1;
        container.dataset.dragging = '';
    });

    container.addEventListener('dragover', (event) => {
        if (!carriesFiles(event)) {
            return;
        }

        event.preventDefault();

        if (event.dataTransfer) {
            event.dataTransfer.dropEffect = input.disabled || applying ? 'none' : 'copy';
        }
    });

    container.addEventListener('dragleave', () => {
        dragDepth = Math.max(0, dragDepth - 1);

        if (dragDepth === 0) {
            delete container.dataset.dragging;
        }
    });

    container.addEventListener('drop', (event) => {
        if (!carriesFiles(event)) {
            return;
        }

        event.preventDefault();
        dragDepth = 0;
        delete container.dataset.dragging;

        const files = Array.from(event.dataTransfer?.files ?? []);

        if (input.disabled || applying || files.length === 0) {
            return;
        }

        openFile(files[0], { fromDrop: true, notice: files.length > 1 ? 'Só a primeira imagem foi aberta. ' : '' });
    });

    if (input.form) {
        registerWithForm(input.form, {
            needsFlush: () => state === 'cropping' || applying !== null,
            flush: () => apply({ moveFocus: false }),
            reset,
        });
    }

    setState(state);
}

/**
 * Um ouvinte de submit por formulário: se algum recortador está com o palco aberto, o envio espera
 * todos os recortes e depois é refeito com o mesmo botão (o submit-busy.js marca o botão nesse
 * segundo envio). Recorte que falha segura o envio e mostra o erro no campo.
 *
 * @param {HTMLFormElement} form
 * @param {{ needsFlush: () => boolean, flush: () => Promise<boolean>, reset: () => void }} controller
 */
function registerWithForm(form, controller) {
    let controllers = formControllers.get(form);

    if (!controllers) {
        controllers = new Set();
        formControllers.set(form, controllers);

        const registered = controllers;

        form.addEventListener('submit', (event) => {
            const pending = Array.from(registered).filter((item) => item.needsFlush());

            if (pending.length === 0) {
                return;
            }

            event.preventDefault();

            if (form.dataset.imageCropperFlushing === 'true') {
                return;
            }

            form.dataset.imageCropperFlushing = 'true';

            const submitter = event.submitter && 'form' in event.submitter && event.submitter.form === form ? event.submitter : undefined;

            Promise.all(pending.map((item) => item.flush())).then((results) => {
                delete form.dataset.imageCropperFlushing;

                if (!results.every(Boolean)) {
                    return;
                }

                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit(submitter);
                } else {
                    form.submit();
                }
            });
        });

        form.addEventListener('reset', () => {
            registered.forEach((item) => item.reset());
        });
    }

    controllers.add(controller);
}

/**
 * Carrega a URL no <img> e devolve o tamanho natural (já girado pelo EXIF). Vale o que vier
 * primeiro entre decode() e o evento load: com a aba em segundo plano o Chrome pode segurar o
 * decode() sem resolver, e o drawImage decodifica sozinho se precisar.
 *
 * @param {HTMLImageElement} image
 * @param {string} url
 * @returns {Promise<{ width: number, height: number }>}
 */
function loadImage(image, url) {
    const sizeOf = () => {
        if (!image.naturalWidth || !image.naturalHeight) {
            throw new Error('A imagem não tem tamanho.');
        }

        return { width: image.naturalWidth, height: image.naturalHeight };
    };

    if (image.getAttribute('src') === url && image.complete && image.naturalWidth > 0) {
        return Promise.resolve(sizeOf());
    }

    // Ouvintes próprios de cada carga: se outra imagem entrar antes, esta carga também termina (e
    // quem chamou descarta o resultado pelo token), em vez de ficar pendente para sempre.
    const listeners = new AbortController();
    const loaded = new Promise((resolve, reject) => {
        image.addEventListener('load', () => resolve(), { signal: listeners.signal });
        image.addEventListener('error', () => reject(new Error('A imagem não abriu.')), { signal: listeners.signal });
    });

    // Quem trata a falha é a cadeia abaixo; isto só evita o aviso de rejeição sem tratamento.
    loaded.catch(() => {});

    image.src = url;

    const decoded = typeof image.decode === 'function' ? image.decode().catch(() => loaded) : loaded;

    return Promise.race([decoded, loaded])
        .then(sizeOf)
        .finally(() => listeners.abort());
}

/**
 * Desenha o recorte num canvas do tamanho final e gera o JPEG. Fundo branco para PNG com
 * transparência. Se passar do limite, tenta qualidades menores.
 *
 * @param {HTMLImageElement} image
 * @param {{ x: number, y: number, width: number, height: number }} crop
 * @param {{ width: number, height: number }} size
 * @param {{ quality: number, maxBytes: number|null, maxLabel: string }} settings
 * @returns {Promise<Blob>}
 */
async function renderJpeg(image, crop, size, settings) {
    const canvas = document.createElement('canvas');

    canvas.width = size.width;
    canvas.height = size.height;

    const context = canvas.getContext('2d');

    if (!context) {
        throw new Error('Canvas indisponível.');
    }

    context.fillStyle = '#ffffff';
    context.fillRect(0, 0, size.width, size.height);
    context.imageSmoothingEnabled = true;
    context.imageSmoothingQuality = 'high';
    context.drawImage(image, crop.x, crop.y, crop.width, crop.height, 0, 0, size.width, size.height);

    const qualities = [settings.quality, ...FALLBACK_QUALITIES.filter((quality) => quality < settings.quality)];

    for (const quality of qualities) {
        const blob = await canvasToJpeg(canvas, quality);

        if (!settings.maxBytes || blob.size <= settings.maxBytes) {
            return blob;
        }
    }

    const error = new Error('Recorte acima do limite.');

    error.userMessage = `Mesmo recortada, a imagem passou de ${settings.maxLabel || formatFileSize(settings.maxBytes)}. Escolha outra foto.`;

    throw error;
}

/**
 * @param {HTMLCanvasElement} canvas
 * @param {number} quality
 * @returns {Promise<Blob>}
 */
function canvasToJpeg(canvas, quality) {
    return new Promise((resolve, reject) => {
        canvas.toBlob((blob) => (blob ? resolve(blob) : reject(new Error('toBlob falhou.'))), 'image/jpeg', quality);
    });
}

/**
 * "IMG_2024.HEIC.png" vira "IMG_2024.HEIC-recorte.jpg".
 *
 * @param {string} name
 * @returns {string}
 */
function croppedFileName(name) {
    const base = String(name || 'imagem').replace(/\.[^./\\]+$/, '').trim() || 'imagem';

    return `${base}-recorte.jpg`;
}

/**
 * Botão − ou + no limite do zoom: aria-disabled (não disabled), para o foco não se perder.
 *
 * @param {HTMLElement} button
 * @param {boolean} isDisabled
 */
function setDisabledLook(button, isDisabled) {
    if (isDisabled) {
        button.setAttribute('aria-disabled', 'true');
    } else {
        button.removeAttribute('aria-disabled');
    }
}
