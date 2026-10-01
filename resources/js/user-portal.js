import apiClient from './api/client';
import { initOwnerMaintenanceForms } from './owner-maintenance-form';
import { icon } from './ui/icons';
import { toast } from './ui/toast';
import { portalPdfUrl } from './utils/vehicle-pdf-export';

/**
 * Portal do proprietário (/usuario). As telas são renderizadas no servidor (Blade); este módulo
 * só cuida da interatividade:
 *
 * - Exportar PDF na ficha do veículo: gera o PDF pela API, mostra o progresso (spinner no botão,
 *   aria-busy e um role="status") e troca o botão pelo link "Baixar PDF", com um toast. O link
 *   segue .ai/rules/js.md: href relativo terminado em .pdf, sem o atributo download e sem navegar
 *   sozinho (nada de location.assign, window.open, iframe, fetch do arquivo ou blob:). No erro, o
 *   botão vira "Tentar novamente" e um toast explica. Sem JS, o formulário pede o PDF por e-mail.
 * - Formulário de manutenção: resources/js/owner-maintenance-form.js.
 *
 * A ficha (x-vehicle.detail) é a mesma em todos os portais: o filtro de procedência, a linha do
 * tempo e as abas pelo #fragmento são ligados pelo app.js (initProvenanceFilters,
 * initVehicleTimelines e initVehicleDetails), não aqui.
 */

const POLL_INTERVAL_MS = 2000;
const MAX_POLL_ATTEMPTS = 60;
const SPINNER_SVG = '<svg class="size-5 motion-safe:animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4Z"></path></svg>';

class PdfExportError extends Error {
    /**
     * @param {string} description texto do toast
     */
    constructor(description) {
        super(description);
        this.description = description;
    }
}

/**
 * @param {number} milliseconds
 * @returns {Promise<void>}
 */
function wait(milliseconds) {
    return new Promise((resolve) => {
        window.setTimeout(resolve, milliseconds);
    });
}

/**
 * Mensagem para a pessoa a partir do erro da API (sessão expirada, sem permissão ou falha).
 *
 * @param {unknown} error
 * @returns {string}
 */
function exportErrorDescription(error) {
    if (error instanceof PdfExportError) {
        return error.description;
    }

    const status = /** @type {{ response?: { status?: number } }} */ (error)?.response?.status;

    if (status === 401 || status === 419) {
        return 'Sua sessão expirou. Entre de novo e tente outra vez.';
    }

    if (status === 403) {
        return 'Sua conta não pode exportar o histórico deste veículo.';
    }

    return 'Tente novamente em alguns instantes. Se continuar, recarregue a página.';
}

/**
 * Consulta GET /api/v1/vehicle-pdf-exports/{id} até o PDF ficar pronto.
 *
 * @param {string|number} exportId
 * @returns {Promise<Record<string, any>>}
 */
async function waitForPdfExport(exportId) {
    for (let attempt = 1; attempt <= MAX_POLL_ATTEMPTS; attempt += 1) {
        const response = await apiClient.getVehiclePdfExportStatus(exportId);
        const data = response.data?.data ?? {};

        if (data.status === 'completed') {
            return data;
        }

        if (data.status === 'failed') {
            throw new PdfExportError('A geração do PDF falhou. Tente novamente em alguns instantes.');
        }

        await wait(POLL_INTERVAL_MS);
    }

    throw new PdfExportError('O PDF está demorando mais que o normal. Tente novamente em alguns minutos.');
}

/**
 * @param {HTMLButtonElement} button
 * @param {string} labelText
 */
function setButtonLabel(button, labelText) {
    const label = button.querySelector('[data-slot="label"]');

    if (label) {
        label.textContent = labelText;
    } else {
        button.textContent = labelText;
    }
}

/**
 * Ocupado sem disabled: o botão continua focável (o foco não cai no body) e aria-disabled avisa
 * que não dá para clicar de novo.
 *
 * @param {HTMLButtonElement} button
 * @param {boolean} busy
 */
