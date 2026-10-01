<?php

namespace Tests\Feature\Ui;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Contrato dos scripts dos componentes de formulário (resources/js/ui): cada um exporta um init*()
 * com root = document, idempotente, que o app.js registra no DOMContentLoaded. O comportamento em
 * navegador foi conferido à parte; aqui ficam as garantias que um refactor não pode perder.
 */
class FormControlScriptsTest extends TestCase
{
    /**
     * @return array<string, array{string, string, string}>
     */
    public static function initializers(): array
    {
        return [
            'file-input' => ['js/ui/file-input.js', 'initFileInputs', 'fileInputReady'],
            'password-toggle' => ['js/ui/password-toggle.js', 'initPasswordToggles', 'passwordToggleReady'],
            'switch' => ['js/ui/switch.js', 'initSwitches', 'switchReady'],
            'form-errors' => ['js/ui/form-errors.js', 'initFormErrors', 'formErrorsReady'],
        ];
    }

    #[DataProvider('initializers')]
    public function test_each_script_exports_an_idempotent_initializer_scoped_to_a_root(string $path, string $function, string $readyFlag): void
    {
        $source = file_get_contents(resource_path($path));

        $this->assertStringContainsString("export function {$function}(root = document)", $source);
        $this->assertStringContainsString('import { elementsWithin', $source);
        $this->assertStringContainsString("dataset.{$readyFlag} === 'true'", $source, 'Ligar duas vezes o mesmo elemento duplicaria os ouvintes.');
        $this->assertStringContainsString("dataset.{$readyFlag} = 'true'", $source);
    }

    public function test_password_toggle_keeps_a_fixed_name_and_the_state_in_aria_pressed(): void
    {
        $source = file_get_contents(resource_path('js/ui/password-toggle.js'));

        $this->assertStringContainsString("setAttribute('aria-pressed'", $source);
        $this->assertStringNotContainsString("setAttribute('aria-label'", $source, 'Botão de alternância com nome variável contradiz o aria-pressed (WCAG 4.1.2).');
        $this->assertStringNotContainsString("'Ocultar senha'", $source);
        $this->assertStringContainsString("input.type = isVisible ? 'text' : 'password'", $source);
        $this->assertStringContainsString("addEventListener('submit'", $source, 'A senha volta a ficar oculta no envio.');
    }

    public function test_file_input_enhances_progressively_and_validates_in_portuguese(): void
    {
        $source = file_get_contents(resource_path('js/ui/file-input.js'));

        $this->assertStringContainsString('new DataTransfer()', $source, 'Sem DataTransfer, fica o input nativo.');
        $this->assertStringContainsString('container.dataset.enhanced', $source);
        $this->assertStringContainsString('input.after(dropzone)', $source, 'A área vem depois do input, para peer-focus-visible funcionar.');
        $this->assertStringContainsString('não é de um tipo aceito', $source);
        $this->assertStringContainsString('O limite é', $source);
        $this->assertStringContainsString('Escolha só um arquivo.', $source);
        $this->assertStringContainsString('Remover ${file.name}', $source);
        $this->assertStringContainsString('URL.revokeObjectURL', $source);
        $this->assertStringContainsString('escapeHtml(messages.join', $source, 'Nome de arquivo vai escapado para o innerHTML.');
        $this->assertStringNotContainsString('confirm(', $source);
    }

    public function test_switch_keeps_aria_checked_in_sync(): void
    {
        $source = file_get_contents(resource_path('js/ui/switch.js'));

        $this->assertStringContainsString("setAttribute('aria-checked', input.checked ? 'true' : 'false')", $source);
        $this->assertStringContainsString("addEventListener('change', sync)", $source);
    }

    public function test_form_errors_focus_the_summary_and_the_linked_control(): void
    {
        $source = file_get_contents(resource_path('js/ui/form-errors.js'));

        $this->assertStringContainsString("hasAttribute('data-autofocus')", $source);
        $this->assertStringContainsString('control.focus({ preventScroll: true })', $source);
        $this->assertStringContainsString('prefersReducedMotion()', $source);
    }
}
