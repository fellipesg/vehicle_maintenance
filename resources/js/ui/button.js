/**
 * Classes do <x-ui.button> para os poucos botões que o JavaScript cria ou troca de variante: o
 * diálogo de confirmação montado quando o layout não trouxe <x-ui.confirm-dialog> (confirm.js),
 * o "Confirmar" que vira destrutivo (data-confirm-variant="danger") e o "Carregar mais"
 * (utils/load-more.js). Espelho de resources/views/components/ui/button.blade.php: mudou lá, muda
 * aqui (tests/Feature/Ui/ButtonScriptTest.php compara os dois). O app.css não tem mais classes
 * de botão.
 */

const BASE_CLASSES = [
    'inline-flex items-center justify-center rounded-control text-center font-semibold select-none',
    'transition-[color,background-color,border-color,box-shadow,scale] duration-fast ease-smooth-out motion-reduce:transition-none',
    'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
    'motion-safe:active:scale-[.98]',
    'disabled:pointer-events-none disabled:opacity-60 aria-disabled:pointer-events-none aria-disabled:opacity-60',
].join(' ');

/** @type {Record<'primary'|'secondary'|'ghost'|'danger'|'link', string>} */
export const BUTTON_VARIANTS = {
    primary: 'bg-primary text-primary-foreground shadow-xs hover:bg-primary-hover',
    secondary: 'border border-border-strong bg-surface text-foreground shadow-xs hover:bg-surface-muted',
    ghost: 'text-foreground hover:bg-surface-muted',
    danger: 'bg-danger text-danger-foreground shadow-xs hover:bg-danger-hover',
    link: 'text-link underline-offset-4 hover:text-link-hover hover:underline',
};

/** @type {Record<'sm'|'md'|'lg', string>} */
export const BUTTON_SIZES = {
    sm: 'min-h-8 gap-1.5 py-1.5 text-xs max-sm:min-h-10',
    md: 'min-h-10 gap-2 py-2 text-sm',
    lg: 'min-h-11 gap-2 py-2.5 text-base',
};

const BUTTON_PADDING = { sm: 'px-3', md: 'px-4', lg: 'px-5' };

/**
 * @param {string} variant
 * @returns {keyof typeof BUTTON_VARIANTS}
 */
function knownVariant(variant) {
    return Object.hasOwn(BUTTON_VARIANTS, variant) ? variant : 'primary';
}

/**
 * Classes completas de um botão, como o <x-ui.button variant size> desenha.
 *
 * @param {string} [variant] primary | secondary | ghost | danger | link
 * @param {string} [size] sm | md | lg
 * @returns {string}
 */
export function buttonClass(variant = 'primary', size = 'md') {
    const buttonVariant = knownVariant(variant);
    const buttonSize = Object.hasOwn(BUTTON_SIZES, size) ? size : 'md';
    const padding = buttonVariant === 'link' ? 'px-0' : BUTTON_PADDING[buttonSize];

    return `${BASE_CLASSES} ${BUTTON_SIZES[buttonSize]} ${padding} ${BUTTON_VARIANTS[buttonVariant]}`;
}

/**
 * Troca a variante de um botão já desenhado (as classes de cor e o data-variant), sem mexer no
 * tamanho nem nas classes extras.
 *
 * @param {HTMLElement} button
 * @param {string} variant
 */
export function setButtonVariant(button, variant) {
    const buttonVariant = knownVariant(variant);
    const variantClasses = new Set(Object.values(BUTTON_VARIANTS).flatMap((classes) => classes.split(' ')));

    button.classList.remove(...variantClasses);
    button.classList.add(...BUTTON_VARIANTS[buttonVariant].split(' '));
    button.dataset.variant = buttonVariant;
}
