<?php

namespace Tests\Feature\Ui;

use Tests\Feature\Ui\Concerns\InspectsUiMarkup;
use Tests\TestCase;

/**
 * <x-ui.stepper> na horizontal (o antigo <x-ui.steps>): etapas de um assistente numa lista
 * ordenada, com a atual em aria-current="step", as anteriores concluídas e o "Etapa N de T" só para
 * leitor de tela.
 */
class StepsTest extends TestCase
{
    use InspectsUiMarkup;

    public function test_steps_mark_the_current_one_and_the_completed_ones(): void
    {
        $xpath = $this->renderUi(
            '<x-ui.stepper label="Etapas: Adicionar veículo" :current="2" :steps="$steps" />',
            ['steps' => ['Documento', 'Conferir', ['label' => 'Capas', 'description' => 'opcional']]],
        );

        $nav = $this->uiElement($xpath, '//nav');
        $this->assertSame('Etapas: Adicionar veículo', $nav->getAttribute('aria-label'));
        $this->assertSame('steps', $nav->getAttribute('data-slot'));
        $this->assertSame(3, $this->uiCount($xpath, '//nav/ol/li'));

        $current = $xpath->query('//li[@aria-current="step"]');
        $this->assertSame(1, $current->length, 'Só uma etapa é a atual.');
        $this->assertSame('current', $current->item(0)->getAttribute('data-state'));
        $this->assertStringContainsString('Etapa 2 de 3: Conferir', $this->uiText($current->item(0)));

        $complete = $this->uiElement($xpath, '//li[@data-state="complete"]');
        $this->assertStringContainsString('Etapa 1 de 3: Documento (concluída)', $this->uiText($complete));
        $this->assertSame(1, $this->uiCount($xpath, '//li[@data-state="complete"]//svg[@aria-hidden="true"]'), 'A concluída troca o número pelo check.');

        $upcoming = $this->uiElement($xpath, '//li[@data-state="upcoming"]');
        $this->assertStringContainsString('Etapa 3 de 3: Capas', $this->uiText($upcoming));
        $this->assertStringContainsString('opcional', $this->uiText($upcoming));
        $this->assertStringNotContainsString('concluída', $this->uiText($upcoming));

        foreach ($xpath->query('//span[@data-slot="steps-marker"]') as $marker) {
            $this->assertSame('true', $marker->getAttribute('aria-hidden'), 'O número visível é decorativo.');
        }
    }

    public function test_small_screens_show_one_summary_line_instead_of_every_label(): void
    {
        $xpath = $this->renderUi('<x-ui.stepper :current="2" :steps="[\'Documento\', \'Conferir\', \'Capas\', \'Análise da equipe\']" />');

        $labels = $xpath->query('//span[@data-slot="steps-label"]');
        $this->assertSame(4, $labels->length);

        foreach ($labels as $label) {
            $this->assertContains('max-sm:sr-only', $this->uiClasses($label), 'No celular o rótulo fica só para leitor de tela.');
        }

        $this->assertHasClasses(['font-semibold', 'text-foreground'], $labels->item(1));

        $summary = $this->uiElement($xpath, '//p[@data-slot="steps-summary"]');
        $this->assertSame('Etapa 2 de 4 · Conferir', $this->uiText($summary));
        $this->assertSame('true', $summary->getAttribute('aria-hidden'), 'A lista já diz a etapa atual ao leitor de tela.');
        $this->assertHasClasses(['sm:hidden'], $summary);
        $this->assertSame('Etapas', $this->uiElement($xpath, '//nav')->getAttribute('aria-label'));
    }

    public function test_current_beyond_the_total_completes_every_step(): void
    {
        $xpath = $this->renderUi('<x-ui.stepper :current="9" :steps="[\'Documento\', \'Conferir\']" />');

        $this->assertSame(0, $this->uiCount($xpath, '//li[@aria-current]'));
        $this->assertSame(2, $this->uiCount($xpath, '//li[@data-state="complete"]'));
        $this->assertSame(0, $this->uiCount($xpath, '//p[@data-slot="steps-summary"]'));
    }

    public function test_steps_are_not_links(): void
    {
        $xpath = $this->renderUi('<x-ui.stepper :steps="[\'Documento\', \'Conferir\']" />');

        $this->assertSame(0, $this->uiCount($xpath, '//a'));
        $this->assertSame('current', $this->uiElement($xpath, '//li[1]')->getAttribute('data-state'), 'Sem current, a primeira é a atual.');
    }

    public function test_steps_need_at_least_one_label(): void
    {
        $this->assertUiRejects('<x-ui.stepper :steps="[]" />', 'x-ui.stepper precisa de steps');
        $this->assertUiRejects('<x-ui.stepper :steps="[\'\']" />', 'x-ui.stepper precisa de steps');
    }
}
