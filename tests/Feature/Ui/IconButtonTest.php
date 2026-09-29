<?php

namespace Tests\Feature\Ui;

use Tests\Feature\Ui\Concerns\InspectsUiMarkup;
use Tests\TestCase;

/**
 * <x-ui.icon-button>: nome acessível obrigatório e alvo mínimo de 40px.
 */
class IconButtonTest extends TestCase
{
    use InspectsUiMarkup;

    public function test_label_becomes_the_accessible_name_and_the_icon_is_decorative(): void
    {
        $xpath = $this->renderUi('<x-ui.icon-button icon="x-mark" label="Fechar aviso" />');
        $button = $this->uiElement($xpath, '//button');

        $this->assertSame('Fechar aviso', $button->getAttribute('aria-label'));
        $this->assertSame('button', $button->getAttribute('type'));
        $this->assertSame('icon-button', $button->getAttribute('data-slot'));
        $this->assertSame('true', $this->uiElement($xpath, '//button/svg')->getAttribute('aria-hidden'));
        $this->assertHasClasses(['size-10', 'text-muted-foreground', 'hover:bg-surface-muted', 'focus-visible:outline-ring', 'motion-reduce:transition-none'], $button);
        $this->assertHasClasses(['size-5'], $this->uiElement($xpath, '//button/svg'));
    }

    public function test_label_and_icon_are_required(): void
    {
        $this->assertUiRejects('<x-ui.icon-button icon="bell" />', 'x-ui.icon-button precisa de label.');
        $this->assertUiRejects('<x-ui.icon-button icon="bell" label="  " />', 'x-ui.icon-button precisa de label.');
        $this->assertUiRejects('<x-ui.icon-button label="Notificações" />', 'x-ui.icon-button precisa de icon.');
    }

    public function test_every_size_reaches_a_40px_target(): void
    {
        $small = $this->uiElement($this->renderUi('<x-ui.icon-button icon="trash" label="Remover item" size="sm" />'), '//button');
        $large = $this->uiElement($this->renderUi('<x-ui.icon-button icon="bars-3" label="Abrir menu" size="lg" />'), '//button');

        $this->assertHasClasses(['size-8', 'relative', 'after:absolute', 'after:-inset-1'], $small);
        $this->assertHasClasses(['size-11'], $large);
    }

    public function test_toggle_and_menu_states_are_exposed(): void
    {
        $pressed = $this->uiElement($this->renderUi('<x-ui.icon-button icon="eye" label="Mostrar senha" :pressed="false" />'), '//button');
        $menu = $this->uiElement($this->renderUi('<x-ui.icon-button icon="bars-3" label="Abrir menu" :expanded="true" aria-controls="menu-principal" />'), '//button');
        $plain = $this->uiElement($this->renderUi('<x-ui.icon-button icon="bars-3" label="Abrir menu" />'), '//button');

        $this->assertSame('false', $pressed->getAttribute('aria-pressed'));
        $this->assertSame('true', $menu->getAttribute('aria-expanded'));
        $this->assertSame('menu-principal', $menu->getAttribute('aria-controls'));
        $this->assertFalse($plain->hasAttribute('aria-pressed'));
        $this->assertFalse($plain->hasAttribute('aria-expanded'));
    }

    public function test_count_is_visual_only_and_capped(): void
    {
        $xpath = $this->renderUi('<x-ui.icon-button icon="bell" label="Notificações, 3 não lidas" :count="3" />');
        $count = $this->uiElement($xpath, '//span[@data-slot="icon-button-count"]');

        $this->assertSame('3', trim($count->textContent));
        $this->assertSame('true', $count->getAttribute('aria-hidden'));
        $this->assertHasClasses(['bg-primary', 'text-primary-foreground', 'tabular-nums', 'text-xs'], $count);

        $this->assertSame('99+', trim($this->uiElement($this->renderUi('<x-ui.icon-button icon="bell" label="Notificações, 120 não lidas" :count="120" />'), '//span[@data-slot="icon-button-count"]')->textContent));
        $this->assertSame(0, $this->uiCount($this->renderUi('<x-ui.icon-button icon="bell" label="Notificações" :count="0" />'), '//span[@data-slot="icon-button-count"]'));
    }

    public function test_variants_and_links(): void
    {
        $danger = $this->uiElement($this->renderUi('<x-ui.icon-button icon="trash" label="Excluir foto" variant="danger" />'), '//button');
        $link = $this->uiElement($this->renderUi('<x-ui.icon-button icon="cog-6-tooth" label="Configurações" href="/conta" variant="secondary" />'), '//a');
        $disabledLink = $this->uiElement($this->renderUi('<x-ui.icon-button icon="cog-6-tooth" label="Configurações" href="/conta" disabled />'), '//a');

        $this->assertHasClasses(['text-danger', 'hover:bg-danger-soft'], $danger);
        $this->assertSame('/conta', $link->getAttribute('href'));
        $this->assertFalse($link->hasAttribute('type'));
        $this->assertHasClasses(['border-border-strong', 'bg-surface'], $link);
        $this->assertFalse($disabledLink->hasAttribute('href'));
        $this->assertSame('true', $disabledLink->getAttribute('aria-disabled'));
    }
}
