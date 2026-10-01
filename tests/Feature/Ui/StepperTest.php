<?php

namespace Tests\Feature\Ui;

use Tests\Feature\Ui\Concerns\InspectsUiMarkup;
use Tests\TestCase;

/**
 * <x-ui.stepper>: as etapas do assistente viraram componente próprio, com orientação vertical,
 * volta por link nas etapas concluídas e o trilho da etapa atual preenchido só com movimento
 * permitido. O nome antigo <x-ui.steps> saiu: o assistente usa <x-ui.stepper>.
 */
class StepperTest extends TestCase
{
    use InspectsUiMarkup;

    public function test_horizontal_stepper_marks_current_and_completed_steps(): void
    {
        $xpath = $this->renderUi('<x-ui.stepper label="Etapas: Adicionar veículo" :current="2" :steps="[\'Documento\', \'Conferir\', \'Capas\']" />');

        $nav = $this->uiElement($xpath, '//nav');
        $this->assertSame('Etapas: Adicionar veículo', $nav->getAttribute('aria-label'));
        $this->assertSame('horizontal', $nav->getAttribute('data-orientation'));
        $this->assertSame(3, $this->uiCount($xpath, '//nav/ol/li'));
        $this->assertSame(1, $this->uiCount($xpath, '//li[@aria-current="step"]'));
        $this->assertStringContainsString('Etapa 1 de 3: Documento (concluída)', $this->uiText($this->uiElement($xpath, '//li[@data-state="complete"]')));
        $this->assertSame('Etapa 2 de 3 · Conferir', $this->uiText($this->uiElement($xpath, '//p[@data-slot="steps-summary"]')));
    }

    public function test_only_the_track_that_reaches_the_current_step_animates_and_respects_reduced_motion(): void
    {
        $xpath = $this->renderUi('<x-ui.stepper :current="3" :steps="[\'Documento\', \'Conferir\', \'Capas\', \'Análise\']" />');

        $fills = $xpath->query('//span[@data-slot="steps-connector"]/span');
        $this->assertSame(2, $fills->length, 'Só os trilhos das etapas concluídas têm preenchimento.');

        $current = $this->uiElement($xpath, '//span[@data-slot="steps-connector-fill-current"]');
        $this->assertHasClasses(['origin-left', 'bg-primary', 'transition-[scale]', 'duration-slow', 'ease-smooth-out', 'motion-reduce:transition-none', 'starting:scale-x-0'], $current);
        $this->assertSame('true', $this->uiElement($xpath, '//span[@data-slot="steps-connector-fill-current"]/..')->getAttribute('aria-hidden'));

        $previous = $this->uiElement($xpath, '//span[@data-slot="steps-connector-fill"]');
        $this->assertLacksClasses(['starting:scale-x-0', 'transition-[scale]'], $previous);
    }

    public function test_completed_step_with_href_links_back_and_the_others_do_not(): void
    {
        $xpath = $this->renderUi('<x-ui.stepper :current="2" :steps="$steps" />', ['steps' => [
            ['label' => 'Documento', 'href' => 'https://revisalog.test/veiculos/novo'],
            ['label' => 'Conferir', 'href' => 'https://revisalog.test/veiculos/conferir'],
            ['label' => 'Capas', 'href' => 'https://revisalog.test/veiculos/capas'],
        ]]);

        $links = $xpath->query('//a');
        $this->assertSame(1, $links->length, 'Só a etapa concluída vira link: a atual e as próximas não.');
        $this->assertSame('https://revisalog.test/veiculos/novo', $links->item(0)->getAttribute('href'));
        $this->assertStringContainsString('Etapa 1 de 3: Documento (concluída)', $this->uiText($links->item(0)));
        $this->assertHasClasses(['focus-visible:outline-2', 'focus-visible:outline-ring'], $links->item(0));
        $this->assertSame(1, $this->uiCount($xpath, '//a//span[@data-slot="steps-marker"]'), 'O número fica dentro do link: no celular o rótulo é só para leitor de tela.');
    }

    public function test_vertical_stepper_keeps_labels_visible_and_has_no_summary_line(): void
    {
        $xpath = $this->renderUi('<x-ui.stepper orientation="vertical" label="Andamento da procuração" :current="2" :steps="$steps" />', ['steps' => [
            'Envio',
            ['label' => 'Análise da equipe', 'description' => 'Em até 2 dias úteis'],
            'Histórico liberado',
        ]]);

        $this->assertSame('vertical', $this->uiElement($xpath, '//nav')->getAttribute('data-orientation'));
        $this->assertHasClasses(['grid'], $this->uiElement($xpath, '//nav/ol'));

        foreach ($xpath->query('//span[@data-slot="steps-label"]') as $label) {
            $this->assertNotContains('max-sm:sr-only', $this->uiClasses($label), 'Na vertical o rótulo fica visível no celular.');
        }

        $this->assertSame(0, $this->uiCount($xpath, '//p[@data-slot="steps-summary"]'));
        $this->assertStringContainsString('Em até 2 dias úteis', $this->uiText($this->uiElement($xpath, '//li[@aria-current="step"]')));
        $this->assertHasClasses(['origin-top', 'starting:scale-y-0', 'motion-reduce:transition-none'], $this->uiElement($xpath, '//span[@data-slot="steps-connector-fill-current"]'));
        $this->assertSame(2, $this->uiCount($xpath, '//span[@data-slot="steps-connector"]'), 'A última etapa não tem trilho.');
    }

    public function test_the_old_steps_alias_is_gone_and_the_wizard_uses_the_stepper(): void
    {
        $this->assertFileDoesNotExist(resource_path('views/components/ui/steps.blade.php'));
        $this->assertStringContainsString('<x-ui.stepper', file_get_contents(resource_path('views/vehicles/entry/layout.blade.php')));

        $xpath = $this->renderUi('<x-ui.stepper label="Etapas" :current="1" :steps="[\'Documento\', \'Conferir\']" class="mt-2" />');

        $nav = $this->uiElement($xpath, '//nav[@data-slot="steps"]');
        $this->assertSame('horizontal', $nav->getAttribute('data-orientation'));
        $this->assertHasClasses(['mb-6', 'mt-2'], $nav);
    }

    public function test_invalid_props_are_rejected(): void
    {
        $this->assertUiRejects('<x-ui.stepper :steps="[]" />', 'x-ui.stepper precisa de steps');
        $this->assertUiRejects('<x-ui.stepper orientation="diagonal" :steps="[\'Documento\']" />', 'x-ui.stepper: orientation "diagonal" não existe');
    }
}
