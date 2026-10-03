import { closeDialog, openDialog } from './dialog';
import { elementsWithin } from './dom';

/**
 * Paleta de comandos de <x-ui.command> (dialog[data-ui-command]).
 *
 * - Atalho: ⌘K no Mac e Ctrl+K nos demais abre e fecha a paleta (a primeira da página). Não abre
 *   por cima de outro diálogo aberto.
 * - Gatilhos: [data-command-open="{id}"]. Em link, só o clique simples abre a paleta; Ctrl/Cmd e o
 *   botão do meio seguem o href (a busca, sem JS). O gatilho ganha aria-haspopup="dialog",
 *   aria-controls e aria-keyshortcuts, e os [data-command-shortcut] mostram "⌘K" ou "Ctrl K".
 * - Campo (role="combobox"): filtra as opções sem acento e sem diferença de maiúsculas, por todas
 *   as palavras digitadas, no rótulo e nas palavras-chave (o grupo). A opção ativa fica em
 *   aria-activedescendant e aria-selected; setas para cima e para baixo trocam (dando a volta),
 *   Enter abre, Ctrl/Cmd+Enter abre em nova aba. O mouse ativa ao passar e abre ao clicar.
 * - "Buscar veículo": com texto, vira "Buscar veículo “ABC1D23”" e abre a busca que já existe com o
 *   texto no parâmetro data-command-param (searchUrl); sem texto, abre a página da busca. Se o
 *   texto parece placa, chassi ou RENAVAM, o grupo Buscar sobe para o topo e a opção já vem ativa.
 * - A região role="status" diz quantas opções sobraram, com uma pausa curta.
 *
 * Idempotente: pode ser chamado de novo com o trecho novo como raiz.
 */

const STATUS_DELAY_MS = 350;
const readyPalettes = new WeakSet();
let shortcutListenerReady = false;

/**
 * Texto sem acento, em minúsculas e sem espaços sobrando, para comparar.
 *
 * @param {unknown} value
 * @returns {string}
 */
export function normalizeText(value) {
    return String(value ?? '')
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/\s+/g, ' ')
        .trim();
}

/**
 * Todas as palavras da busca aparecem no texto (em qualquer ordem).
 *
 * @param {string} text
 * @param {string} query
 * @returns {boolean}
 */
export function matchesQuery(text, query) {
    const terms = normalizeText(query).split(' ').filter(Boolean);
    const haystack = normalizeText(text);

    return terms.every((term) => haystack.includes(term));
}

/**
 * O texto tem cara de placa (ABC1234 ou Mercosul ABC1D23), chassi (17 caracteres, com número e sem
 * I, O ou Q) ou RENAVAM (9 a 11 dígitos).
 *
 * @param {string} query
 * @returns {boolean}
 */
export function looksLikeVehicleIdentifier(query) {
    const compact = String(query ?? '').replace(/[\s.\-]/g, '').toUpperCase();

    return /^[A-Z]{3}[0-9][A-Z0-9][0-9]{2}$/.test(compact)
        || /^(?=.*[0-9])[A-HJ-NPR-Z0-9]{17}$/.test(compact)
        || /^[0-9]{9,11}$/.test(compact);
}

/**
 * URL da busca com o texto no parâmetro (mantém os parâmetros que a URL já tiver).
 *
 * @param {string} action
 * @param {string} param
 * @param {string} query
 * @returns {string}
 */
export function searchUrl(action, param, query) {
    const url = new URL(action, window.location.href);
    const text = String(query ?? '').trim();

    if (text !== '' && param) {
        url.searchParams.set(param, text);
    }

    return url.toString();
}

/**
 * @returns {boolean}
 */
export function isApplePlatform() {
    const platform = navigator.userAgentData?.platform || navigator.platform || navigator.userAgent || '';

    return /mac|iphone|ipad|ipod/i.test(platform);
}

/**
 * @param {KeyboardEvent} event
 * @param {boolean} apple
 * @returns {boolean}
 */
export function isPaletteShortcut(event, apple) {
    const modifier = apple ? event.metaKey && !event.ctrlKey : event.ctrlKey && !event.metaKey;

    return modifier && !event.altKey && !event.shiftKey && !event.isComposing && String(event.key).toLowerCase() === 'k';
}

