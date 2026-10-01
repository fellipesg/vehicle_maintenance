/**
 * Plugins do Preline que o app usa, e só eles: HSOverlay (menu mobile, gaveta do admin e
 * <x-ui.sheet>), HSDropdown (<x-ui.dropdown>) e HSRemoveElement (botão "Fechar aviso" de
 * <x-ui.alert dismissible>, com a classe hs-removing durante o fade). Abas, dicas e confirmação têm
 * JS próprio em resources/js/ui/. O pacote inteiro ('preline', ~400 KB) saiu do bundle.
 *
 * Importa as entradas "non-auto" porque são as únicas com export: o package.json do Preline marca
 * dist/overlay.mjs e dist/dropdown.mjs (as entradas automáticas, só com efeito colateral) como sem
 * side effects, e o build do Vite descartaria um `import 'preline/plugins/overlay'`. A inicialização
 * fica em initPreline(), e o fechamento por largura do sheet (data-close-from) usa matchMedia.
 */
import HSOverlay from 'preline/plugins/overlay-non-auto';
import HSDropdown from 'preline/plugins/dropdown-non-auto';
import HSRemoveElement from 'preline/plugins/remove-element-non-auto';
import { elementsWithin } from './dom';

/** Larguras mínimas dos breakpoints do Tailwind 4, as mesmas do shell-script dos layouts. */
const BREAKPOINTS = {
    sm: '(min-width: 40rem)',
    md: '(min-width: 48rem)',
    lg: '(min-width: 64rem)',
    xl: '(min-width: 80rem)',
};

const keyboardFixed = new WeakSet();
const closeFromBound = new WeakSet();

/**
 * O HSAccessibilityObserver do Preline 4.2 escuta o teclado no document e chama preventDefault no
 * Enter de qualquer link ou botão dentro de um .hs-overlay aberto ou de um .hs-dropdown-menu (e,
 * no menu, também no Espaço e em item com ícone). Parar a propagação no próprio painel devolve a
 * ativação nativa: o link navega, o botão dispara click e o dropdown fecha pelo próprio clique.
 *
 * O overlay também só prende o Tab para a frente: Shift+Tab no primeiro item, ou no próprio painel
 * (que recebe o foco ao abrir), levaria o foco para trás do fundo escuro. Aqui ele volta ao último.
 *
 * @param {HTMLElement} container
 */
function keepNativeActivation(container) {
    if (keyboardFixed.has(container)) {
        return;
    }

    keyboardFixed.add(container);

    container.addEventListener('keydown', (event) => {
        const control = event.target instanceof Element ? event.target.closest('a[href], button, [role="menuitem"]') : null;
        const isActivationKey = event.key === 'Enter' || (event.key === ' ' && control?.tagName === 'BUTTON');

        if (isActivationKey && control && container.contains(control) && !control.matches('[data-hs-overlay]')) {
            event.stopPropagation();

            return;
        }

        if (event.key === 'Tab' && event.shiftKey && container.classList.contains('hs-overlay') && container.classList.contains('open')) {
            const focusables = Array.from(container.querySelectorAll(
                'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
            )).filter((element) => !element.hidden && element.getClientRects().length > 0);

            if (focusables.length > 0 && (document.activeElement === container || document.activeElement === focusables[0])) {
                event.preventDefault();
                focusables[focusables.length - 1].focus();
            }
        }
    });
}

/**
 * Sheet com data-close-from: a partir dessa largura o conteúdo vira barra ou sidebar fixa, então
 * um painel aberto fecha (senão o fundo escuro e o bloqueio de rolagem ficariam na tela).
 *
 * @param {HTMLElement} sheet
 */
function closeFromBreakpoint(sheet) {
    const query = BREAKPOINTS[sheet.dataset.closeFrom ?? ''];

    if (!query || closeFromBound.has(sheet) || typeof window.matchMedia !== 'function') {
        return;
    }

    closeFromBound.add(sheet);

    window.matchMedia(query).addEventListener('change', (event) => {
        if (event.matches && sheet.classList.contains('open')) {
            HSOverlay.getInstance(sheet, true)?.element.close(true);
        }
    });
}

/**
 * Inicializa (ou reinicializa, para HTML inserido depois) os overlays, os dropdowns e os avisos
 * que se fecham (data-hs-remove-element). autoInit() do
 * Preline ignora elementos já inicializados, então a função pode rodar quantas vezes precisar.
 *
 * @param {ParentNode} [root]
 */
export function initPreline(root = document) {
    window.HSOverlay ??= HSOverlay;
    window.HSDropdown ??= HSDropdown;
    window.HSRemoveElement ??= HSRemoveElement;

    HSOverlay.autoInit();
    HSDropdown.autoInit();
    HSRemoveElement.autoInit();

    elementsWithin(root, '.hs-overlay, .hs-dropdown-menu').forEach(keepNativeActivation);
    elementsWithin(root, '.hs-overlay[data-ui-sheet][data-close-from]').forEach(closeFromBreakpoint);
}

export { HSOverlay, HSDropdown, HSRemoveElement };
