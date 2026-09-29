<?php

namespace Tests\Feature\Ui;

use Illuminate\Support\Facades\Exceptions;
use InvalidArgumentException;
use Tests\Feature\Ui\Concerns\InspectsUiMarkup;
use Tests\TestCase;

/**
 * <x-ui.button>: variantes pelos tokens, <a> com href, loading acessível e foco visível.
 */
class ButtonTest extends TestCase
{
    use InspectsUiMarkup;

    public function test_default_is_a_primary_md_button_that_does_not_submit_by_accident(): void
    {
        $button = $this->uiElement($this->renderUi('<x-ui.button>Salvar manutenção</x-ui.button>'), '//button');

        $this->assertSame('button', $button->getAttribute('type'));
        $this->assertSame('button', $button->getAttribute('data-slot'));
        $this->assertSame('primary', $button->getAttribute('data-variant'));
        $this->assertFalse($button->hasAttribute('disabled'));
        $this->assertFalse($button->hasAttribute('aria-busy'));
        $this->assertSame('Salvar manutenção', $this->uiText($button));
        $this->assertHasClasses([
            'bg-primary', 'text-primary-foreground', 'hover:bg-primary-hover', 'min-h-10', 'px-4', 'text-sm',
            'rounded-control', 'focus-visible:outline-2', 'focus-visible:outline-offset-2', 'focus-visible:outline-ring',
            'duration-fast', 'ease-smooth-out', 'motion-reduce:transition-none', 'motion-safe:active:scale-[.98]',
        ], $button);
    }

    public function test_variants_use_semantic_tokens(): void
    {
        $expectations = [
            'secondary' => ['border-border-strong', 'bg-surface', 'text-foreground', 'hover:bg-surface-muted'],
            'ghost' => ['text-foreground', 'hover:bg-surface-muted'],
            'danger' => ['bg-danger', 'text-danger-foreground', 'hover:bg-danger-hover'],
            'link' => ['text-link', 'hover:text-link-hover', 'hover:underline', 'px-0'],
        ];

        foreach ($expectations as $variant => $classes) {
            $button = $this->uiElement($this->renderUi('<x-ui.button :variant="$variant">Ação</x-ui.button>', ['variant' => $variant]), '//button');

            $this->assertSame($variant, $button->getAttribute('data-variant'));
            $this->assertHasClasses($classes, $button);
            $this->assertLacksClasses(['bg-primary'], $button);
        }
    }

    public function test_sizes_keep_a_40px_target_on_small_screens(): void
    {
        $small = $this->uiElement($this->renderUi('<x-ui.button size="sm">Filtrar</x-ui.button>'), '//button');
        $large = $this->uiElement($this->renderUi('<x-ui.button size="lg">Começar</x-ui.button>'), '//button');

        $this->assertHasClasses(['min-h-8', 'max-sm:min-h-10', 'px-3', 'text-xs'], $small);
        $this->assertHasClasses(['min-h-11', 'px-5', 'text-base'], $large);
    }

    public function test_href_renders_a_link_without_button_type(): void
    {
        $link = $this->uiElement(
            $this->renderUi('<x-ui.button :href="$url" variant="secondary">Ver veículo</x-ui.button>', ['url' => '/usuario/veiculos/1?aba=historico&x=1']),
            '//a',
        );

        $this->assertSame('/usuario/veiculos/1?aba=historico&x=1', $link->getAttribute('href'));
        $this->assertFalse($link->hasAttribute('type'));
        $this->assertFalse($link->hasAttribute('role'));
        $this->assertFalse($link->hasAttribute('download'));
        $this->assertSame('Ver veículo', $this->uiText($link));
    }

    public function test_disabled_link_loses_its_href_and_is_announced_as_disabled(): void
    {
        $link = $this->uiElement($this->renderUi('<x-ui.button href="/exportar" disabled>Exportar PDF</x-ui.button>'), '//a');

        $this->assertFalse($link->hasAttribute('href'));
        $this->assertSame('link', $link->getAttribute('role'));
        $this->assertSame('true', $link->getAttribute('aria-disabled'));
        $this->assertHasClasses(['aria-disabled:pointer-events-none', 'aria-disabled:opacity-60'], $link);
    }

