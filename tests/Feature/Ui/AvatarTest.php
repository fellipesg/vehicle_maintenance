<?php

namespace Tests\Feature\Ui;

use Tests\Feature\Ui\Concerns\InspectsUiMarkup;
use Tests\TestCase;

/**
 * <x-ui.avatar>: iniciais do nome, imagem sem corte por cima e papel acessível opcional.
 */
class AvatarTest extends TestCase
{
    use InspectsUiMarkup;

    public function test_initials_come_from_the_first_and_last_meaningful_words(): void
    {
        $cases = [
            'Oficina do Zé' => 'OZ',
            'João da Silva Santos' => 'JS',
            'lucas' => 'L',
            'Auto Center São José' => 'AJ',
            '  Élio   e   Ângela ' => 'ÉÂ',
            'R&M Motors' => 'RM',
        ];

        foreach ($cases as $name => $initials) {
            $xpath = $this->renderUi('<x-ui.avatar :name="$name" />', ['name' => $name]);

            $this->assertSame($initials, $this->uiText($this->uiElement($xpath, '//span[@data-slot="avatar-initials"]')), "Iniciais de \"{$name}\".");
        }
    }

    public function test_name_without_letters_falls_back_to_a_person_icon(): void
    {
        $xpath = $this->renderUi('<x-ui.avatar name="---" />');

        $this->assertSame(0, $this->uiCount($xpath, '//span[@data-slot="avatar-initials"]'));
        $this->assertSame(1, $this->uiCount($xpath, '//span[@data-slot="avatar"]/svg'));
        $this->assertUiRejects('<x-ui.avatar name="" />', 'x-ui.avatar precisa de name.');
    }

    public function test_decorative_by_default_and_named_image_on_request(): void
    {
        $decorative = $this->uiElement($this->renderUi('<x-ui.avatar name="Lucas Dantas" />'), '//span[@data-slot="avatar"]');
        $this->assertSame('true', $decorative->getAttribute('aria-hidden'));
        $this->assertFalse($decorative->hasAttribute('role'));
        $this->assertHasClasses(['bg-accent', 'text-accent-foreground', 'rounded-full', 'size-10', 'text-sm'], $decorative);

        $named = $this->uiElement($this->renderUi('<x-ui.avatar name="Oficina do Zé" :decorative="false" />'), '//span[@data-slot="avatar"]');
        $this->assertSame('img', $named->getAttribute('role'));
        $this->assertSame('Oficina do Zé', $named->getAttribute('aria-label'));
        $this->assertFalse($named->hasAttribute('aria-hidden'));
    }

    public function test_image_sits_over_the_initials_without_cropping(): void
    {
        $xpath = $this->renderUi('<x-ui.avatar name="Oficina do Zé" src="https://cdn.example.test/logo.png" shape="square" size="xl" />');
        $avatar = $this->uiElement($xpath, '//span[@data-slot="avatar"]');
        $image = $this->uiElement($xpath, '//img[@data-slot="avatar-image"]');

        $this->assertSame('https://cdn.example.test/logo.png', $image->getAttribute('src'));
        $this->assertSame('', $image->getAttribute('alt'));
        $this->assertSame('lazy', $image->getAttribute('loading'));
        $this->assertHasClasses(['object-contain', 'absolute', 'inset-0', 'rounded-control'], $image);
        $this->assertLacksClasses(['object-cover'], $image);
        $this->assertHasClasses(['bg-white', 'size-16', 'rounded-control'], $avatar);
        $this->assertLacksClasses(['bg-accent'], $avatar);
        $this->assertSame(1, $this->uiCount($xpath, '//span[@data-slot="avatar-initials"]'), 'As iniciais ficam por baixo, para quando a imagem não carregar.');
    }

    public function test_sizes(): void
    {
        $expectations = ['xs' => 'size-6', 'sm' => 'size-8', 'lg' => 'size-12'];

        foreach ($expectations as $size => $class) {
            $avatar = $this->uiElement($this->renderUi('<x-ui.avatar name="Ana" :size="$size" />', ['size' => $size]), '//span[@data-slot="avatar"]');

            $this->assertHasClasses([$class], $avatar);
        }
    }
}
