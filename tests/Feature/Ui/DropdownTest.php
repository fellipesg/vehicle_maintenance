<?php

namespace Tests\Feature\Ui;

use Tests\Feature\Ui\Concerns\InspectsUiMarkup;
use Tests\TestCase;

/**
 * <x-ui.dropdown> e <x-ui.dropdown-item>: menu do HSDropdown com gatilho acessível e itens que são
 * link, botão, formulário, separador ou cabeçalho.
 */
class DropdownTest extends TestCase
{
    use InspectsUiMarkup;

    public function test_trigger_is_a_button_that_announces_and_controls_the_menu(): void
    {
        $xpath = $this->renderUi('<x-ui.dropdown id="conta" label="Conta de Ana"><x-slot:trigger>AS</x-slot:trigger><x-ui.dropdown-item href="/conta">Minha conta</x-ui.dropdown-item></x-ui.dropdown>');
        $wrapper = $this->uiElement($xpath, '//div[@data-ui-dropdown]');
        $trigger = $this->uiElement($xpath, '//button[@id="conta-gatilho"]');
        $menu = $this->uiElement($xpath, '//div[@id="conta-itens"]');

        $this->assertHasClasses(['hs-dropdown', 'relative', 'inline-flex', '[--placement:bottom-right]'], $wrapper);
        $this->assertSame('button', $trigger->getAttribute('type'));
        $this->assertSame('menu', $trigger->getAttribute('aria-haspopup'));
        $this->assertSame('false', $trigger->getAttribute('aria-expanded'));
        $this->assertSame('conta-itens', $trigger->getAttribute('aria-controls'));
        $this->assertSame('Conta de Ana', $trigger->getAttribute('aria-label'));
        $this->assertHasClasses(['hs-dropdown-toggle', 'min-h-10', 'hover:bg-surface-muted'], $trigger);
        $this->assertSame('AS', $this->uiText($trigger));

        $this->assertSame('menu', $menu->getAttribute('role'));
        $this->assertSame('conta-gatilho', $menu->getAttribute('aria-labelledby'));
        $this->assertHasClasses([
            'hs-dropdown-menu', 'hidden', 'theme-default', 'z-40', 'w-56', 'max-w-[calc(100vw-2rem)]', 'rounded-card',
            'bg-surface', 'shadow-lg', 'origin-top-right', 'opacity-0', 'hs-dropdown-open:opacity-100',
            'motion-safe:scale-[.97]', 'motion-safe:hs-dropdown-open:scale-100', 'duration-fast', 'motion-reduce:transition-none',
        ], $menu);
    }

    public function test_trigger_slot_attributes_reach_the_button_and_replace_the_default_look(): void
    {
        $trigger = $this->uiElement(
            $this->renderUi('<x-ui.dropdown label="Conta"><x-slot:trigger class="size-10 rounded-full bg-surface-muted" data-testid="avatar">AS</x-slot:trigger></x-ui.dropdown>'),
            '//button[contains(@class, "hs-dropdown-toggle")]',
        );

        $this->assertHasClasses(['hs-dropdown-toggle', 'size-10', 'rounded-full'], $trigger);
        $this->assertLacksClasses(['min-h-10', 'px-3'], $trigger);
        $this->assertSame('avatar', $trigger->getAttribute('data-testid'));
        $this->assertSame('menu', $trigger->getAttribute('aria-haspopup'));
    }

    public function test_generated_ids_are_unique_per_menu(): void
    {
        $xpath = $this->renderUi('<x-ui.dropdown><x-slot:trigger>A</x-slot:trigger></x-ui.dropdown><x-ui.dropdown><x-slot:trigger>B</x-slot:trigger></x-ui.dropdown>');
        $ids = array_map(fn ($node): string => $node->getAttribute('id'), iterator_to_array($xpath->query('//div[@role="menu"]')));

        $this->assertCount(2, array_unique($ids));
        $this->assertMatchesRegularExpression('/^menu-[a-z0-9]{8}-itens$/', $ids[0]);
    }

    public function test_placement_and_width_map_to_literal_classes(): void
    {
        $expectations = [
            'bottom-start' => ['[--placement:bottom-left]', 'origin-top-left'],
            'top-end' => ['[--placement:top-right]', 'origin-bottom-right'],
            'top-start' => ['[--placement:top-left]', 'origin-bottom-left'],
        ];

        foreach ($expectations as $placement => [$placementClass, $originClass]) {
            $xpath = $this->renderUi('<x-ui.dropdown :placement="$placement" width="lg"><x-slot:trigger>A</x-slot:trigger></x-ui.dropdown>', ['placement' => $placement]);

            $this->assertHasClasses([$placementClass], $this->uiElement($xpath, '//div[@data-ui-dropdown]'));
            $this->assertHasClasses([$originClass, 'w-72'], $this->uiElement($xpath, '//div[@role="menu"]'));
        }

        $this->assertUiRejects('<x-ui.dropdown placement="left"><x-slot:trigger>A</x-slot:trigger></x-ui.dropdown>', 'x-ui.dropdown: placement "left" não existe');
        $this->assertUiRejects('<x-ui.dropdown width="xl"><x-slot:trigger>A</x-slot:trigger></x-ui.dropdown>', 'x-ui.dropdown: width "xl" não existe');
        $this->assertUiRejects('<x-ui.dropdown></x-ui.dropdown>', 'x-ui.dropdown precisa de trigger');
    }

