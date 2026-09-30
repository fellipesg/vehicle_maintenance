/**
 * Link do PDF do histórico no portal do proprietário (.ai/rules/js.md): caminho relativo, do mesmo
 * site, cujo último segmento é o nome ASCII do arquivo terminado em .pdf
 * (/usuario/exportacoes-pdf/{id}/historico_manutencoes_ABC1D23_....pdf). Nunca download_url,
 * URL absoluta, /api/v1/.../download nem blob:.
 */

export const PORTAL_PDF_PREFIX = '/usuario/exportacoes-pdf/';

const FALLBACK_FILENAME = 'historico_manutencoes.pdf';

/**
 * Nome do arquivo com a extensão .pdf e só os caracteres que a rota aceita ([A-Za-z0-9._-]).
 *
 * @param {unknown} name
 * @returns {string}
 */
export function pdfDownloadFilename(name) {
    const raw = String(name ?? '').trim();
    const ascii = raw
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .replace(/[^A-Za-z0-9._-]+/g, '_')
        .replace(/_+/g, '_')
        .replace(/^[._]+/, '');
    const base = ascii === '' || ascii.toLowerCase() === '.pdf' ? FALLBACK_FILENAME : ascii;

    return base.toLowerCase().endsWith('.pdf') ? base : `${base}.pdf`;
}

/**
 * @param {string|number} exportId
 * @param {{ download_portal_url?: string|null, filename?: string|null }} [status] resposta de
 *     GET /api/v1/vehicle-pdf-exports/{id} com status completed
 * @returns {string}
 */
export function portalPdfUrl(exportId, status = {}) {
    const portalUrl = typeof status.download_portal_url === 'string' ? status.download_portal_url : '';
    const expectedPrefix = `${PORTAL_PDF_PREFIX}${exportId}/`;

    if (
        portalUrl.startsWith(expectedPrefix)
        && /^[A-Za-z0-9._-]+\.pdf$/i.test(portalUrl.slice(expectedPrefix.length))
    ) {
        return portalUrl;
    }

    return `${expectedPrefix}${pdfDownloadFilename(status.filename)}`;
}
