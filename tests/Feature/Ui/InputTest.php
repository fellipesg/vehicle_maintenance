<?php

namespace Tests\Feature\Ui;

use Tests\Feature\Ui\Concerns\InspectsRenderedComponents;
use Tests\TestCase;

/**
 * <x-ui.input>: name, id, value e old(), estado inválido, ícones dentro da borda e o botão
 * "Mostrar senha" do type=password.
 */
class InputTest extends TestCase
{
    use InspectsRenderedComponents;

    public function test_standalone_input_gets_id_from_name_and_token_classes(): void
    {
        $document = $this->renderComponent('<x-ui.input name="email" type="email" autocomplete="email" placeholder="voce@exemplo.com.br" class="max-w-sm" />');

        $input = $this->element($document, 'input');
        $classes = $this->classesOf($input);

        $this->assertSame('email', $input->getAttribute('type'));
        $this->assertSame('email', $input->getAttribute('name'));
        $this->assertSame('email', $input->getAttribute('id'));
        $this->assertSame('email', $input->getAttribute('autocomplete'));
        $this->assertSame('voce@exemplo.com.br', $input->getAttribute('placeholder'));
        $this->assertSame('control', $input->getAttribute('data-slot'));
        $this->assertFalse($input->hasAttribute('value'));

        foreach (['form-input', 'h-10', 'text-base', 'sm:text-sm', 'aria-invalid:border-danger', 'motion-reduce:transition-none', 'max-w-sm'] as $class) {
            $this->assertContains($class, $classes);
        }

        $this->assertDoesNotMatchRegularExpression('/(?:^|\s)[a-z:-]*(?:red|green|blue|gray|zinc|automotive|wrench)-\d/', $input->getAttribute('class'), 'Só tokens semânticos.');
    }

    public function test_value_is_escaped_and_old_input_wins_after_a_failed_submission(): void
    {
        $first = $this->renderComponent('<x-ui.input name="model" :value="$model" />', ['model' => 'Onix "LT" <1.0>']);

        $this->assertSame('Onix "LT" <1.0>', $this->element($first, 'input')->getAttribute('value'));
        $this->assertStringNotContainsString('<1.0>', (string) $this->blade('<x-ui.input name="model" :value="$model" />', ['model' => 'Onix "LT" <1.0>']));

        $this->withOldInput(['items' => [['price' => '150,00']], 'model' => null]);

        $old = $this->renderComponent('<x-ui.input name="items[0][price]" value="99,90" /><x-ui.input name="model" value="Onix" />');

        $this->assertSame('150,00', $this->element($old, '#items_0_price')->getAttribute('value'));
        $this->assertFalse($this->element($old, '#model')->hasAttribute('value'), 'Campo esvaziado pelo usuário volta vazio.');
    }

    public function test_explicit_id_and_invalid_prop(): void
    {
        $document = $this->renderComponent('<x-ui.input name="email" id="login-email" invalid />');

        $input = $this->element($document, 'input');

        $this->assertSame('login-email', $input->getAttribute('id'));
        $this->assertSame('true', $input->getAttribute('aria-invalid'));
    }

    public function test_error_in_the_bag_marks_the_input_invalid(): void
    {
        $this->withViewErrors(['plate' => ['Placa inválida.']]);

        $this->assertSame('true', $this->element($this->renderComponent('<x-ui.input name="plate" />'), 'input')->getAttribute('aria-invalid'));
        $this->assertFalse($this->element($this->renderComponent('<x-ui.input name="model" />'), 'input')->hasAttribute('aria-invalid'));
    }

    public function test_password_never_comes_back_filled_and_has_a_hidden_toggle_until_js(): void
    {
        $this->withOldInput(['password' => 'segredo123']);

        $document = $this->renderComponent('<x-ui.input name="password" type="password" value="nao-mostrar" autocomplete="current-password" class="mt-2" />');

        $input = $this->element($document, 'input');
        $group = $this->element($document, '[data-slot="input-group"]');
        $toggle = $this->element($document, 'button[data-password-toggle]');

        $this->assertSame('password', $input->getAttribute('type'));
        $this->assertFalse($input->hasAttribute('value'));
        $this->assertSame('button', $toggle->getAttribute('type'));
        $this->assertTrue($toggle->hasAttribute('hidden'), 'Sem JS não há o que alternar.');
        $this->assertSame('password', $toggle->getAttribute('aria-controls'));
        $this->assertSame('false', $toggle->getAttribute('aria-pressed'));
        $this->assertSame('Mostrar senha', $toggle->getAttribute('aria-label'));
        $this->assertFalse($toggle->hasAttribute('data-label-hide'), 'Nome fixo: o estado fica só no aria-pressed.');
        $this->assertFalse($toggle->hasAttribute('data-label-show'));
        $this->assertContains('size-10', $this->classesOf($toggle), 'Alvo de 40px.');
        $this->assertNotNull($toggle->querySelector('svg[data-password-toggle-icon="show"][aria-hidden="true"]'));
        $this->assertTrue($this->element($document, 'svg[data-password-toggle-icon="hide"]')->hasAttribute('hidden'));
        $this->assertContains('mt-2', $this->classesOf($group), 'class vai para a moldura.');
        $this->assertNotContains('mt-2', $this->classesOf($input));
    }

    public function test_password_toggle_can_be_turned_off_and_gets_an_id_without_name(): void
    {
        $plain = $this->renderComponent('<x-ui.input name="pin" type="password" :revealable="false" />');
        $nameless = $this->renderComponent('<x-ui.input type="password" />');

        $this->assertNull($plain->querySelector('[data-password-toggle]'));
        $this->assertNull($plain->querySelector('[data-slot="input-group"]'));

        $input = $this->element($nameless, 'input');

        $this->assertMatchesRegularExpression('/^senha-[a-z0-9]{8}$/', $input->getAttribute('id'));
        $this->assertSame($input->getAttribute('id'), $this->element($nameless, 'button')->getAttribute('aria-controls'));
    }

    public function test_leading_and_trailing_icons_are_decorative_and_inside_the_border(): void
    {
        $document = $this->renderComponent('<x-ui.input name="q" type="search" leading-icon="magnifying-glass" trailing-icon="qr-code" />');

        $group = $this->element($document, '[data-slot="input-group"]');
        $leading = $this->element($document, '[data-align="leading"] svg');
        $trailing = $this->element($document, '[data-align="trailing"] svg');

        $this->assertSame('true', $leading->getAttribute('aria-hidden'));
        $this->assertSame('true', $trailing->getAttribute('aria-hidden'));
        $this->assertSame($group, $this->element($document, 'input')->parentElement);
        $this->assertContains('[&_[data-slot=control]]:pl-2', $this->classesOf($group));
        $this->assertContains('[&_input[data-slot=control]]:pr-2', $this->classesOf($group));
    }

    public function test_input_inside_a_group_drops_its_own_border_through_the_in_variant(): void
    {
        $input = $this->element($this->renderComponent('<x-ui.input name="q" leading-icon="magnifying-glass" />'), 'input');

        foreach (['in-data-[slot=input-group]:border-0', 'in-data-[slot=input-group]:bg-transparent', 'in-data-[slot=input-group]:focus:ring-0', 'in-data-[slot=input-group]:h-9.5'] as $class) {
            $this->assertContains($class, $this->classesOf($input));
        }
    }
}
