<?php

namespace Tests\Feature\Ui;

use Tests\Feature\Ui\Concerns\InspectsUiMarkup;
use Tests\TestCase;

/**
 * <x-ui.page-header>: o único H1 da página, com trilha, descrição e ações.
 */
class PageHeaderTest extends TestCase
{
    use InspectsUiMarkup;

    public function test_renders_the_single_h1_with_description_and_actions(): void
    {
        $xpath = $this->renderUi(<<<'BLADE'
            <x-ui.page-header title="Estoque" description="Veículos da sua loja, com o histórico de procedência.">
                <x-slot:actions>
                    <x-ui.button variant="secondary">Importar CRLV-e</x-ui.button>
                    <x-ui.button icon="plus" href="/garagem/veiculos/novo">Adicionar ao estoque</x-ui.button>
                </x-slot:actions>
            </x-ui.page-header>
            BLADE);

        $header = $this->uiElement($xpath, '//header[@data-slot="page-header"]');
        $this->assertHasClasses(['mb-6'], $header);

        $this->assertSame(1, $this->uiCount($xpath, '//h1'));
        $title = $this->uiElement($xpath, '//h1');
        $this->assertSame('Estoque', $this->uiText($title));
        $this->assertHasClasses(['text-2xl', 'sm:text-3xl', 'font-bold', 'tracking-tight', 'text-foreground'], $title);

        $description = $this->uiElement($xpath, '//p[@data-slot="page-header-description"]');
        $this->assertHasClasses(['text-sm', 'text-muted-foreground', 'max-w-prose'], $description);

        $actions = $this->uiElement($xpath, '//div[@data-slot="page-header-actions"]');
        $this->assertHasClasses(['flex', 'flex-wrap', 'max-sm:*:grow', 'sm:justify-end'], $actions);
        $this->assertSame(2, $xpath->query('//div[@data-slot="page-header-actions"]/*')->length);
        $this->assertSame(0, $this->uiCount($xpath, '//nav'));
        $this->assertSame(0, $this->uiCount($xpath, '//p[@data-slot="page-header-eyebrow"]'));
    }

    public function test_breadcrumbs_prop_and_eyebrow(): void
    {
        $xpath = $this->renderUi('<x-ui.page-header title="Gol 1.0 2020" eyebrow="Selo da oficina" :breadcrumbs="$trail" />', [
            'trail' => [['Meus veículos', '/usuario/veiculos'], ['Gol 1.0 2020']],
        ]);

        $this->assertSame('Trilha', $this->uiElement($xpath, '//header/nav')->getAttribute('aria-label'));
        $eyebrow = $this->uiElement($xpath, '//p[@data-slot="page-header-eyebrow"]');
        $this->assertSame('Selo da oficina', $this->uiText($eyebrow));
        $this->assertHasClasses(['text-xs', 'uppercase', 'text-accent-foreground'], $eyebrow);

        $nodes = $xpath->query('//header//*[self::p[@data-slot="page-header-eyebrow"] or self::h1]');
        $this->assertSame('p', $nodes->item(0)->nodeName, 'O eyebrow vem antes do H1.');
    }

    public function test_breadcrumb_slot_and_extra_content(): void
    {
        $xpath = $this->renderUi(<<<'BLADE'
            <x-ui.page-header title="Minha conta">
                <x-slot:breadcrumb>
                    <nav aria-label="Trilha" id="trilha-propria">Trilha própria</nav>
                </x-slot:breadcrumb>
                <x-ui.badge variant="success">Ativa</x-ui.badge>
            </x-ui.page-header>
            BLADE);

        $this->assertSame(1, $this->uiCount($xpath, '//nav[@id="trilha-propria"]'));
        $this->assertSame(1, $this->uiCount($xpath, '//span[@data-slot="badge"]'));
        $this->assertSame(0, $this->uiCount($xpath, '//div[@data-slot="page-header-actions"]'));
    }

    public function test_title_is_required_and_escaped(): void
    {
        $this->assertUiRejects('<x-ui.page-header description="Sem título" />', 'x-ui.page-header precisa de title.');

        $html = (string) $this->blade('<x-ui.page-header :title="$title" />', ['title' => '<script>x</script>']);
        $this->assertStringNotContainsString('<script>', $html);
    }
}