/**
 * @param {number} count
 * @returns {string}
 */
function statusText(count) {
    if (count === 0) {
        return 'Nenhuma opção encontrada.';
    }

    return count === 1 ? '1 opção.' : `${count} opções.`;
}

/**
 * @param {HTMLDialogElement} dialog
 */
function bindPalette(dialog) {
    if (readyPalettes.has(dialog)) {
        return;
    }

    const input = dialog.querySelector('[data-command-input]');
    const list = dialog.querySelector('[data-command-list]');
    const status = dialog.querySelector('[data-command-status]');
    const searchGroup = dialog.querySelector('[data-command-group="busca"]');
    const searchOption = dialog.querySelector('[data-command-search]');

    if (!(input instanceof HTMLInputElement) || !(list instanceof HTMLElement)) {
        return;
    }

    readyPalettes.add(dialog);

    const options = Array.from(dialog.querySelectorAll('[data-command-option]'));
    const groups = Array.from(dialog.querySelectorAll('[data-command-group]'));
    const searchLabel = searchOption?.querySelector('[data-command-label]') ?? null;
    const searchQuery = searchOption?.querySelector('[data-command-search-query]') ?? null;
    const defaultSearchLabel = searchLabel?.textContent ?? '';
    let active = null;
    let statusTimer = 0;

    // Na ordem da tela: o grupo Buscar muda de lugar conforme o texto.
    const visibleOptions = () => Array.from(list.querySelectorAll('[data-command-option]')).filter((option) => !option.hidden);

    const setActive = (option, { scroll = true } = {}) => {
        active?.setAttribute('aria-selected', 'false');
        active = option instanceof HTMLElement ? option : null;

        if (!active) {
            input.removeAttribute('aria-activedescendant');

            return;
        }

        active.setAttribute('aria-selected', 'true');
        input.setAttribute('aria-activedescendant', active.id);

        if (scroll) {
            active.scrollIntoView({ block: 'nearest' });
        }
    };

    const filter = () => {
        const query = input.value.trim();
        const identifier = looksLikeVehicleIdentifier(query);

        options.forEach((option) => {
            if (option === searchOption) {
                return;
            }

            const text = `${option.querySelector('[data-command-label]')?.textContent ?? ''} ${option.dataset.commandKeywords ?? ''}`;
            option.hidden = query !== '' && !matchesQuery(text, query);
        });

        if (searchLabel && searchQuery) {
            searchLabel.textContent = query === '' ? defaultSearchLabel : 'Buscar veículo ';
            searchQuery.textContent = query === '' ? '' : `“${query}”`;
        }

        if (searchGroup) {
            if (identifier) {
                list.prepend(searchGroup);
            } else {
                list.append(searchGroup);
            }
        }

        groups.forEach((group) => {
            group.hidden = !Array.from(group.querySelectorAll('[data-command-option]')).some((option) => !option.hidden);
        });

        const visible = visibleOptions();
        const preferred = identifier && searchOption ? searchOption : visible[0];
        setActive(preferred ?? null, { scroll: false });
        list.scrollTop = 0;

        window.clearTimeout(statusTimer);
        statusTimer = window.setTimeout(() => {
            if (status) {
                status.textContent = statusText(visibleOptions().length);
            }
        }, STATUS_DELAY_MS);
    };

    const move = (step) => {
        const visible = visibleOptions();

        if (visible.length === 0) {
            return;
        }

        const index = visible.indexOf(active);
        const next = index === -1 ? (step > 0 ? 0 : visible.length - 1) : (index + step + visible.length) % visible.length;
        setActive(visible[next]);
    };

    const activate = (option, { newTab = false } = {}) => {
        if (!(option instanceof HTMLElement)) {
            return;
        }

        const url = option === searchOption
            ? searchUrl(option.dataset.commandUrl || '', option.dataset.commandParam || '', input.value)
            : option.dataset.commandUrl;

        if (!url) {
            return;
        }

        if (newTab) {
            window.open(url, '_blank', 'noopener');

            return;
        }

        closeDialog(dialog);
        window.location.assign(url);
    };

    input.addEventListener('input', filter);

    input.addEventListener('keydown', (event) => {
        if (event.isComposing) {
            return;
        }

        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            move(event.key === 'ArrowDown' ? 1 : -1);
        } else if (event.key === 'Enter') {
            event.preventDefault();
            activate(active, { newTab: event.metaKey || event.ctrlKey });
        }
    });

    list.addEventListener('pointermove', (event) => {
        const option = event.target instanceof Element ? event.target.closest('[data-command-option]') : null;

        if (option && option !== active && !option.hidden) {
            setActive(option, { scroll: false });
        }
    });

    list.addEventListener('click', (event) => {
        const option = event.target instanceof Element ? event.target.closest('[data-command-option]') : null;

        if (option) {
            activate(option, { newTab: event.metaKey || event.ctrlKey });
            input.focus({ preventScroll: true });
        }
    });

    // Não deixa o clique numa opção tirar o foco do campo.
    list.addEventListener('mousedown', (event) => event.preventDefault());

    dialog.addEventListener('ui:dialog-open', () => {
        input.value = '';
        filter();
    });

    filter();
}

