import { writeToClipboard } from './ui/copy';
import { toast } from './ui/toast';

/**
 * "Compartilhar" do Selo da oficina (x-provenance-seal e /v/{código}): [data-share-verification]
 * com data-url. Copiar o código e o link é do <x-ui.copy-button> (resources/js/ui/copy.js).
 *
 * O botão nasce oculto e só aparece onde o navegador tem a folha de compartilhamento nativa
 * (celular, principalmente); sem ela, "Copiar link" já cobre o caso. Se o compartilhamento falhar
 * (e não foi a pessoa que cancelou), o link vai para a área de transferência com um toast, que o
 * leitor de tela anuncia. Idempotente.
 *
 * @param {ParentNode} [root]
 */
export function initProvenanceActions(root = document) {
    root.querySelectorAll('[data-share-verification]').forEach((button) => {
        if (!(button instanceof HTMLElement) || button.dataset.provenanceBound === '1') {
            return;
        }

        button.dataset.provenanceBound = '1';

        if (typeof navigator.share !== 'function') {
            return;
        }

        button.hidden = false;
        button.addEventListener('click', async () => {
            const url = button.dataset.url ?? '';

            try {
                await navigator.share({ title: 'Selo da oficina', url });
            } catch (error) {
                if (error?.name === 'AbortError') {
                    return;
                }

                if (await writeToClipboard(url)) {
                    toast({ title: 'Link copiado', description: url });
                } else {
                    toast({ variant: 'warning', title: 'Não foi possível compartilhar.', description: 'Use "Copiar link" ao lado.' });
                }
            }
        });
    });
}
