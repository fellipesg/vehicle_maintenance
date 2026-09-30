<?php

namespace Tests\Feature\Web;

use App\Enums\Portal;
use App\Models\User;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Botões de ação das páginas dos portais com os verbos do glossário (Adicionar veículo, Adicionar ao
 * estoque, Registrar manutenção, Nova OS), o mesmo texto da ação principal da topbar
 * (Portal::primaryAction()) e sem o prefixo "+" no texto: o ícone já diz isso.
 */
class PortalCallToActionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: string, 2: string, 3: string}>
     */
    public static function pages(): array
    {
        return [
            'meus veículos' => ['asUser', 'user.vehicles.index', 'user.vehicles.create', 'Adicionar veículo'],
            'manutenções do proprietário' => ['asUser', 'user.maintenances.index', 'user.maintenances.create', 'Registrar manutenção'],
            'estoque' => ['asGarage', 'garage.vehicles.index', 'garage.vehicles.create', 'Adicionar ao estoque'],
            'manutenções do lojista' => ['asGarage', 'garage.maintenances.index', 'garage.maintenances.create', 'Registrar manutenção'],
            'início do lojista' => ['asGarage', 'garage.dashboard', 'garage.vehicles.create', 'Adicionar ao estoque'],
            'ordens de serviço' => ['asWorkshop', 'workshop.maintenances.index', 'workshop.maintenances.create', 'Nova OS'],
        ];
    }

    #[DataProvider('pages')]
    public function test_page_action_uses_the_glossary_verb_and_the_button_component(string $state, string $page, string $target, string $label): void
    {
        $user = User::factory()->{$state}()->create();
        $html = $this->actingAs($user)->get(route($page))->assertOk()->getContent();

        $document = new \DOMDocument;
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        $xpath = new DOMXPath($document);

        $pageButtons = $xpath->query('//main//a[@data-slot="button"][@href="'.route($target).'"]');

        $this->assertGreaterThanOrEqual(1, $pageButtons->length, "{$page}: botão para {$target} no conteúdo.");
        $this->assertSame($label, trim($xpath->query('.//*[@data-slot="label"]', $pageButtons->item(0))->item(0)->textContent));
        $this->assertSame(1, $xpath->query('.//svg', $pageButtons->item(0))->length, 'Ícone de mais antes do rótulo.');
    }

    public function test_page_actions_match_the_topbar_primary_action(): void
    {
        $this->assertSame('Adicionar veículo', Portal::Owner->primaryAction()['label']);
        $this->assertSame('Adicionar ao estoque', Portal::Dealer->primaryAction()['label']);
        $this->assertSame('Nova OS', Portal::Workshop->primaryAction()['label']);
    }

    public function test_no_button_label_starts_with_a_plus_sign(): void
    {
        $offenders = [];

        foreach (['views', 'js'] as $directory) {
            foreach (File::allFiles(resource_path($directory)) as $file) {
                if (preg_match_all('/[>\'"`]\s*\+ \p{L}[^<\'"`]*/u', $file->getContents(), $matches)) {
                    foreach ($matches[0] as $match) {
                        $offenders[] = $file->getRelativePathname().': '.trim($match);
                    }
                }
            }
        }

        $this->assertSame([], $offenders, 'Tire o "+" do texto e use icon="plus" no <x-ui.button>.');
    }
}
