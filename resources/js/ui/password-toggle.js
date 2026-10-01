import { elementsWithin } from './dom';

/**
 * Botão "Mostrar senha" do <x-ui.input type="password">. O botão vem com hidden do servidor (sem
 * JS não há o que alternar) e aparece aqui. Alterna o type do campo entre password e text; o
 * estado fica só no aria-pressed e o nome é fixo ("Mostrar senha, pressionado" = senha visível).
 * Trocar o nome junto com o aria-pressed faria o leitor de tela anunciar "Ocultar senha,
 * pressionado" com a senha à mostra (WCAG 4.1.2). O ícone muda (olho / olho cortado) só para quem
 * vê. Ao enviar o formulário, a senha volta a ficar oculta.
 */

const TOGGLE_SELECTOR = '[data-password-toggle]';

/**
 * Liga os botões de root (e o próprio root). Idempotente: botão já ligado é ignorado.
 *
 * @param {ParentNode} [root]
 */
export function initPasswordToggles(root = document) {
    elementsWithin(root, TOGGLE_SELECTOR).forEach(bindPasswordToggle);
}

/**
 * @param {Element} button
 */
function bindPasswordToggle(button) {
    if (!(button instanceof HTMLButtonElement) || button.dataset.passwordToggleReady === 'true') {
        return;
    }

    const input = passwordInputFor(button);

    if (!input) {
        return;
    }

    button.dataset.passwordToggleReady = 'true';

    const showIcon = button.querySelector('[data-password-toggle-icon="show"]');
    const hideIcon = button.querySelector('[data-password-toggle-icon="hide"]');

    const render = (isVisible) => {
        input.type = isVisible ? 'text' : 'password';
        button.setAttribute('aria-pressed', isVisible ? 'true' : 'false');
        showIcon?.toggleAttribute('hidden', isVisible);
        hideIcon?.toggleAttribute('hidden', !isVisible);
    };

    button.addEventListener('click', () => {
        render(input.type === 'password');
    });

    input.form?.addEventListener('submit', () => {
        render(false);
    });

    render(input.type !== 'password');
    button.hidden = false;
}

/**
 * O campo que o botão controla: o id de aria-controls ou, sem ele, o campo da mesma moldura.
 *
 * @param {HTMLButtonElement} button
 * @returns {HTMLInputElement|null}
 */
function passwordInputFor(button) {
    const controlsId = button.getAttribute('aria-controls');
    const byId = controlsId ? button.ownerDocument.getElementById(controlsId) : null;
    const input = byId ?? button.closest('[data-slot="input-group"]')?.querySelector('input[data-slot="control"]');

    return input instanceof HTMLInputElement ? input : null;
}
