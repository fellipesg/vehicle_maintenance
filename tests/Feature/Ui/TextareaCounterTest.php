<?php

namespace Tests\Feature\Ui;

use Tests\Feature\Ui\Concerns\InspectsRenderedComponents;
use Tests\TestCase;

/**
 * <x-ui.textarea counter>: contador "120/4.000" ligado ao campo por aria-describedby, estado perto
 * do limite e no limite, e a região aria-live polite que o resources/js/ui/textarea-counter.js usa
 * para avisar a cada 10%.
 */
class TextareaCounterTest extends TestCase
{
    use InspectsRenderedComponents;

    public function test_counter_is_described_by_the_field_and_starts_with_the_current_length(): void
    {
        $document = $this->renderComponent('<x-ui.textarea name="message" maxlength="4000" counter :value="$text" />', ['text' => 'Troca de óleo']);

        $textarea = $this->element($document, 'textarea');
        $this->assertSame('message-contador', $textarea->getAttribute('data-textarea-counter'));
        $this->assertSame(['message-contador'], $this->describedByOf($textarea));
        $this->assertSame('4000', $textarea->getAttribute('maxlength'));

        $counter = $this->element($document, '#message-contador');
        $this->assertSame('ok', $counter->getAttribute('data-state'));
        $this->assertSame('4000', $counter->getAttribute('data-max'));
        $this->assertSame('13/4.000', $this->element($document, '[data-textarea-counter-visual]')->textContent);
        $this->assertSame('true', $this->element($document, '[data-textarea-counter-visual]')->getAttribute('aria-hidden'));
        $this->assertSame('13 de 4.000 caracteres', $this->element($document, '[data-textarea-counter-text]')->textContent);
        $this->assertContains('tabular-nums', $this->classesOf($counter));
        $this->assertContains('data-[state=near]:text-warning', $this->classesOf($counter));
        $this->assertContains('data-[state=limit]:text-danger', $this->classesOf($counter));
        $this->assertContains('motion-reduce:transition-none', $this->classesOf($counter));

        $live = $this->element($document, '[data-textarea-counter-live]');
        $this->assertSame('polite', $live->getAttribute('aria-live'));
        $this->assertSame('true', $live->getAttribute('aria-atomic'));
        $this->assertSame('', trim($live->textContent), 'A região começa vazia: só fala quando cruza uma faixa de 10%.');
    }

    public function test_near_and_at_the_limit_change_the_state(): void
    {
        $near = $this->renderComponent('<x-ui.textarea name="a" maxlength="10" counter value="123456789" />');
        $this->assertSame('near', $this->element($near, '#a-contador')->getAttribute('data-state'));

        $limit = $this->renderComponent('<x-ui.textarea name="b" maxlength="10" counter value="1234567890" />');
        $this->assertSame('limit', $this->element($limit, '#b-contador')->getAttribute('data-state'));
        $this->assertSame('10/10', $this->element($limit, '[data-textarea-counter-visual]')->textContent);
    }

    public function test_counter_counts_old_input_and_escaped_text_like_the_browser(): void
    {
        $this->withOldInput(['notes' => "Linha <1>\r\nLinha 2"]);

        $document = $this->renderComponent('<x-ui.textarea name="notes" maxlength="300" counter value="Antigo" />');

        $this->assertSame('17/300', $this->element($document, '[data-textarea-counter-visual]')->textContent, 'Quebra de linha vale 1 e o texto escapado conta como digitado.');
    }

    public function test_field_hint_and_error_join_the_counter_in_aria_describedby(): void
    {
        $this->withViewErrors(['message' => ['Escreva a mensagem.']]);

        $document = $this->renderComponent('<x-ui.field name="message" label="Mensagem" hint="Conte o que aconteceu."><x-ui.textarea rows="6" maxlength="4000" counter /></x-ui.field>');

        $textarea = $this->element($document, 'textarea');
        $this->assertSame(['message-contador', 'message-hint', 'message-error'], $this->describedByOf($textarea));
        $this->assertSame('true', $textarea->getAttribute('aria-invalid'));
        $this->assertSame('message', $this->element($document, 'label')->getAttribute('for'));
    }

    public function test_without_counter_the_markup_is_unchanged(): void
    {
        $document = $this->renderComponent('<x-ui.textarea name="notes" maxlength="4000" />');

        $this->assertNull($document->querySelector('[data-slot="textarea-counter"]'));
        $this->assertFalse($this->element($document, 'textarea')->hasAttribute('data-textarea-counter'));
        $this->assertFalse($this->element($document, 'textarea')->hasAttribute('aria-describedby'));
    }

    public function test_counter_needs_maxlength(): void
    {
        try {
            $this->blade('<x-ui.textarea name="notes" counter />');
            $this->fail('Contador sem maxlength deveria falhar.');
        } catch (\Throwable $exception) {
            $cause = $exception;

            while (! $cause instanceof \InvalidArgumentException && $cause->getPrevious() !== null) {
                $cause = $cause->getPrevious();
            }

            $this->assertInstanceOf(\InvalidArgumentException::class, $cause, $exception->getMessage());
            $this->assertStringContainsString('x-ui.textarea precisa de maxlength', $cause->getMessage());
        }
    }
}
