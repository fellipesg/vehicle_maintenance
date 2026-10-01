<?php

namespace Tests\Feature\Ui;

use Tests\Feature\Ui\Concerns\InspectsUiMarkup;
use Tests\TestCase;

/**
 * <x-ui.container>: largura máxima por tipo de página e recuo lateral padrão.
 */
class ContainerTest extends TestCase
{
    use InspectsUiMarkup;

    public function test_default_is_the_wide_container_with_standard_gutters(): void
    {
        $container = $this->uiElement($this->renderUi('<x-ui.container>Conteúdo</x-ui.container>'), '//div[@data-slot="container"]');

        $this->assertHasClasses(['mx-auto', 'w-full', 'px-4', 'sm:px-6', 'max-w-7xl'], $container);
        $this->assertLacksClasses(['py-6'], $container);
        $this->assertSame('Conteúdo', $this->uiText($container));
    }

    public function test_sizes_element_and_vertical_padding(): void
    {
        $expectations = ['sm' => 'max-w-2xl', 'md' => 'max-w-3xl', 'lg' => 'max-w-5xl', 'xl' => 'max-w-7xl'];

        foreach ($expectations as $size => $class) {
            $container = $this->uiElement($this->renderUi('<x-ui.container :size="$size">x</x-ui.container>', ['size' => $size]), '//div[@data-slot="container"]');

            $this->assertHasClasses([$class], $container);
        }

        $main = $this->uiElement($this->renderUi('<x-ui.container as="section" size="sm" padded class="space-y-6">x</x-ui.container>'), '//section[@data-slot="container"]');
        $this->assertHasClasses(['max-w-2xl', 'py-6', 'sm:py-8', 'space-y-6'], $main);

        $this->assertUiRejects('<x-ui.container size="2xl">x</x-ui.container>', 'x-ui.container: size "2xl" não existe. Use sm, md, lg, xl.');
    }
}