    public function test_disabled_button_uses_the_native_attribute(): void
    {
        $button = $this->uiElement($this->renderUi('<x-ui.button type="submit" :disabled="true">Enviar</x-ui.button>'), '//button');

        $this->assertSame('submit', $button->getAttribute('type'));
        $this->assertTrue($button->hasAttribute('disabled'));
        $this->assertHasClasses(['disabled:opacity-60', 'disabled:pointer-events-none'], $button);
    }

    public function test_loading_shows_a_spinner_marks_busy_and_disables(): void
    {
        $xpath = $this->renderUi('<x-ui.button type="submit" icon="plus" loading loading-label="Salvando…">Salvar</x-ui.button>');
        $button = $this->uiElement($xpath, '//button');

        $this->assertSame('true', $button->getAttribute('aria-busy'));
        $this->assertTrue($button->hasAttribute('disabled'));
        $this->assertSame('Salvando…', $button->getAttribute('data-loading-label'));
        $this->assertSame('Salvando…', $this->uiText($this->uiElement($xpath, '//button/span[@data-slot="label"]')));

        $spinner = $this->uiElement($xpath, '//button/span[@data-slot="spinner"]');
        $this->assertSame('true', $spinner->getAttribute('aria-hidden'), 'O botão já anuncia o estado com aria-busy.');
        $this->assertSame(1, $this->uiCount($xpath, '//button//svg[contains(@class, "motion-safe:animate-spin")]'));
        $this->assertSame(0, $this->uiCount($xpath, '//button//svg[@data-slot="icon"]'), 'O spinner substitui o ícone de início.');
    }

    public function test_loading_without_label_keeps_the_original_text(): void
    {
        $xpath = $this->renderUi('<x-ui.button loading>Enviar fotos</x-ui.button>');

        $this->assertSame('Enviar fotos', $this->uiText($this->uiElement($xpath, '//button/span[@data-slot="label"]')));
        $this->assertFalse($this->uiElement($xpath, '//button')->hasAttribute('data-loading-label'));
    }

    public function test_leading_and_trailing_icons_are_decorative_and_sized_by_the_button(): void
    {
        $xpath = $this->renderUi('<x-ui.button icon="plus" icon-trailing="chevron-right" size="sm">Adicionar</x-ui.button>');
        $icons = $xpath->query('//button/svg[@data-slot="icon"]');

        $this->assertSame(2, $icons->length);

        foreach ($icons as $icon) {
            $this->assertSame('true', $icon->getAttribute('aria-hidden'));
            $this->assertHasClasses(['size-4'], $icon);
        }
    }

    public function test_full_width_and_extra_attributes_are_merged(): void
    {
        $button = $this->uiElement(
            $this->renderUi('<x-ui.button full class="mt-4" name="acao" value="publicar" form="form-post" data-testid="publicar">Publicar</x-ui.button>'),
            '//button',
        );

        $this->assertHasClasses(['w-full', 'mt-4', 'bg-primary'], $button);
        $this->assertSame('acao', $button->getAttribute('name'));
        $this->assertSame('publicar', $button->getAttribute('value'));
        $this->assertSame('form-post', $button->getAttribute('form'));
        $this->assertSame('publicar', $button->getAttribute('data-testid'));
    }

    public function test_unknown_variant_is_rejected_in_testing(): void
    {
        $this->assertUiRejects('<x-ui.button variant="primario">Salvar</x-ui.button>', 'x-ui.button: variant "primario" não existe. Use primary, secondary, ghost, danger, link.');
        $this->assertUiRejects('<x-ui.button size="xl">Salvar</x-ui.button>', 'x-ui.button: size "xl" não existe.');
        $this->assertUiRejects('<x-ui.button type="enviar">Salvar</x-ui.button>', 'x-ui.button: type "enviar" não existe.');
    }

    public function test_unknown_variant_falls_back_to_primary_in_production(): void
    {
        Exceptions::fake();
        $this->app->detectEnvironment(fn (): string => 'production');

        $button = $this->uiElement($this->renderUi('<x-ui.button variant="primario">Salvar</x-ui.button>'), '//button');

        $this->assertSame('primary', $button->getAttribute('data-variant'));
        Exceptions::assertReported(fn (InvalidArgumentException $exception): bool => str_contains($exception->getMessage(), 'variant "primario"'));
    }
}
