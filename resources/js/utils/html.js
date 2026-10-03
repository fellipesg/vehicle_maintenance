const HTML_ESCAPES = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;',
};

/**
 * Escapa um valor para interpolar em HTML (conteúdo ou atributo entre aspas).
 * Todo dado vindo da API ou digitado por usuário/oficina precisa passar por aqui
 * antes de entrar numa template string que vai para innerHTML.
 *
 * @param {unknown} value
 * @returns {string}
 */
export function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (character) => HTML_ESCAPES[character]);
}
