import { initAdminBlogEditor } from './admin-blog-editor';
import { initAdminMaps } from './admin-map';
import { initDialogs } from './ui/dialog';

/**
 * Scripts das telas do admin que não são o filtro de manutenções (initAdminMaintenancesFilters):
 * mapas (admin-map.js) e editor de artigo (admin-blog-editor.js). Cada um só age onde encontra o
 * próprio data-*, então é seguro chamar em qualquer página. Idempotente; para HTML inserido depois,
 * passe o trecho.
 *
 * Os diálogos de formulário que voltaram do servidor com erro (<dialog data-dialog-open-on-load>)
 * reabrem pelo initDialogs() genérico de resources/js/ui/dialog.js, que o app.js já chama na página
 * inteira; aqui ele roda de novo só para o trecho inserido depois.
 *
 * @param {ParentNode} [root]
 */
export function initAdmin(root = document) {
    initAdminMaps(root);
    initAdminBlogEditor(root);

    if (root !== document) {
        initDialogs(root);
    }
}
