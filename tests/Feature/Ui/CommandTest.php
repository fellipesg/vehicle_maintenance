<?php

namespace Tests\Feature\Ui;

use Tests\Feature\Ui\Concerns\InspectsRenderedComponents;
use Tests\TestCase;

/**
 * <x-ui.command>: paleta em <dialog> com o padrão combobox + listbox (foco sempre no campo,
 * aria-activedescendant), opções em grupos rotulados e a busca de veículo que abre a busca que já
 * existe com o texto no parâmetro, sem rota nova.
 */
class CommandTest extends TestCase
{
    use InspectsRenderedComponents;

    /**
     * @var list<array<string, mixed>>
     */
    private const DESTINATIONS = [
        ['label' => 'Visão geral', 'href' => 'https://revisalog.test/admin', 'active' => true, 'icon' => 'chart-bar'],
        ['label' => 'Frota', 'items' => [
            ['label' => 'Veículos', 'href' => 'https://revisalog.test/admin/veiculos', 'active' => false, 'icon' => 'truck'],
            ['label' => 'Manutenções', 'href' => 'https://revisalog.test/admin/manutencoes', 'active' => false, 'icon' => 'wrench-screwdriver'],
        ]],
    ];

    public function test_palette_is_a_dialog_with_a_combobox_controlling_a_listbox(): void
    {
        $document = $this->renderComponent(
            '<x-ui.command :destinations="$destinations" :actions="$actions" search-action="https://revisalog.test/admin/veiculos" search-param="search" />',
            ['destinations' => self::DESTINATIONS, 'actions' => [['label' => 'Nova marca', 'href' => 'https://revisalog.test/admin/marcas/nova', 'icon' => 'plus']]],
        );

        $dialog = $this->element($document, 'dialog#comandos');
        $this->assertTrue($dialog->hasAttribute('data-ui-dialog'), 'Usa o motor de resources/js/ui/dialog.js.');
        $this->assertTrue($dialog->hasAttribute('data-ui-command'));
        $this->assertSame('comandos-titulo', $dialog->getAttribute('aria-labelledby'));
        $this->assertSame('comandos-dica', $dialog->getAttribute('aria-describedby'));
        $this->assertSame('Buscar ou ir para', $this->element($document, '#comandos-titulo')->textContent);
        $this->assertContains('sr-only', $this->classesOf($this->element($document, '#comandos-titulo')));

        foreach (['theme-default', 'backdrop:bg-overlay', 'motion-safe:scale-[.96]', 'open:duration-base', 'duration-fast', 'ease-smooth-out', 'motion-reduce:transition-none', 'max-w-xl'] as $class) {
            $this->assertContains($class, $this->classesOf($dialog), "Faltou {$class}.");
        }

        $input = $this->element($document, 'input[data-command-input]');
        $this->assertSame('combobox', $input->getAttribute('role'));
        $this->assertSame('comandos-opcoes', $input->getAttribute('aria-controls'));
        $this->assertSame('true', $input->getAttribute('aria-expanded'));
        $this->assertSame('list', $input->getAttribute('aria-autocomplete'));
        $this->assertSame('off', $input->getAttribute('autocomplete'));
        $this->assertTrue($input->hasAttribute('data-dialog-initial-focus'));
        $this->assertFalse($input->hasAttribute('name'), 'Sem name: a página com campo de busca continua com um só.');
        $this->assertSame('Buscar ou ir para', $this->element($document, 'label[for="comandos-campo"]')->textContent);
        $this->assertNull($document->querySelector('form'));

        $listbox = $this->element($document, '#comandos-opcoes');
        $this->assertSame('listbox', $listbox->getAttribute('role'));

        $groups = [];
        foreach ($document->querySelectorAll('[role="group"]') as $group) {
            $groups[] = trim($this->element($document, '#'.$group->getAttribute('aria-labelledby'))->textContent);
        }
        $this->assertSame(['Ir para', 'Ações', 'Buscar'], $groups);

        $options = $document->querySelectorAll('[role="option"]');
        $this->assertCount(5, $options);

        foreach ($options as $option) {
            $this->assertSame('false', $option->getAttribute('aria-selected'));
            $this->assertNotSame('', $option->getAttribute('id'));
            $this->assertFalse($option->hasAttribute('href'), 'Opção não é link focável: o foco fica no campo.');
            $this->assertContains('aria-selected:bg-accent', $this->classesOf($option));
            $this->assertContains('min-h-11', $this->classesOf($option));
        }

        $vehicles = $options->item(1);
        $this->assertSame('https://revisalog.test/admin/veiculos', $vehicles->getAttribute('data-command-url'));
        $this->assertSame('Frota', $vehicles->getAttribute('data-command-keywords'), 'O grupo também filtra.');
        $this->assertStringContainsString('Frota', $vehicles->textContent);
        $this->assertStringContainsString('Página atual', $options->item(0)->textContent);
        $this->assertSame('https://revisalog.test/admin/marcas/nova', $options->item(3)->getAttribute('data-command-url'));

        $search = $this->element($document, '[data-command-search]');
        $this->assertSame('https://revisalog.test/admin/veiculos', $search->getAttribute('data-command-url'));
        $this->assertSame('search', $search->getAttribute('data-command-param'));
        $this->assertStringContainsString('Buscar veículo por placa, chassi ou RENAVAM', $search->textContent);

        $status = $this->element($document, '[data-command-status]');
        $this->assertSame('status', $status->getAttribute('role'));
        $this->assertSame('polite', $status->getAttribute('aria-live'));

        $close = $this->element($document, 'button[data-dialog-close-button]');
        $this->assertSame('Fechar', $close->getAttribute('aria-label'));
    }

    public function test_portal_defaults_search_the_vehicle_by_identifier_and_skip_empty_groups(): void
    {
        $document = $this->renderComponent('<x-ui.command id="paleta" :destinations="[[\'label\' => \'Início\', \'href\' => \'/usuario\']]" search-action="https://revisalog.test/buscar-veiculo" />');

        $this->assertNotNull($document->querySelector('dialog#paleta'));
        $this->assertSame('identifier', $this->element($document, '[data-command-search]')->getAttribute('data-command-param'));
        $this->assertNull($document->querySelector('[data-command-group="acoes"]'), 'Sem ações, sem grupo vazio.');
        $this->assertSame('paleta-opcoes', $this->element($document, 'input[role="combobox"]')->getAttribute('aria-controls'));
    }

    public function test_search_action_is_required(): void
    {
        try {
            $this->blade('<x-ui.command :destinations="[]" />');
            $this->fail('Paleta sem busca deveria falhar.');
        } catch (\Throwable $exception) {
            $cause = $exception;

            while (! $cause instanceof \InvalidArgumentException && $cause->getPrevious() !== null) {
                $cause = $cause->getPrevious();
            }

            $this->assertInstanceOf(\InvalidArgumentException::class, $cause, $exception->getMessage());
            $this->assertStringContainsString('x-ui.command precisa de searchAction', $cause->getMessage());
        }
    }
}