/**
 * @param {Element|null} trigger
 * @returns {HTMLDialogElement|null}
 */
function paletteFor(trigger) {
    const id = trigger?.getAttribute('data-command-open') || '';
    const dialog = id !== '' ? document.getElementById(id) : document.querySelector('dialog[data-ui-command]');

    return dialog instanceof HTMLDialogElement && dialog.hasAttribute('data-ui-command') ? dialog : null;
}

/**
 * @param {Element} trigger
 * @param {boolean} apple
 */
function describeTrigger(trigger, apple) {
    const dialog = paletteFor(trigger);

    if (!dialog) {
        return;
    }

    trigger.setAttribute('aria-haspopup', 'dialog');
    trigger.setAttribute('aria-controls', dialog.id);
    trigger.setAttribute('aria-keyshortcuts', apple ? 'Meta+K' : 'Control+K');
}

/**
 * Abre a paleta (a primeira da página, ou a do id).
 *
 * @param {string} [id]
 * @param {{ opener?: Element|null }} [options]
 * @returns {HTMLDialogElement|null}
 */
export function openCommandPalette(id = '', { opener = document.activeElement } = {}) {
    const dialog = id !== '' ? document.getElementById(id) : document.querySelector('dialog[data-ui-command]');

    if (!(dialog instanceof HTMLDialogElement) || !dialog.hasAttribute('data-ui-command')) {
        return null;
    }

    bindPalette(dialog);

    return openDialog(dialog, { opener });
}

/**
 * @param {ParentNode} [root]
 */
export function initCommandPalettes(root = document) {
    const apple = isApplePlatform();

    elementsWithin(root, 'dialog[data-ui-command]').forEach((dialog) => {
        if (dialog instanceof HTMLDialogElement) {
            bindPalette(dialog);
        }
    });

    elementsWithin(root, '[data-command-open]').forEach((trigger) => describeTrigger(trigger, apple));
    elementsWithin(root, '[data-command-shortcut]').forEach((shortcut) => {
        shortcut.textContent = apple ? '⌘K' : 'Ctrl K';
    });

    if (shortcutListenerReady) {
        return;
    }

    shortcutListenerReady = true;

    document.addEventListener('keydown', (event) => {
        if (!isPaletteShortcut(event, apple)) {
            return;
        }

        const dialog = document.querySelector('dialog[data-ui-command]');

        if (!(dialog instanceof HTMLDialogElement)) {
            return;
        }

        event.preventDefault();

        if (dialog.open) {
            closeDialog(dialog);

            return;
        }

        if (document.querySelector('dialog[open]') === null) {
            openCommandPalette(dialog.id);
        }
    });

    document.addEventListener('click', (event) => {
        const trigger = event.target instanceof Element ? event.target.closest('[data-command-open]') : null;

        if (!trigger || event.defaultPrevented) {
            return;
        }

        const isLink = trigger instanceof HTMLAnchorElement;

        if (isLink && (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey)) {
            return;
        }

        const dialog = paletteFor(trigger);

        if (!dialog) {
            return;
        }

        event.preventDefault();
        openCommandPalette(dialog.id, { opener: trigger });
    });
}
