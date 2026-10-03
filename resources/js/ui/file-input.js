import { elementsWithin } from './dom';
import { icon } from './icons';
import { escapeHtml } from '../utils/html';

/**
 * <x-ui.file-input>: transforma o <input type="file"> nativo numa área de soltar. Sem JS (ou sem
 * DataTransfer para montar a lista de arquivos), o input nativo estilizado continua valendo.
 *
 * Com JS:
 * - o <label> tracejado do <template> entra logo depois do input, que fica visualmente oculto mas
 *   focável (o foco aparece na área, por peer-focus-visible);
 * - arrastar e soltar sobre a área escolhe os arquivos; em multiple, o que se escolhe depois soma
 *   aos anteriores (sem repetir o mesmo arquivo);
 * - tipo (accept), tamanho (data-max-bytes) e quantidade (data-max-files) são conferidos em pt-BR,
 *   numa mensagem role="alert"; o que não passa fica de fora do envio;
 * - a lista mostra nome, tamanho e o botão "Remover" de cada arquivo, e uma região aria-live conta
 *   o que foi escolhido.
 *
 * O servidor continua validando: esta conferência só evita um envio que voltaria com erro.
 */

const CONTAINER_SELECTOR = '[data-file-input]';

const numberFormat = new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 1 });

/**
 * Liga as áreas de envio de root (e o próprio root). Idempotente.
 *
 * @param {ParentNode} [root]
 */
export function initFileInputs(root = document) {
    if (!supportsFileAssignment()) {
        return;
    }

    elementsWithin(root, CONTAINER_SELECTOR).forEach(enhanceFileInput);
}

/**
 * Tamanho em pt-BR: "512 bytes", "12,5 KB", "1,2 MB".
 *
 * @param {number} bytes
 * @returns {string}
 */
export function formatFileSize(bytes) {
    if (bytes < 1024) {
        return `${bytes} ${bytes === 1 ? 'byte' : 'bytes'}`;
    }

    if (bytes < 1024 * 1024) {
        return `${numberFormat.format(bytes / 1024)} KB`;
    }

    return `${numberFormat.format(bytes / (1024 * 1024))} MB`;
}

/**
 * O arquivo é de um dos tipos do accept (".pdf", "image/*" ou "application/pdf"). Arquivo sem tipo
 * informado pelo sistema passa: quem decide é o servidor.
 *
 * @param {{ name: string, type: string }} file
 * @param {string} accept
 * @returns {boolean}
 */
export function fileMatchesAccept(file, accept) {
    const acceptedTypes = String(accept ?? '')
        .split(',')
        .map((type) => type.trim().toLowerCase())
        .filter(Boolean);

    if (acceptedTypes.length === 0) {
        return true;
    }

    const fileType = String(file.type ?? '').toLowerCase();
    const dotIndex = file.name.lastIndexOf('.');
    const extension = dotIndex >= 0 ? file.name.slice(dotIndex).toLowerCase() : '';

    return acceptedTypes.some((acceptedType) => {
        if (acceptedType.startsWith('.')) {
            return acceptedType === extension;
        }

        if (fileType === '') {
            return true;
        }

        if (acceptedType.endsWith('/*')) {
            return fileType.startsWith(acceptedType.slice(0, -1));
        }

        return acceptedType === fileType;
    });
}

function supportsFileAssignment() {
    try {
        return typeof new DataTransfer().items?.add === 'function';
    } catch {
        return false;
    }
}

/**
 * @param {File} file
 * @returns {string}
 */
function fileKey(file) {
    return `${file.name}|${file.size}|${file.lastModified}`;
}

/**
 * @param {Element} container
 */
