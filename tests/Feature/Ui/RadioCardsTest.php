<?php

namespace Tests\Feature\Ui;

use Tests\Feature\Ui\Concerns\InspectsRenderedComponents;
use Tests\TestCase;

/**
 * <x-ui.radio-cards>: escolha única em cartões, com o radio nativo por dentro (setas do teclado),
 * name, selected e erro vindos do <x-ui.fieldset>, e o cartão marcado indicado por borda e check,
 * não só por cor.
 */
class RadioCardsTest extends TestCase
{
    use InspectsRenderedComponents;

    private const OPTIONS = '[
        \'draft\' => [\'label\' => \'Rascunho\', \'description\' => \'Só o admin vê.\', \'icon\' => \'pencil-square\'],
        \'published\' => [\'label\' => \'Publicar agora\', \'icon\' => \'globe-alt\'],
        \'scheduled\' => [\'label\' => \'Agendar\', \'disabled\' => true],
    ]';

    public function test_cards_are_native_radios_named_by_the_fieldset(): void
    {
        $document = $this->renderComponent(
            '<x-ui.fieldset name="status" legend="Publicação" selected="published" required>'
            .'<x-ui.radio-cards :columns="3" :options="'.self::OPTIONS.'" />'
            .'</x-ui.fieldset>'
        );

        $grid = $this->element($document, '[data-slot="radio-cards"]');
        $this->assertContains('sm:grid-cols-2', $this->classesOf($grid));
        $this->assertContains('lg:grid-cols-3', $this->classesOf($grid));

        $radios = $document->querySelectorAll('input[type="radio"]');
        $this->assertCount(3, $radios);

        foreach ($radios as $radio) {
            $this->assertSame('status', $radio->getAttribute('name'));
            $this->assertContains('sr-only', $this->classesOf($radio), 'O radio sai da tela mas continua focável e com as setas.');
            $this->assertNotNull($document->querySelector('label[for="'.$radio->getAttribute('id').'"]'));
        }

        $this->assertSame('status_draft', $radios->item(0)->getAttribute('id'));
        $this->assertFalse($radios->item(0)->hasAttribute('checked'));
        $this->assertTrue($radios->item(1)->hasAttribute('checked'), 'O selected do fieldset marca a opção.');
        $this->assertTrue($radios->item(2)->hasAttribute('disabled'));
        $this->assertSame('status_draft-description', $radios->item(0)->getAttribute('aria-describedby'));
        $this->assertSame('Só o admin vê.', $this->element($document, '#status_draft-description')->textContent);
        $this->assertNull($document->querySelector('#status_published')->getAttribute('aria-describedby'));
    }

    public function test_selected_card_has_a_shape_cue_and_keyboard_focus_ring(): void
    {
        $document = $this->renderComponent('<x-ui.radio-cards name="plan" :options="[\'a\' => \'Básico\', \'b\' => \'Completo\']" />');

        $card = $this->element($document, 'label[data-slot="radio-card"]');
        $classes = $this->classesOf($card);

        foreach (['has-checked:border-ring', 'has-checked:bg-accent', 'has-focus-visible:outline-2', 'has-focus-visible:outline-ring', 'motion-reduce:transition-none', 'duration-fast', 'ease-smooth-out', 'border-border'] as $class) {
            $this->assertContains($class, $classes, "Faltou {$class} no cartão.");
        }

        $indicator = $this->element($document, '[data-slot="radio-card-indicator"]');
        $this->assertSame('true', $indicator->getAttribute('aria-hidden'));
        $this->assertContains('peer-checked:bg-ring', $this->classesOf($indicator));
        $this->assertNotNull($indicator->querySelector('svg'), 'O check aparece no cartão marcado.');
        $this->assertContains('sm:grid-cols-2', $this->classesOf($this->element($document, '[data-slot="radio-cards"]')));
    }

    public function test_old_input_wins_and_group_error_is_linked(): void
    {
        $this->withOldInput(['status' => 'draft']);
        $this->withViewErrors(['status' => ['Escolha como publicar o artigo.']]);

        $document = $this->renderComponent(
            '<x-ui.fieldset name="status" legend="Publicação" selected="published">'
            .'<x-ui.radio-cards :options="'.self::OPTIONS.'" required />'
            .'</x-ui.fieldset>'
        );

        $this->assertTrue($this->element($document, '#status_draft')->hasAttribute('checked'));
        $this->assertFalse($this->element($document, '#status_published')->hasAttribute('checked'));
        $this->assertSame('true', $this->element($document, '#status_published')->getAttribute('aria-invalid'));
        $this->assertTrue($this->element($document, '#status_published')->hasAttribute('required'));
        $this->assertSame(['status_draft-description', 'status-error'], $this->describedByOf($this->element($document, '#status_draft')));
        $this->assertStringContainsString('Escolha como publicar o artigo.', $this->element($document, '#status-error')->textContent);
        $this->assertContains('border-danger', $this->classesOf($this->element($document, 'label[data-slot="radio-card"]')));
        $this->assertNotContains('border-border', $this->classesOf($this->element($document, 'label[data-slot="radio-card"]')));
    }

    public function test_options_and_name_are_required(): void
    {
        foreach ([
            '<x-ui.radio-cards name="plan" :options="[]" />' => 'x-ui.radio-cards precisa de options',
            '<x-ui.radio-cards :options="[\'a\' => \'Básico\']" />' => 'x-ui.radio-cards precisa de name',
            '<x-ui.radio-cards name="plan" columns="5" :options="[\'a\' => \'Básico\']" />' => 'x-ui.radio-cards: columns "5" não existe',
        ] as $template => $message) {
            try {
                $this->blade($template);
                $this->fail("Esperava erro em {$template}");
            } catch (\Throwable $exception) {
                $cause = $exception;

                while (! $cause instanceof \InvalidArgumentException && $cause->getPrevious() !== null) {
                    $cause = $cause->getPrevious();
                }

                $this->assertInstanceOf(\InvalidArgumentException::class, $cause, $exception->getMessage());
                $this->assertStringContainsString($message, $cause->getMessage());
            }
        }
    }
}
