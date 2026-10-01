<?php

namespace Tests\Feature\DesignSystem;

use App\Models\BlogPost;
use App\Models\Vehicle;
use DOMElement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Feature\Ui\Concerns\InspectsUiMarkup;
use Tests\TestCase;

/**
 * No Tailwind 4 os utilitários de movimento (translate-*, scale-*, rotate-*) escrevem as
 * propriedades individuais translate, scale e rotate, e não transform. Uma lista
 * transition-[...,transform] não anima o aperto dos botões nem o lift de 2px dos cards: eles
 * pulam na hora, sem os 150ms com ease-smooth-out. A lista nomeia a propriedade que muda.
 */
class MotionTransitionPropertiesTest extends TestCase
{
    use InspectsUiMarkup;
    use RefreshDatabase;

    public function test_arbitrary_transition_lists_never_name_transform(): void
    {
        $offenders = [];

        foreach ([resource_path('views'), resource_path('js')] as $directory) {
            foreach (File::allFiles($directory) as $file) {
                if (! str_ends_with($file->getFilename(), '.blade.php') && $file->getExtension() !== 'js') {
                    continue;
                }

                if (preg_match_all('/transition-\[[^\]]*\btransform\b[^\]]*\]/', $file->getContents(), $matches)) {
                    $offenders[] = $file->getRelativePathname().': '.implode(', ', array_unique($matches[0]));
                }
            }
        }

        $this->assertSame([], $offenders, 'Troque transform por translate, scale ou rotate: é o que os utilitários do Tailwind 4 escrevem.');
    }

    public function test_pressable_controls_animate_the_scale_they_press_with(): void
    {
        foreach ([
            '<x-ui.button>Salvar</x-ui.button>',
            '<x-ui.icon-button icon="x-mark" label="Fechar aviso" />',
            '<x-ui.copy-button value="9BWZZZ377VT004251" label="Copiar chassi" />',
        ] as $template) {
            $button = $this->uiElement($this->renderUi($template), '//button');

            $this->assertHasClasses(['transition-[color,background-color,border-color,box-shadow,scale]', 'duration-fast', 'ease-smooth-out', 'motion-reduce:transition-none'], $button);
            $this->assertMovementIsTransitioned($button, $template);
        }
    }

    public function test_clickable_cards_and_dismissed_alerts_animate_the_translate_they_move_with(): void
    {
        $vehicle = Vehicle::factory()->create();

        $elements = [
            'x-ui.card' => $this->uiElement(
                $this->renderUi('<x-ui.card href="/usuario/veiculos/7" title="Gol 1.0 2020">Placa ABC1D23</x-ui.card>'),
                '//*[contains(@class, "transition-[")]',
            ),
            'x-ui.stat' => $this->uiElement(
                $this->renderUi('<x-ui.stat label="Manutenções" value="48" href="/usuario/manutencoes" />'),
                '//*[contains(@class, "transition-[")]',
            ),
            'x-vehicle.card' => $this->uiElement(
                $this->renderUi('<x-vehicle.card :vehicle="$vehicle" href="/usuario/veiculos/9" />', ['vehicle' => $vehicle]),
                '//*[@data-slot="vehicle-card"]',
            ),
        ];

        foreach ($elements as $component => $element) {
            $this->assertHasClasses(['transition-[border-color,box-shadow,translate]', 'motion-safe:hover:-translate-y-0.5'], $element);
            $this->assertMovementIsTransitioned($element, $component);
        }

        $alert = $this->uiElement(
            $this->renderUi('<x-ui.alert variant="warning" dismissible>Complete o cadastro da oficina.</x-ui.alert>'),
            '//*[contains(@class, "hs-removing:")]',
        );
        $this->assertHasClasses(['transition-[opacity,translate]', 'motion-safe:hs-removing:-translate-y-1'], $alert);
        $this->assertMovementIsTransitioned($alert, 'x-ui.alert dismissible');
    }

    public function test_blog_card_lift_is_transitioned(): void
    {
        BlogPost::factory()->create(['title' => 'Troca de óleo por quilometragem']);

        $xpath = $this->parseHtml($this->get(route('blog.index'))->assertOk()->getContent());
        $card = $this->uiElement($xpath, '//*[contains(concat(" ", normalize-space(@class), " "), " motion-safe:hover:-translate-y-0.5 ")]');

        $this->assertHasClasses(['transition-[border-color,box-shadow,translate]'], $card);
        $this->assertMovementIsTransitioned($card, 'x-blog.card');
    }

    /**
     * Todo utilitário de movimento do elemento (com qualquer variante: motion-safe:, hover:,
     * active:, hs-removing:) precisa da propriedade dele na lista de transição do mesmo elemento.
     */
    private function assertMovementIsTransitioned(DOMElement $element, string $context): void
    {
        $classes = $this->uiClasses($element);
        $transitionLists = array_values(preg_grep('/^transition-\[/', $classes));

        $this->assertCount(1, $transitionLists, "{$context}: esperava uma lista transition-[...].");

        $transitioned = explode(',', substr($transitionLists[0], strlen('transition-['), -1));
        $moved = [];

        foreach ($classes as $class) {
            $segments = explode(':', $class);

            if (preg_match('/^-?(translate|scale|rotate)-/', end($segments), $match)) {
                $moved[$match[1]] = true;
            }
        }

        $this->assertNotEmpty($moved, "{$context}: nenhum utilitário de movimento encontrado.");

        foreach (array_keys($moved) as $property) {
            $this->assertContains($property, $transitioned, "{$context}: a transição não inclui {$property}.");
        }
    }
}
