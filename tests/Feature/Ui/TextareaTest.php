<?php

namespace Tests\Feature\Ui;

use Tests\Feature\Ui\Concerns\InspectsRenderedComponents;
use Tests\TestCase;

/**
 * <x-ui.textarea>: valor (prop ou slot), old() e estado inválido.
 */
class TextareaTest extends TestCase
{
    use InspectsRenderedComponents;

    public function test_value_prop_is_escaped_and_rows_default_to_four(): void
    {
        $html = (string) $this->blade('<x-ui.textarea name="notes" :value="$notes" maxlength="4000" class="font-mono" />', ['notes' => 'Trocar <óleo> & filtro']);
        $textarea = $this->element($this->parseHtml($html), 'textarea');

        $this->assertSame('Trocar <óleo> & filtro', $textarea->textContent);
        $this->assertStringNotContainsString('<óleo>', $html);
        $this->assertSame('notes', $textarea->getAttribute('id'));
        $this->assertSame('4', $textarea->getAttribute('rows'));
        $this->assertSame('4000', $textarea->getAttribute('maxlength'));
        $this->assertContains('form-input', $this->classesOf($textarea));
        $this->assertContains('resize-y', $this->classesOf($textarea));
        $this->assertContains('font-mono', $this->classesOf($textarea));
    }

    public function test_slot_is_the_value_when_there_is_no_prop(): void
    {
        $textarea = $this->element($this->renderComponent('<x-ui.textarea name="body">{{ $text }}</x-ui.textarea>', ['text' => 'Linha <1>']), 'textarea');

        $this->assertSame('Linha <1>', $textarea->textContent);
    }

    public function test_old_input_wins_even_when_the_user_cleared_the_text(): void
    {
        $this->withOldInput(['notes' => 'Novo texto', 'summary' => null]);

        $document = $this->renderComponent('<x-ui.textarea name="notes" value="Antigo" /><x-ui.textarea name="summary" value="Resumo salvo" /><x-ui.textarea name="other" value="Intacto" />');

        $this->assertSame('Novo texto', $this->element($document, '#notes')->textContent);
        $this->assertSame('', $this->element($document, '#summary')->textContent);
        $this->assertSame('Intacto', $this->element($document, '#other')->textContent, 'Campo fora do envio mantém o valor.');
    }

    public function test_autosize_and_invalid_state(): void
    {
        $this->withViewErrors(['message' => ['Escreva a mensagem.']]);

        $textarea = $this->element($this->renderComponent('<x-ui.textarea name="message" autosize rows="3" />'), 'textarea');

        $this->assertSame('true', $textarea->getAttribute('aria-invalid'));
        $this->assertSame('3', $textarea->getAttribute('rows'));
        $this->assertContains('field-sizing-content', $this->classesOf($textarea));
        $this->assertNotContains('resize-y', $this->classesOf($textarea));
    }
}