function setButtonBusy(button, busy) {
    const buttonIcon = button.querySelector(':scope > [data-slot="icon"]');
    let spinner = button.querySelector(':scope > [data-pdf-export-spinner]');

    if (busy) {
        button.setAttribute('aria-busy', 'true');
        button.setAttribute('aria-disabled', 'true');

        if (!spinner) {
            spinner = document.createElement('span');
            spinner.className = 'inline-flex shrink-0 items-center justify-center';
            spinner.dataset.pdfExportSpinner = '';
            spinner.setAttribute('aria-hidden', 'true');
            spinner.innerHTML = SPINNER_SVG;
            button.prepend(spinner);
        }

        // O ícone é um <svg>: o atributo hidden (e não a propriedade) é que esconde.
        buttonIcon?.setAttribute('hidden', '');

        return;
    }

    button.removeAttribute('aria-busy');
    button.removeAttribute('aria-disabled');
    spinner?.remove();
    buttonIcon?.removeAttribute('hidden');
}

/**
 * Link final: mesma aparência do botão, href relativo terminado em .pdf e sem download.
 *
 * @param {HTMLButtonElement} button
 * @param {string} href
 * @returns {HTMLAnchorElement}
 */
function downloadLink(button, href) {
    const link = document.createElement('a');
    // O último segmento PRECISA ser o .pdf. Sem o atributo download: o Chromium baixaria como
    // blob: e a barra de downloads mostraria um UUID (.ai/rules/js.md).
    link.href = href;
    link.className = button.className;
    link.dataset.slot = 'button';
    link.dataset.pdfDownload = '';
    link.innerHTML = `${icon('arrow-down-tray', { className: 'size-5' })}<span data-slot="label">Baixar PDF</span>`;

    return link;
}

/**
 * @param {HTMLFormElement} form [data-vehicle-pdf-export]
 */
async function exportVehiclePdf(form) {
    const button = form.querySelector('[data-pdf-export-button]');
    const status = form.querySelector('[data-pdf-export-status]');

    if (!(button instanceof HTMLButtonElement) || form.dataset.pdfExportState === 'busy') {
        return;
    }

    form.dataset.pdfExportState = 'busy';
    setButtonBusy(button, true);
    setButtonLabel(button, 'Gerando PDF…');

    if (status) {
        status.textContent = 'Preparando o histórico em PDF. Isso pode levar até 1 minuto.';
    }

    try {
        const response = await apiClient.requestVehiclePdfExport(form.dataset.vehicleId);
        const exportId = response.data?.data?.export_id;

        if (!exportId) {
            throw new PdfExportError('A geração do PDF não começou. Tente novamente.');
        }

        const result = await waitForPdfExport(exportId);
        const link = downloadLink(button, portalPdfUrl(exportId, result));
        const hadFocus = form.contains(document.activeElement);

        form.replaceWith(link);

        if (hadFocus) {
            link.focus();
        }

        toast({
            variant: 'success',
            title: 'PDF pronto',
            description: 'Use o botão "Baixar PDF" no topo da ficha para salvar o arquivo.',
        });
    } catch (error) {
        console.error(error);
        form.dataset.pdfExportState = 'error';
        setButtonBusy(button, false);
        setButtonLabel(button, 'Tentar novamente');

        const description = exportErrorDescription(error);

        if (status) {
            status.textContent = `Não foi possível gerar o PDF. ${description}`;
        }

        toast({ variant: 'error', title: 'Não foi possível gerar o PDF.', description });
    }
}

/**
 * @param {ParentNode} root
 */
function initVehiclePdfExports(root) {
    root.querySelectorAll('[data-vehicle-pdf-export]').forEach((form) => {
        if (!(form instanceof HTMLFormElement) || form.dataset.pdfExportReady === 'true') {
            return;
        }

        form.dataset.pdfExportReady = 'true';
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            exportVehiclePdf(form);
        });
    });
}

/**
 * Liga a interatividade das telas do proprietário. Idempotente.
 *
 * @param {ParentNode} [root]
 */
export function initUserPortal(root = document) {
    initVehiclePdfExports(root);
    initOwnerMaintenanceForms(root);
}