function enhanceFileInput(container) {
    if (!(container instanceof HTMLElement) || container.dataset.fileInputReady === 'true') {
        return;
    }

    const input = container.querySelector('input[type="file"][data-file-input-control]');
    const dropzoneTemplate = container.querySelector('template[data-file-input-dropzone-template]');
    const itemTemplate = container.querySelector('template[data-file-input-item-template]');
    const list = container.querySelector('[data-file-input-list]');
    const feedback = container.querySelector('[data-file-input-feedback]');
    const status = container.querySelector('[data-file-input-status]');
    const dropzone = dropzoneTemplate?.content.firstElementChild?.cloneNode(true);

    if (!(input instanceof HTMLInputElement) || !(dropzone instanceof HTMLElement) || !itemTemplate || !list || !feedback || !status) {
        return;
    }

    container.dataset.fileInputReady = 'true';
    input.after(dropzone);
    container.dataset.enhanced = '';

    const settings = {
        multiple: input.multiple,
        maxBytes: Number(container.dataset.maxBytes) || null,
        maxLabel: container.dataset.maxLabel || '',
        maxFiles: Number(container.dataset.maxFiles) || null,
        typesLabel: container.dataset.typesLabel || '',
    };
    const serverInvalid = input.getAttribute('aria-invalid') === 'true';
    const previewUrls = [];
    let files = Array.from(input.files ?? []);
    let isSyncing = false;
    let dragDepth = 0;

    const syncInput = () => {
        const transfer = new DataTransfer();

        files.forEach((file) => transfer.items.add(file));
        input.files = transfer.files;
    };

    const notifyChange = () => {
        isSyncing = true;
        input.dispatchEvent(new Event('change', { bubbles: true }));
        isSyncing = false;
    };

    const announce = (message) => {
        status.textContent = message;
    };

    const selectionSummary = () => {
        if (files.length === 0) {
            return 'Nenhum arquivo escolhido.';
        }

        if (files.length === 1) {
            return `Arquivo escolhido: ${files[0].name}, ${formatFileSize(files[0].size)}.`;
        }

        return `${files.length} arquivos escolhidos.`;
    };

    const setFeedback = (messages) => {
        const describedBy = (input.getAttribute('aria-describedby') ?? '').split(/\s+/).filter((id) => id && id !== feedback.id);

        if (messages.length === 0) {
            feedback.replaceChildren();

            if (serverInvalid) {
                input.setAttribute('aria-invalid', 'true');
            } else {
                input.removeAttribute('aria-invalid');
            }
        } else {
            feedback.innerHTML = `${icon('exclamation-circle', { variant: 'solid', className: 'mt-0.5 size-4' })}<span><span class="sr-only">Erro: </span>${escapeHtml(messages.join(' '))}</span>`;
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

    const releasePreviews = () => {
        previewUrls.splice(0).forEach((url) => URL.revokeObjectURL(url));
    };

    const buildItem = (file, index) => {
        const item = itemTemplate.content.firstElementChild.cloneNode(true);
        const name = item.querySelector('[data-file-input-item-name]');
        const size = item.querySelector('[data-file-input-item-size]');
        const image = item.querySelector('[data-file-input-item-image]');
        const fileIcon = item.querySelector('[data-file-input-item-icon]');
        const removeButton = item.querySelector('[data-file-input-remove]');

        if (name) {
            name.textContent = file.name;
            name.setAttribute('title', file.name);
        }

        if (size) {
            size.textContent = formatFileSize(file.size);
        }

        if (image instanceof HTMLImageElement && String(file.type).startsWith('image/') && typeof URL.createObjectURL === 'function') {
            const url = URL.createObjectURL(file);

            previewUrls.push(url);
            image.src = url;
            image.hidden = false;
            fileIcon?.setAttribute('hidden', '');
        }

        if (removeButton) {
            removeButton.setAttribute('aria-label', `Remover ${file.name}`);
            removeButton.addEventListener('click', () => removeFile(index));
        }

        return item;
    };

    const render = () => {
        releasePreviews();
        list.replaceChildren(...files.map(buildItem));
        list.hidden = files.length === 0;
    };

    const removeFile = (index) => {
        const [removed] = files.splice(index, 1);

        syncInput();
        render();
        setFeedback([]);
        announce(removed ? `${removed.name} removido. ${selectionSummary()}` : selectionSummary());
        notifyChange();

        const removeButtons = list.querySelectorAll('[data-file-input-remove]');
        const nextFocus = removeButtons[Math.min(index, removeButtons.length - 1)] ?? input;

        nextFocus.focus();
    };

    const addFiles = (incomingList) => {
        const messages = [];
        let incoming = Array.from(incomingList ?? []);

        if (!settings.multiple && incoming.length > 1) {
            messages.push('Escolha só um arquivo.');
            incoming = incoming.slice(0, 1);
        }

        const accepted = incoming.filter((file) => {
            if (!fileMatchesAccept(file, input.accept)) {
                messages.push(`"${file.name}" não é de um tipo aceito.${settings.typesLabel ? ` Envie ${settings.typesLabel}.` : ''}`);

                return false;
            }

            if (settings.maxBytes && file.size > settings.maxBytes) {
                messages.push(`"${file.name}" tem ${formatFileSize(file.size)}. O limite é ${settings.maxLabel || formatFileSize(settings.maxBytes)} por arquivo.`);

                return false;
            }

            return true;
        });

        let next;

        if (settings.multiple) {
            const known = new Set(files.map(fileKey));

            next = files.concat(accepted.filter((file) => !known.has(fileKey(file))));
        } else {
            next = accepted.length > 0 ? accepted.slice(0, 1) : files;
        }

        if (settings.multiple && settings.maxFiles && next.length > settings.maxFiles) {
            const leftOut = next.length - settings.maxFiles;

            next = next.slice(0, settings.maxFiles);
            messages.push(`Você pode enviar até ${settings.maxFiles} arquivos. ${leftOut === 1 ? '1 arquivo ficou' : `${leftOut} arquivos ficaram`} de fora.`);
        }

        files = next;
        syncInput();
        render();
        setFeedback(messages);
        announce(selectionSummary());
    };

    input.addEventListener('change', () => {
        if (isSyncing) {
            return;
        }

        const picked = Array.from(input.files ?? []);

        if (picked.length === 0) {
            // Seletor cancelado: alguns navegadores esvaziam o input. Volta o que estava escolhido.
            syncInput();

            return;
        }

        addFiles(picked);
    });

    const carriesFiles = (event) => Array.from(event.dataTransfer?.types ?? []).includes('Files');

    dropzone.addEventListener('dragenter', (event) => {
        if (!carriesFiles(event)) {
            return;
        }

        event.preventDefault();
        dragDepth += 1;
        dropzone.dataset.dragging = '';
    });

    dropzone.addEventListener('dragover', (event) => {
        if (!carriesFiles(event)) {
            return;
        }

        event.preventDefault();

        if (event.dataTransfer) {
            event.dataTransfer.dropEffect = input.disabled ? 'none' : 'copy';
        }
    });

    dropzone.addEventListener('dragleave', () => {
        dragDepth = Math.max(0, dragDepth - 1);

        if (dragDepth === 0) {
            delete dropzone.dataset.dragging;
        }
    });

    dropzone.addEventListener('drop', (event) => {
        if (!carriesFiles(event)) {
            return;
        }

        event.preventDefault();
        dragDepth = 0;
        delete dropzone.dataset.dragging;

        if (input.disabled) {
            return;
        }

        addFiles(event.dataTransfer?.files);
        notifyChange();
    });

    input.form?.addEventListener('reset', () => {
        // O reset esvazia o input depois do evento.
        window.setTimeout(() => {
            files = [];
            render();
            setFeedback([]);
            announce('');
        });
    });

    render();
}