    public function test_link_item_is_a_menuitem_with_an_icon(): void
    {
        $xpath = $this->renderUi('<x-ui.dropdown-item href="/conta" icon="user-circle" data-testid="item">Minha conta</x-ui.dropdown-item>');
        $link = $this->uiElement($xpath, '//a');

        $this->assertSame('/conta', $link->getAttribute('href'));
        $this->assertSame('menuitem', $link->getAttribute('role'));
        $this->assertSame('item', $link->getAttribute('data-testid'));
        $this->assertHasClasses(['min-h-10', 'rounded-control', 'text-foreground', 'hover:bg-surface-muted', 'focus-visible:bg-surface-muted', 'focus-visible:outline-offset-[-2px]', 'motion-reduce:transition-none'], $link);
        $this->assertSame('true', $this->uiElement($xpath, '//a/svg')->getAttribute('aria-hidden'));
        $this->assertSame('Minha conta', $this->uiText($link));
    }

    public function test_action_item_is_a_post_form_with_csrf_and_the_extra_attributes_on_the_submit_button(): void
    {
        $xpath = $this->renderUi('<x-ui.dropdown-item action="/sair" icon="arrow-right-start-on-rectangle">Sair</x-ui.dropdown-item>');
        $form = $this->uiElement($xpath, '//form');

        $this->assertSame('POST', $form->getAttribute('method'));
        $this->assertSame('/sair', $form->getAttribute('action'));
        $this->assertSame('none', $form->getAttribute('role'));
        $this->assertSame(1, $this->uiCount($xpath, '//form/input[@name="_token"]'));
        $this->assertSame(0, $this->uiCount($xpath, '//form/input[@name="_method"]'));
        $this->assertSame('submit', $this->uiElement($xpath, '//form/button')->getAttribute('type'));
        $this->assertSame('menuitem', $this->uiElement($xpath, '//form/button')->getAttribute('role'));

        $deleteXpath = $this->renderUi('<x-ui.dropdown-item action="/veiculos/1" method="delete" variant="danger" data-confirm="Excluir o veículo?">Excluir</x-ui.dropdown-item>');
        $button = $this->uiElement($deleteXpath, '//form/button');

        $this->assertSame('DELETE', $this->uiElement($deleteXpath, '//form/input[@name="_method"]')->getAttribute('value'));
        $this->assertSame('Excluir o veículo?', $button->getAttribute('data-confirm'));
        $this->assertHasClasses(['text-danger', 'hover:bg-danger-soft'], $button);
        $this->assertUiRejects('<x-ui.dropdown-item action="/x" method="GET">X</x-ui.dropdown-item>', 'x-ui.dropdown-item: method "GET" não existe');
    }

    public function test_item_without_href_or_action_is_a_plain_button_for_javascript(): void
    {
        $button = $this->uiElement($this->renderUi('<x-ui.dropdown-item data-dialog-open="dialogo-placa">Trocar placa</x-ui.dropdown-item>'), '//button');

        $this->assertSame('button', $button->getAttribute('type'));
        $this->assertSame('menuitem', $button->getAttribute('role'));
        $this->assertSame('dialogo-placa', $button->getAttribute('data-dialog-open'));
    }

    public function test_separator_heading_and_disabled_items(): void
    {
        $separator = $this->uiElement($this->renderUi('<x-ui.dropdown-item separator />'), '//div[@id="ui-test-root"]/div');
        $this->assertSame('separator', $separator->getAttribute('role'));
        $this->assertHasClasses(['h-px', 'bg-border'], $separator);

        $heading = $this->uiElement($this->renderUi('<x-ui.dropdown-item heading>ana@example.com</x-ui.dropdown-item>'), '//div[@id="ui-test-root"]/div');
        $this->assertSame('none', $heading->getAttribute('role'));
        $this->assertHasClasses(['text-muted-foreground'], $heading);
        $this->assertSame('ana@example.com', $this->uiText($heading));

        $disabledXpath = $this->renderUi('<x-ui.dropdown-item href="/relatorio" disabled>Relatório</x-ui.dropdown-item>');
        $this->assertSame(0, $this->uiCount($disabledXpath, '//a'), 'Item desabilitado perde o href.');
        $disabled = $this->uiElement($disabledXpath, '//button');
        $this->assertSame('true', $disabled->getAttribute('aria-disabled'));
        $this->assertSame('-1', $disabled->getAttribute('tabindex'));
        $this->assertHasClasses(['pointer-events-none', 'opacity-60'], $disabled);

        $this->assertUiRejects('<x-ui.dropdown-item variant="warning">X</x-ui.dropdown-item>', 'x-ui.dropdown-item: variant "warning" não existe');
    }
}
