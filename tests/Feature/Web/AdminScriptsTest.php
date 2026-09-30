<?php

namespace Tests\Feature\Web;

use Tests\TestCase;

/**
 * Scripts das telas do admin: resources/js/admin.js reúne mapas e editor de artigo numa só entrada
 * (initAdmin) para o app.js chamar; cada módulo é idempotente e não usa caixa nativa nem HTML
 * montado com dados. Diálogos com erro reabrem pelo initDialogs genérico (ui/dialog.js).
 */
class AdminScriptsTest extends TestCase
{
    public function test_admin_entry_starts_every_admin_module(): void
    {
        $entry = file_get_contents(resource_path('js/admin.js'));

        $this->assertStringContainsString('export function initAdmin(root = document)', $entry);
        foreach (['initAdminMaps', 'initAdminBlogEditor', 'initDialogs'] as $init) {
            $this->assertStringContainsString($init.'(root)', $entry);
        }
    }

    public function test_modules_are_idempotent_and_avoid_native_boxes(): void
    {
        $modules = [
            'admin-map.js' => 'dataset.adminMapReady',
            'admin-blog-editor.js' => 'dataset.adminBlogEditorReady',
            'ui/dialog.js' => 'dataset.dialogOpenedOnLoad',
        ];

        foreach ($modules as $file => $readyFlag) {
            $source = file_get_contents(resource_path('js/'.$file));

            $this->assertStringContainsString($readyFlag, $source, "{$file} precisa ser idempotente.");
            $this->assertDoesNotMatchRegularExpression('/(?<![\w.$-])(?:window\.)?(?:confirm|alert)\(/', $source, "{$file} não usa caixa nativa.");
        }
    }

    public function test_failed_form_dialogs_reopen_through_the_shared_dialog_api(): void
    {
        $this->assertFileDoesNotExist(resource_path('js/admin-dialogs.js'), 'A reabertura na carga é do initDialogs genérico; o módulo do admin duplicava a lógica.');

        $dialog = file_get_contents(resource_path('js/ui/dialog.js'));
        $this->assertStringContainsString("elementsWithin(root, 'dialog[data-dialog-open-on-load]').forEach(openOnLoad);", $dialog);
        $this->assertStringContainsString('[data-dialog-open="${CSS.escape(dialog.id)}"]', $dialog);

        $this->assertStringContainsString("import { initDialogs } from './ui/dialog';", file_get_contents(resource_path('js/admin.js')));
        $this->assertStringContainsString('initDialogs();', file_get_contents(resource_path('js/app.js')));
    }
}
