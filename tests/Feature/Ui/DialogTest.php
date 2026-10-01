<?php

namespace Tests\Feature\Ui;

use Tests\Feature\Ui\Concerns\InspectsUiMarkup;
use Tests\TestCase;

/**
 * <x-ui.dialog>: <dialog> nativo com nome e descrição acessíveis, rodapé de ações e fechamento
 * configurável, ligado por resources/js/ui/dialog.js.
 */
class DialogTest extends TestCase
{
    use InspectsUiMarkup;

    public function test_dialog_is_a_native_dialog_named_by_its_title_and_described_by_its_description(): void
    {
        $xpath = $this->renderUi('<x-ui.dialog id="dialogo-placa" title="Trocar placa" description="A placa antiga fica no histórico.">Campos</x-ui.dialog>');
        $dialog = $this->uiElement($xpath, '//dialog');

        $this->assertSame('dialogo-placa', $dialog->getAttribute('id'));
        $this->assertSame('dialogo-placa-titulo', $dialog->getAttribute('aria-labelledby'));
        $this->assertSame('dialogo-placa-descricao', $dialog->getAttribute('aria-describedby'));
        $this->assertFalse($dialog->hasAttribute('role'), 'O <dialog> já tem o papel dialog.');
        $this->assertTrue($dialog->hasAttribute('data-ui-dialog'));
        $this->assertSame('true', $dialog->getAttribute('data-dismissible'));
        $this->assertSame('true', $dialog->getAttribute('data-close-on-backdrop'));
        $this->assertSame('Trocar placa', $this->uiText($this->uiElement($xpath, '//h2[@id="dialogo-placa-titulo"]')));
        $this->assertSame('A placa antiga fica no histórico.', $this->uiText($this->uiElement($xpath, '//p[@id="dialogo-placa-descricao"]')));
        $this->assertStringContainsString('Campos', $dialog->textContent);
    }

    public function test_dialog_without_description_has_no_dangling_aria_describedby(): void
    {
        $dialog = $this->uiElement($this->renderUi('<x-ui.dialog id="d" title="Título">Corpo</x-ui.dialog>'), '//dialog');

        $this->assertFalse($dialog->hasAttribute('aria-describedby'));
    }

    public function test_close_button_is_labelled_in_portuguese_and_comes_last_so_it_is_not_the_initial_focus(): void
    {
        $xpath = $this->renderUi('<x-ui.dialog id="d" title="Título"><input id="campo"><x-slot:footer><button type="button" data-dialog-close>Cancelar</button></x-slot:footer></x-ui.dialog>');
        $closeButton = $this->uiElement($xpath, '//dialog/button[@aria-label="Fechar"]');

        $this->assertSame('button', $closeButton->getAttribute('type'));
        $this->assertTrue($closeButton->hasAttribute('data-dialog-close'));
        $this->assertTrue($closeButton->hasAttribute('data-dialog-close-button'));
        $this->assertHasClasses(['size-10', 'rounded-control', 'hover:bg-surface-muted', 'motion-reduce:transition-none'], $closeButton);
        $this->assertSame('true', $this->uiElement($xpath, '//dialog/button//svg')->getAttribute('aria-hidden'));
        $this->assertTrue($closeButton->isSameNode($this->uiElement($xpath, '//dialog/*[last()]')), 'O "Fechar" vem por último no DOM.');
    }

    public function test_footer_stacks_on_mobile_with_the_primary_action_on_top(): void
    {
        $footer = $this->uiElement(
            $this->renderUi('<x-ui.dialog id="d" title="Título"><x-slot:footer class="border-t pt-4"><button>Cancelar</button><button>Salvar</button></x-slot:footer></x-ui.dialog>'),
            '//dialog/div[button[.="Salvar"]]',
        );

        $this->assertHasClasses(['mt-6', 'flex', 'flex-col-reverse', 'gap-2', 'sm:flex-row', 'sm:justify-end', 'border-t', 'pt-4'], $footer);
    }

    public function test_sizes_map_to_max_widths(): void
    {
        $expectations = ['sm' => 'max-w-sm', 'md' => 'max-w-lg', 'lg' => 'max-w-2xl', 'xl' => 'max-w-4xl'];

        foreach ($expectations as $size => $class) {
            $dialog = $this->uiElement($this->renderUi('<x-ui.dialog id="d" title="T" :size="$size" />', ['size' => $size]), '//dialog');

            $this->assertHasClasses([$class], $dialog);
        }

        $this->assertUiRejects('<x-ui.dialog id="d" title="T" size="gigante" />', 'x-ui.dialog: size "gigante" não existe');
    }

