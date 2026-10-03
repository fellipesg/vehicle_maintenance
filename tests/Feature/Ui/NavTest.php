<?php

namespace Tests\Feature\Ui;

use DOMXPath;
use Tests\Feature\Ui\Concerns\InspectsUiMarkup;
use Tests\TestCase;

/**
 * <x-ui.nav> e <x-ui.nav-item>: lista de navegação com aria-current e indicador de forma no item
 * ativo, em barra (horizontal) ou menu lateral (vertical, com grupos).
 */
class NavTest extends TestCase
{
    use InspectsUiMarkup;

    public function test_horizontal_items_render_inside_a_labelled_nav(): void
    {
        $xpath = $this->renderNav('<x-ui.nav label="Principal" :items="$items" />');

        $this->assertSame('Principal', $this->uiElement($xpath, '//nav')->getAttribute('aria-label'));
        $this->assertSame('list', $this->uiElement($xpath, '//nav/ul')->getAttribute('role'));
        $this->assertHasClasses(['flex', 'flex-wrap', 'items-center', 'gap-1'], $this->uiElement($xpath, '//nav/ul'));
        $this->assertSame(3, $this->uiCount($xpath, '//nav/ul/li/a'));
        $this->assertSame(['Início', 'Meus veículos', 'Notificações 3 não lidas'], array_map(fn ($link): string => $this->uiText($link), iterator_to_array($xpath->query('//nav/ul/li/a'))));
    }

    public function test_active_item_has_aria_current_and_a_bar_not_only_color(): void
    {
        $xpath = $this->renderNav('<x-ui.nav label="Principal" :items="$items" />');
        $active = $this->uiElement($xpath, '//a[@href="/inicio"]');
        $inactive = $this->uiElement($xpath, '//a[@href="/veiculos"]');

        $this->assertSame('page', $active->getAttribute('aria-current'));
        $this->assertHasClasses(['h-10', 'font-semibold', 'text-foreground', 'after:absolute', 'after:h-0.5', 'after:bg-accent-foreground', 'rounded-control', 'motion-reduce:transition-none'], $active);
        $this->assertFalse($inactive->hasAttribute('aria-current'));
        $this->assertHasClasses(['text-muted-foreground', 'hover:bg-surface-muted', 'hover:text-foreground'], $inactive);
        $this->assertLacksClasses(['after:h-0.5', 'font-semibold'], $inactive);
    }

    public function test_icon_badge_item_class_and_escaped_extra_attributes(): void
    {
        $xpath = $this->renderNav('<x-ui.nav label="Principal" :items="$items" />');
        $notifications = $this->uiElement($xpath, '//a[@href="/notificacoes"]');

        $this->assertSame('true', $this->uiElement($xpath, '//a[@href="/inicio"]/svg')->getAttribute('aria-hidden'));
        $this->assertSame('não lidas', $this->uiText($this->uiElement($xpath, '//a[@href="/notificacoes"]//span[@class="sr-only"]')));
        $this->assertHasClasses(['bg-primary', 'text-primary-foreground', 'rounded-full'], $this->uiElement($xpath, '//a[@href="/notificacoes"]/span[2]'));
        $this->assertSame('max-md:hidden', $notifications->parentNode->getAttribute('class'));
        $this->assertSame('sino "principal"', $notifications->getAttribute('data-testid'));
    }

    public function test_vertical_variant_uses_44px_rows_and_a_side_bar_on_the_active_item(): void
    {
        $xpath = $this->renderNav('<x-ui.nav label="Menu do portal" variant="vertical" :items="$items" />');
        $active = $this->uiElement($xpath, '//a[@href="/inicio"]');

        $this->assertHasClasses(['flex', 'flex-col', 'gap-1'], $this->uiElement($xpath, '//nav/ul'));
        $this->assertHasClasses(['min-h-11', 'bg-accent', 'text-accent-foreground', 'font-semibold', 'before:absolute', 'before:w-1', 'before:bg-accent-foreground'], $active);
        $this->assertHasClasses(['min-h-11', 'text-muted-foreground'], $this->uiElement($xpath, '//a[@href="/veiculos"]'));
        $this->assertHasClasses(['min-w-0', 'flex-1', 'truncate'], $this->uiElement($xpath, '//a[@href="/veiculos"]/span'));
    }

