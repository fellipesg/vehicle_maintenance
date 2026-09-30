<?php

namespace Tests\Feature\Ui;

use Tests\Feature\Ui\Concerns\InspectsUiMarkup;
use Tests\TestCase;

/**
 * <x-ui.empty-state>: título, frase de apoio, ícone decorativo e ações.
 */
class EmptyStateTest extends TestCase
{
    use InspectsUiMarkup;

    public function test_full_empty_state(): void
    {
        $xpath = $this->renderUi(<<<'BLADE'
            <x-ui.empty-state icon="truck" title="Nenhum veículo ainda" description="Cadastre pelo chassi para começar o histórico que acompanha o carro.">
                <x-slot:actions>
                    <x-ui.button icon="plus" href="/usuario/veiculos/novo">Adicionar veículo</x-ui.button>
                </x-slot:actions>
            </x-ui.empty-state>
            BLADE);

        $root = $this->uiElement($xpath, '//div[@data-slot="empty-state"]');
        $this->assertHasClasses(['border-dashed', 'border-border-strong', 'rounded-card', 'text-center', 'py-10', 'sm:py-12'], $root);

        $title = $this->uiElement($xpath, '//h3[@data-slot="empty-state-title"]');
        $this->assertSame('Nenhum veículo ainda', $this->uiText($title));
        $this->assertHasClasses(['text-base', 'font-semibold', 'text-foreground'], $title);

        $description = $this->uiElement($xpath, '//p[@data-slot="empty-state-description"]');
        $this->assertHasClasses(['text-muted-foreground'], $description);

        $icon = $this->uiElement($xpath, '//span[@data-slot="empty-state-icon"]');
        $this->assertHasClasses(['size-12', 'rounded-full', 'bg-surface-muted', 'text-muted-foreground'], $icon);
        $this->assertSame('true', $this->uiElement($xpath, '//span[@data-slot="empty-state-icon"]/svg')->getAttribute('aria-hidden'));

        $this->assertSame('/usuario/veiculos/novo', $this->uiElement($xpath, '//div[@data-slot="empty-state-actions"]/a')->getAttribute('href'));
    }

    public function test_heading_level_variant_and_size(): void
    {
        $xpath = $this->renderUi('<x-ui.empty-state title="Nenhuma manutenção com Selo da oficina" heading-level="p" variant="plain" size="sm" />');
        $root = $this->uiElement($xpath, '//div[@data-slot="empty-state"]');

        $this->assertSame(1, $this->uiCount($xpath, '//p[@data-slot="empty-state-title"]'));
        $this->assertSame(0, $this->uiCount($xpath, '//h3'));
        $this->assertLacksClasses(['border-dashed'], $root);
        $this->assertHasClasses(['py-6'], $root);
        $this->assertSame(0, $this->uiCount($xpath, '//span[@data-slot="empty-state-icon"]'));
        $this->assertSame(0, $this->uiCount($xpath, '//div[@data-slot="empty-state-actions"]'));

        $this->assertSame(1, $this->uiCount($this->renderUi('<x-ui.empty-state title="Nada" heading-level="h2" />'), '//h2'));
    }

    public function test_default_slot_adds_extra_content(): void
    {
        $xpath = $this->renderUi('<x-ui.empty-state title="Nenhum resultado">Tente buscar pela placa antiga.</x-ui.empty-state>');

        $this->assertStringContainsString('Tente buscar pela placa antiga.', $this->uiText($this->uiElement($xpath, '//div[@data-slot="empty-state"]')));
    }

    public function test_title_is_required(): void
    {
        $this->assertUiRejects('<x-ui.empty-state description="Sem título" />', 'x-ui.empty-state precisa de title.');
        $this->assertUiRejects('<x-ui.empty-state title="x" heading-level="h1" />', 'x-ui.empty-state: heading-level "h1" não existe.');
    }
}
