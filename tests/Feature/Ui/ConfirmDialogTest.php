<?php

namespace Tests\Feature\Ui;

use Tests\Feature\Ui\Concerns\InspectsUiMarkup;
use Tests\TestCase;

/**
 * <x-ui.confirm-dialog>: o diálogo único que resources/js/ui/confirm.js usa no lugar da caixa de
 * confirmação nativa do navegador.
 */
class ConfirmDialogTest extends TestCase
{
    use InspectsUiMarkup;

    public function test_it_renders_a_single_alertdialog_with_default_copy_in_portuguese(): void
    {
        $xpath = $this->renderUi('<x-ui.confirm-dialog />');
        $dialog = $this->uiElement($xpath, '//dialog');

        $this->assertSame('confirmacao', $dialog->getAttribute('id'));
        $this->assertSame('alertdialog', $dialog->getAttribute('role'));
        $this->assertTrue($dialog->hasAttribute('data-ui-dialog'));
        $this->assertTrue($dialog->hasAttribute('data-ui-confirm-dialog'));
        $this->assertSame('confirmacao-titulo', $dialog->getAttribute('aria-labelledby'));
        $this->assertSame('confirmacao-descricao', $dialog->getAttribute('aria-describedby'));
        $this->assertSame('Confirmar ação', $this->uiText($this->uiElement($xpath, '//h2[@id="confirmacao-titulo"]')));
        $this->assertSame('Deseja continuar?', $this->uiText($this->uiElement($xpath, '//p[@id="confirmacao-descricao"]')));
        $this->assertHasClasses(['max-w-sm', 'theme-default'], $dialog);
    }

    public function test_esc_cancels_but_backdrop_click_and_close_icon_do_not_exist(): void
    {
        $xpath = $this->renderUi('<x-ui.confirm-dialog />');
        $dialog = $this->uiElement($xpath, '//dialog');

        $this->assertSame('true', $dialog->getAttribute('data-dismissible'));
        $this->assertSame('false', $dialog->getAttribute('data-close-on-backdrop'));
        $this->assertSame(0, $this->uiCount($xpath, '//button[@aria-label="Fechar"]'));
    }

    public function test_focus_starts_on_cancel_and_only_the_confirm_button_returns_the_confirmed_value(): void
    {
        $xpath = $this->renderUi('<x-ui.confirm-dialog />');
        $cancel = $this->uiElement($xpath, '//button[@data-confirm-cancel]');
        $accept = $this->uiElement($xpath, '//button[@data-confirm-accept]');

        $this->assertSame('Cancelar', $this->uiText($cancel));
        $this->assertTrue($cancel->hasAttribute('data-dialog-initial-focus'));
        $this->assertSame('cancelar', $cancel->getAttribute('data-dialog-close'));
        $this->assertSame('button', $cancel->getAttribute('type'));
        $this->assertSame('Confirmar', $this->uiText($accept));
        $this->assertSame('confirmar', $accept->getAttribute('data-dialog-close'));
        $this->assertSame('button', $accept->getAttribute('type'));
        // Os dois são <x-ui.button>; o confirm.js troca a variante do "Confirmar" para danger.
        $this->assertSame('primary', $accept->getAttribute('data-variant'));
        $this->assertSame('secondary', $cancel->getAttribute('data-variant'));
        $this->assertHasClasses(['bg-primary', 'text-primary-foreground', 'min-h-10'], $accept);
        $this->assertHasClasses(['border-border-strong', 'bg-surface', 'min-h-10'], $cancel);
        $this->assertTrue($cancel->isSameNode($this->uiElement($xpath, '//dialog//div[button][1]/button[1]')), 'Cancelar vem antes de Confirmar no DOM (fica embaixo no celular).');
    }

    public function test_custom_id_keeps_the_aria_links(): void
    {
        $dialog = $this->uiElement($this->renderUi('<x-ui.confirm-dialog id="confirmar-exclusao" />'), '//dialog');

        $this->assertSame('confirmar-exclusao', $dialog->getAttribute('id'));
        $this->assertSame('confirmar-exclusao-titulo', $dialog->getAttribute('aria-labelledby'));
        $this->assertSame('confirmar-exclusao-descricao', $dialog->getAttribute('aria-describedby'));
    }

    /**
     * O JS lê os mesmos data-attributes que o comentário do componente documenta e reconhece o
     * mesmo valor de confirmação do botão.
     */
    public function test_confirm_script_follows_the_component_contract(): void
    {
        $script = file_get_contents(resource_path('js/ui/confirm.js'));
        $component = file_get_contents(resource_path('views/components/ui/confirm-dialog.blade.php'));

        foreach (['data-confirm', 'data-confirm-title', 'data-confirm-action-label', 'data-confirm-cancel-label', 'data-confirm-variant'] as $attribute) {
            $this->assertStringContainsString("'{$attribute}'", $script, "confirm.js precisa ler {$attribute}.");
            $this->assertStringContainsString($attribute, $component, "O componente precisa documentar {$attribute}.");
        }

        $this->assertStringContainsString("const CONFIRMED_VALUE = 'confirmar';", $script);
        $this->assertStringContainsString('dialog[data-ui-confirm-dialog]', $script);
        $this->assertStringContainsString('window.revisalogConfirm = confirmAction;', $script);
        $this->assertStringContainsString("'ui:dialog-close'", $script, 'O fechamento vem do evento de dialog.js, não do close nativo.');
        $this->assertDoesNotMatchRegularExpression('/(?<![\w.$])(?:window\.)?confirm\(/', $script, 'Nada de caixa de confirmação nativa.');
        $this->assertStringContainsString("setButtonVariant(accept, isDanger ? 'danger' : 'primary');", $script, 'A variante destrutiva vem do espelho do x-ui.button.');
        $this->assertDoesNotMatchRegularExpression('/btn-(?:primary|secondary|danger)/', $script.$component, 'As classes .btn-* saíram do app.css.');
    }
}