    public function test_surface_uses_tokens_stays_light_and_animates_only_without_reduced_motion(): void
    {
        $dialog = $this->uiElement($this->renderUi('<x-ui.dialog id="d" title="T" class="extra-classe" />'), '//dialog');

        $this->assertHasClasses([
            'theme-default', 'm-auto', 'bg-surface', 'text-foreground', 'border-border', 'rounded-overlay', 'shadow-xl',
            'backdrop:bg-overlay', 'open:opacity-100', 'starting:open:opacity-0', 'motion-safe:scale-[.96]',
            'motion-safe:open:scale-100', 'transition-discrete', 'duration-fast', 'open:duration-base', 'ease-smooth-out',
            'motion-reduce:transition-none', 'max-h-[calc(100dvh-2rem)]', 'overflow-y-auto', 'extra-classe',
        ], $dialog);
    }

    public function test_alertdialog_that_is_not_dismissible_keeps_esc_backdrop_and_close_button_off(): void
    {
        $xpath = $this->renderUi('<x-ui.dialog id="d" title="Atenção" role="alertdialog" :dismissible="false" />');
        $dialog = $this->uiElement($xpath, '//dialog');

        $this->assertSame('alertdialog', $dialog->getAttribute('role'));
        $this->assertSame('false', $dialog->getAttribute('data-dismissible'));
        $this->assertSame('false', $dialog->getAttribute('data-close-on-backdrop'));
        $this->assertSame(0, $this->uiCount($xpath, '//button[@aria-label="Fechar"]'));
        $this->assertUiRejects('<x-ui.dialog id="d" title="T" role="modal" />', 'x-ui.dialog: role "modal" não existe');
    }

    public function test_backdrop_and_close_button_can_be_turned_off_independently(): void
    {
        $xpath = $this->renderUi('<x-ui.dialog id="d" title="T" :close-on-backdrop="false" />');

        $this->assertSame('true', $this->uiElement($xpath, '//dialog')->getAttribute('data-dismissible'));
        $this->assertSame('false', $this->uiElement($xpath, '//dialog')->getAttribute('data-close-on-backdrop'));
        $this->assertSame(1, $this->uiCount($xpath, '//button[@aria-label="Fechar"]'));

        $this->assertSame(0, $this->uiCount($this->renderUi('<x-ui.dialog id="d" title="T" :close-button="false" />'), '//button[@aria-label="Fechar"]'));
    }

    public function test_title_and_description_are_escaped_and_required(): void
    {
        $html = (string) $this->blade('<x-ui.dialog id="d" :title="$title" :description="$description" />', [
            'title' => 'Excluir <script>',
            'description' => 'Placa "ABC"',
        ]);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('Excluir &lt;script&gt;', $html);
        $this->assertUiRejects('<x-ui.dialog id="d" title=" " />', 'x-ui.dialog precisa de title');
    }

    /**
     * Formulário dentro de um diálogo que voltou do servidor com erro: data-dialog-open-on-load
     * passa pelo componente e initDialogs() (resources/js/ui/dialog.js) abre o diálogo na carga, uma
     * vez só, devolvendo o foco ao gatilho. Vale para qualquer portal, não só para o admin.
     */
    public function test_dialog_marked_to_open_on_load_is_opened_by_the_generic_script(): void
    {
        $dialog = $this->uiElement($this->renderUi('<x-ui.dialog id="nova-categoria" title="Nova categoria" data-dialog-open-on-load="true" />'), '//dialog');

        $this->assertSame('true', $dialog->getAttribute('data-dialog-open-on-load'));

        $script = file_get_contents(resource_path('js/ui/dialog.js'));
        $init = substr($script, (int) strpos($script, 'export function initDialogs'));

        $this->assertStringContainsString("elementsWithin(root, 'dialog[data-dialog-open-on-load]').forEach(openOnLoad);", $init);
        $this->assertStringContainsString("dialog.dataset.dialogOpenedOnLoad === 'true' || dialog.open", $script, 'Abre uma vez só.');
        $this->assertStringContainsString('[data-dialog-open="${CSS.escape(dialog.id)}"]', $script, 'O foco volta ao gatilho.');
    }
}