    public function test_groups_label_their_sublists(): void
    {
        $xpath = $this->renderUi('<x-ui.nav label="Administração" variant="vertical" :items="$groups" />', ['groups' => [
            ['label' => 'Visão', 'items' => [['label' => 'Visão geral', 'href' => '/admin', 'active' => true]]],
            ['label' => 'Cadastros', 'items' => [['label' => 'Marcas', 'href' => '/admin/marcas'], ['label' => 'Veículos', 'href' => '/admin/veiculos']]],
        ]]);

        $sublists = iterator_to_array($xpath->query('//nav/ul/li/ul[@aria-labelledby]'));
        $this->assertCount(2, $sublists);

        foreach ($sublists as $sublist) {
            $label = $this->uiElement($xpath, "//p[@id='{$sublist->getAttribute('aria-labelledby')}']");
            $this->assertHasClasses(['text-xs', 'uppercase', 'text-subtle-foreground'], $label);
        }

        $this->assertSame('Cadastros', $this->uiText($this->uiElement($xpath, "//p[@id='{$sublists[1]->getAttribute('aria-labelledby')}']")));
        $this->assertSame(2, $this->uiCount($xpath, '//nav/ul/li[2]/ul/li/a'));
        $this->assertHasClasses(['min-h-11'], $this->uiElement($xpath, '//a[@href="/admin/marcas"]'));
        $this->assertSame('page', $this->uiElement($xpath, '//a[@href="/admin"]')->getAttribute('aria-current'));
    }

    public function test_without_label_only_the_list_is_rendered_with_the_extra_attributes(): void
    {
        $xpath = $this->renderNav('<x-ui.nav :items="$items" class="justify-end" data-testid="lista" />');

        $this->assertSame(0, $this->uiCount($xpath, '//nav'));
        $list = $this->uiElement($xpath, '//div[@id="ui-test-root"]/ul');
        $this->assertHasClasses(['flex', 'justify-end'], $list);
        $this->assertSame('lista', $list->getAttribute('data-testid'));
    }

    public function test_slot_items_follow_the_nav_variant_and_ignore_unrelated_variants_above(): void
    {
        $xpath = $this->renderUi('<x-ui.nav label="Conta" variant="vertical"><x-ui.nav-item href="/conta" :active="true" icon="user-circle">Dados</x-ui.nav-item></x-ui.nav>');
        $this->assertHasClasses(['min-h-11', 'bg-accent'], $this->uiElement($xpath, '//a[@href="/conta"]'));

        $standalone = $this->renderUi('<ul><x-ui.nav-item href="/x">X</x-ui.nav-item></ul>');
        $this->assertHasClasses(['h-10'], $this->uiElement($standalone, '//a'));

        $this->assertUiRejects('<x-ui.nav variant="sidebar" :items="[]" />', 'x-ui.nav: variant "sidebar" não existe');
    }

    private function renderNav(string $template): DOMXPath
    {
        return $this->renderUi($template, ['items' => [
            ['label' => 'Início', 'href' => '/inicio', 'active' => true, 'icon' => 'home'],
            ['label' => 'Meus veículos', 'href' => '/veiculos', 'icon' => 'truck'],
            [
                'label' => 'Notificações',
                'href' => '/notificacoes',
                'badge' => 3,
                'badgeLabel' => 'não lidas',
                'itemClass' => 'max-md:hidden',
                'attributes' => ['data-testid' => 'sino "principal"'],
            ],
        ]]);
    }
}
