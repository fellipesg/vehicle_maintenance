/**
 * Elementos que casam com o seletor dentro de root, incluindo o próprio root. Os init*() dos
 * componentes de resources/js/ui recebem root = document na carga e um trecho novo de HTML
 * (conteúdo carregado depois, linha adicionada) quando precisam ligar só aquele pedaço.
 *
 * @param {ParentNode} root
 * @param {string} selector
 * @returns {Element[]}
 */
export function elementsWithin(root, selector) {
    const found = typeof root?.querySelectorAll === 'function' ? Array.from(root.querySelectorAll(selector)) : [];

    if (root instanceof Element && root.matches(selector)) {
        found.unshift(root);
    }

    return found;
}

/**
 * O usuário pediu menos movimento no sistema.
 *
 * @returns {boolean}
 */
export function prefersReducedMotion() {
    return typeof window.matchMedia === 'function' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}
