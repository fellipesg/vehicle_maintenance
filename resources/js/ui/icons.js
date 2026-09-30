import iconSet from './icons.json';
import { escapeHtml } from '../utils/html';

/**
 * Ícones Heroicons v2 (MIT, Tailwind Labs) para templates JS, da mesma fonte do componente
 * <x-ui.icon> (resources/js/ui/icons.json). Mesmas regras do componente: sem title o ícone é
 * decorativo (aria-hidden); sem size-*, w-* ou h-* em className entra size-5; cor currentColor.
 */

export const ICON_VARIANTS = ['outline', 'solid'];

const SIZE_CLASS_PATTERN = /(?:^|\s)!?(?:size|w|h)-/;

/**
 * @param {string} name
 * @param {'outline'|'solid'} [variant]
 * @returns {boolean}
 */
export function hasIcon(name, variant = 'outline') {
    return ICON_VARIANTS.includes(variant) && Object.hasOwn(iconSet[variant], name);
}

/**
 * Markup do <svg> para interpolar numa template string que vai para innerHTML.
 * Nome ou variante inexistente: erro no Vite em desenvolvimento; em produção, aviso no console e
 * string vazia, para um ícone não derrubar a tela.
 *
 * @param {string} name Nome do Heroicons, como x-mark ou check-circle.
 * @param {{ variant?: 'outline'|'solid', className?: string, title?: string|null }} [options]
 * @returns {string}
 */
export function icon(name, { variant = 'outline', className = '', title = null } = {}) {
    if (!hasIcon(name, variant)) {
        const message = `Ícone "${name}" não existe na variante ${variant} de resources/js/ui/icons.json.`;

        if (import.meta.env?.DEV) {
            throw new Error(message);
        }

        console.warn(message);

        return '';
    }

    const classes = [SIZE_CLASS_PATTERN.test(className) ? '' : 'size-5', 'shrink-0', className]
        .filter(Boolean)
        .join(' ');
    const svgAttributes = Object.entries(iconSet._svg[variant])
        .map(([attribute, value]) => `${attribute}="${escapeHtml(value)}"`)
        .join(' ');
    const hasTitle = title !== null && String(title).trim() !== '';
    const accessibility = hasTitle ? `role="img" aria-label="${escapeHtml(title)}"` : 'aria-hidden="true"';
    const titleElement = hasTitle ? `<title>${escapeHtml(title)}</title>` : '';

    return `<svg class="${escapeHtml(classes)}" xmlns="http://www.w3.org/2000/svg" ${svgAttributes} data-slot="icon" focusable="false" ${accessibility}>${titleElement}${iconSet[variant][name]}</svg>`;
}
