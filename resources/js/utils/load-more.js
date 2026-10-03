import { buttonClass } from '../ui/button';

/**
 * Botão "Carregar mais" que não derruba o foco de quem navega por teclado ou leitor de tela.
 *
 * O contador "Mostrando X de Y" fica num role="status" que continua no DOM entre as páginas,
 * então o leitor anuncia a mudança. Enquanto carrega, o botão fica desabilitado e o navegador
 * tira o foco dele; depois do append, focusAfterAppend() devolve o foco ao botão ou, na última
 * página (quando o botão some), ao primeiro item novo da lista.
 *
 * @param {HTMLElement} pager Contêiner do contador e do botão.
 * @param {() => void} onLoadMore Chamado no clique, com o botão já desabilitado.
 */
export function createLoadMorePager(pager, onLoadMore) {
    /** @type {HTMLElement|null} */
    let status = null;
    /** @type {HTMLButtonElement|null} */
    let button = null;

    const build = () => {
        status = document.createElement('p');
        status.className = 'text-sm text-muted-foreground';
        status.setAttribute('role', 'status');

        const loadMoreButton = document.createElement('button');
        loadMoreButton.type = 'button';
        loadMoreButton.className = `${buttonClass('secondary')} mt-2`;
        loadMoreButton.setAttribute('data-load-more', '');
        loadMoreButton.addEventListener('click', () => {
            loadMoreButton.disabled = true;
            loadMoreButton.textContent = 'Carregando...';
            onLoadMore();
        });
        button = loadMoreButton;

        pager.replaceChildren(status, button);
    };

    return {
        /**
         * @param {{ shown: number, total?: number|null, hasMore: boolean }} state
         */
        render({ shown, total, hasMore }) {
            if (!hasMore) {
                pager.replaceChildren();
                status = null;
                button = null;

                return;
            }

            if (!status?.isConnected || !button?.isConnected) {
                build();
            }

            status.textContent = `Mostrando ${shown} de ${total ?? shown}`;
            button.disabled = false;
            button.textContent = 'Carregar mais';
        },

        /**
         * @param {HTMLElement} list
         * @param {number} previousCount Quantos itens a lista tinha antes do append.
         */
        focusAfterAppend(list, previousCount) {
            if (button?.isConnected) {
                button.focus();

                return;
            }

            list.children[previousCount]?.focus();
        },
    };
}
