<?php

namespace Tests\Feature\Ui;

use Tests\Feature\Ui\Concerns\InspectsRenderedComponents;
use Tests\TestCase;

/**
 * <x-ui.checkbox>: rótulo clicável com alvo de 40px, descrição, estado marcado com old input,
 * valor de desmarcado e erro (próprio ou do grupo).
 */
class CheckboxTest extends TestCase
{
    use InspectsRenderedComponents;

    public function test_label_wraps_the_box_with_a_forty_pixel_target(): void
    {
        $document = $this->renderComponent('<x-ui.checkbox name="remember" label="Lembrar de mim neste aparelho" class="mt-2" data-track="login" />');

        $label = $this->element($document, 'label');
        $input = $this->element($document, 'input[type="checkbox"]');

        $this->assertSame('remember', $label->getAttribute('for'));
        $this->assertSame($label, $input->parentElement);
        $this->assertSame('remember', $input->getAttribute('id'));
        $this->assertSame('1', $input->getAttribute('value'));
        $this->assertFalse($input->hasAttribute('checked'));
        $this->assertSame('login', $input->getAttribute('data-track'));
        $this->assertContains('min-h-10', $this->classesOf($label));
        $this->assertContains('cursor-pointer', $this->classesOf($label));
        $this->assertContains('mt-2', $this->classesOf($this->element($document, '[data-slot="checkbox"]')));
        $this->assertStringContainsString('Lembrar de mim neste aparelho', $label->textContent);
    }

    public function test_checked_box_uses_an_accessible_fill_and_visible_focus(): void
    {
        $classes = $this->classesOf($this->element($this->renderComponent('<x-ui.checkbox name="a" label="A" checked />'), 'input'));

        $this->assertContains('text-accent-foreground', $classes, 'Marcado em wrench-800 (6,31:1), nunca wrench-500/600.');
        $this->assertContains('not-checked:bg-surface', $classes);
        $this->assertContains('focus-visible:outline-ring', $classes);
        $this->assertContains('focus:ring-0', $classes, 'Sem o anel azul do plugin de formulários.');
        $this->assertContains('aria-invalid:border-danger', $classes);
    }

    public function test_description_is_linked_and_slot_label_accepts_links(): void
    {
        $document = $this->renderComponent('<x-ui.checkbox name="terms_accepted" description="Você pode mudar isso depois.">Li e aceito os <a href="/termos">termos de uso</a></x-ui.checkbox>');

        $input = $this->element($document, 'input');

        $this->assertSame(['terms_accepted-description'], $this->describedByOf($input));
        $this->assertSame('Você pode mudar isso depois.', trim($this->element($document, '#terms_accepted-description')->textContent));
        $this->assertSame('/termos', $this->element($document, 'label a')->getAttribute('href'));
    }

    public function test_checked_prop_on_first_visit_and_user_choice_after_a_failed_submission(): void
    {
        $this->assertTrue($this->element($this->renderComponent('<x-ui.checkbox name="notify" label="Avisar" checked />'), 'input')->hasAttribute('checked'));

        $this->withOldInput(['email' => 'ana@example.com']);

        $this->assertFalse(
            $this->element($this->renderComponent('<x-ui.checkbox name="notify" label="Avisar" checked />'), 'input')->hasAttribute('checked'),
            'Desmarcado pelo usuário no envio que voltou com erro.',
        );
    }

    public function test_unchecked_value_goes_in_a_hidden_input_before_the_box(): void
    {
        $document = $this->renderComponent('<x-ui.checkbox name="is_public" label="Público" unchecked-value="0" />');

        $hidden = $this->element($document, 'input[type="hidden"]');

        $this->assertSame('is_public', $hidden->getAttribute('name'));
        $this->assertSame('0', $hidden->getAttribute('value'));
        $this->assertNotNull($document->querySelector('input[type="hidden"] + label input[type="checkbox"]'));
    }

    public function test_own_error_is_drawn_below_the_box(): void
    {
        $this->withViewErrors(['terms_accepted' => ['Aceite os termos para continuar.']]);

        $document = $this->renderComponent('<x-ui.checkbox name="terms_accepted" label="Aceito os termos" />');

        $input = $this->element($document, 'input');

        $this->assertSame('true', $input->getAttribute('aria-invalid'));
        $this->assertSame(['terms_accepted-error'], $this->describedByOf($input));
        $this->assertStringContainsString('Erro: Aceite os termos para continuar.', $this->element($document, '#terms_accepted-error')->textContent);
        $this->assertNull($this->renderComponent('<x-ui.checkbox name="terms_accepted" label="Aceito" :error="false" />')->querySelector('#terms_accepted-error'));
    }

    public function test_list_boxes_point_to_the_group_error_and_get_value_ids(): void
    {
        $this->withViewErrors(['services' => ['Marque pelo menos um serviço.']]);

        $document = $this->renderComponent(
            '<x-ui.fieldset name="services[]" legend="Serviços" :selected="[\'filters\']">'
            .'<x-ui.checkbox value="oil" label="Troca de óleo" />'
            .'<x-ui.checkbox value="filters" label="Filtros" />'
            .'</x-ui.fieldset>',
        );

        $oil = $this->element($document, '#services_oil');
        $filters = $this->element($document, '#services_filters');

        $this->assertSame('services[]', $oil->getAttribute('name'));
        $this->assertFalse($oil->hasAttribute('checked'));
        $this->assertTrue($filters->hasAttribute('checked'), 'selected do fieldset chega por @aware.');
        $this->assertSame(['services-error'], $this->describedByOf($oil));
        $this->assertSame('true', $oil->getAttribute('aria-invalid'));
        $this->assertCount(1, $document->querySelectorAll('[id$="-error"]'), 'O erro do grupo aparece uma vez, no fieldset.');
        $this->assertNotNull($document->querySelector('fieldset > #services-error'));
    }

    public function test_list_boxes_keep_the_user_choice_after_a_failed_submission(): void
    {
        $this->withOldInput(['services' => ['oil']]);

        $document = $this->renderComponent('<x-ui.checkbox name="services[]" value="oil" label="Óleo" /><x-ui.checkbox name="services[]" value="filters" label="Filtros" checked />');

        $this->assertTrue($this->element($document, '#services_oil')->hasAttribute('checked'));
        $this->assertFalse($this->element($document, '#services_filters')->hasAttribute('checked'));
    }
}
