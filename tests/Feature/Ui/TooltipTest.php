<?php

namespace Tests\Feature\Ui;

use Tests\Feature\Ui\Concerns\InspectsUiMarkup;
use Tests\TestCase;

/**
 * <x-ui.tooltip>: dica em CSS com role="tooltip", ligada ao gatilho por aria-describedby, que abre
 * no hover e no foco e some com Esc (resources/js/ui/tooltip.js).
 */
class TooltipTest extends TestCase
{
    use InspectsUiMarkup;

    public function test_tooltip_describes_the_trigger(): void
    {
        $xpath = $this->renderUi('<x-ui.tooltip text="Usado em 3 OS: não pode ser excluído" id="dica-modelo"><button type="button" class="btn-secondary">Excluir</button></x-ui.tooltip>');
        $tooltip = $this->uiElement($xpath, '//span[@role="tooltip"]');
        $trigger = $this->uiElement($xpath, '//button');

        $this->assertSame('dica-modelo', $tooltip->getAttribute('id'));
        $this->assertSame('dica-modelo', $trigger->getAttribute('aria-describedby'));
        $this->assertSame('btn-secondary', $trigger->getAttribute('class'));
        $this->assertSame('Usado em 3 OS: não pode ser excluído', $this->uiText($tooltip));
        $this->assertTrue($tooltip->hasAttribute('data-ui-tooltip-content'));
        $this->assertTrue($this->uiElement($xpath, '//span[@data-ui-tooltip]')->hasAttribute('data-ui-tooltip'));
    }

    public function test_existing_aria_describedby_is_kept_and_the_tooltip_id_is_appended(): void
    {
        $trigger = $this->uiElement(
            $this->renderUi('<x-ui.tooltip text="Dica" id="dica"><a href="/ajuda" aria-describedby="nota">Ajuda</a></x-ui.tooltip>'),
            '//a',
        );

        $this->assertSame('nota dica', $trigger->getAttribute('aria-describedby'));
        $this->assertSame('/ajuda', $trigger->getAttribute('href'));
    }

    public function test_void_and_self_closing_triggers_stay_valid(): void
    {
        $input = $this->uiElement($this->renderUi('<x-ui.tooltip text="Formato ABC1D23" id="dica"><input type="text" name="placa" /></x-ui.tooltip>'), '//input');

        $this->assertSame('dica', $input->getAttribute('aria-describedby'));
        $this->assertSame('placa', $input->getAttribute('name'));
    }

    public function test_plain_text_becomes_a_focusable_span(): void
    {
        $trigger = $this->uiElement($this->renderUi('<x-ui.tooltip text="Selo emitido pela oficina" id="dica">Selo da oficina</x-ui.tooltip>'), '//span[@tabindex="0"]');

        $this->assertSame('dica', $trigger->getAttribute('aria-describedby'));
        $this->assertSame('Selo da oficina', $this->uiText($trigger));
    }

    public function test_it_opens_on_hover_and_focus_can_be_dismissed_and_stays_hoverable(): void
    {
        $tooltip = $this->uiElement($this->renderUi('<x-ui.tooltip text="Dica"><button type="button">X</button></x-ui.tooltip>'), '//span[@role="tooltip"]');

        $this->assertMatchesRegularExpression('/^dica-[a-z0-9]{8}$/', $tooltip->getAttribute('id'));
        $this->assertHasClasses([
            'invisible', 'opacity-0', 'pointer-events-none', 'bg-foreground', 'text-background', 'text-xs', 'rounded-control', 'z-40',
            'group-hover/tooltip:visible', 'group-hover/tooltip:opacity-100', 'group-hover/tooltip:pointer-events-auto', 'group-hover/tooltip:delay-300',
            'group-focus-within/tooltip:visible', 'group-focus-within/tooltip:opacity-100',
            'group-data-[tooltip-dismissed]/tooltip:invisible!', 'group-data-[tooltip-dismissed]/tooltip:opacity-0!',
            'transition-[opacity,visibility]', 'duration-fast', 'motion-reduce:transition-none',
            'after:absolute', 'after:h-2', 'max-w-[min(16rem,calc(100vw-2rem))]',
        ], $tooltip);
    }

    public function test_placements_map_to_literal_classes(): void
    {
        $expectations = [
            'top' => ['bottom-full', 'mb-2', 'after:top-full'],
            'bottom' => ['top-full', 'mt-2', 'after:bottom-full'],
            'left' => ['right-full', 'mr-2', 'after:left-full'],
            'right' => ['left-full', 'ml-2', 'after:right-full'],
        ];

        foreach ($expectations as $placement => $classes) {
            $tooltip = $this->uiElement($this->renderUi('<x-ui.tooltip text="Dica" :placement="$placement"><button>X</button></x-ui.tooltip>', ['placement' => $placement]), '//span[@role="tooltip"]');

            $this->assertHasClasses($classes, $tooltip);
        }

        $this->assertUiRejects('<x-ui.tooltip text="Dica" placement="center"><button>X</button></x-ui.tooltip>', 'x-ui.tooltip: placement "center" não existe');
    }

    public function test_text_is_escaped_and_required(): void
    {
        $html = (string) $this->blade('<x-ui.tooltip :text="$text"><button>X</button></x-ui.tooltip>', ['text' => '<img src=x onerror=alert(1)>']);

        $this->assertStringNotContainsString('<img', $html);
        $this->assertUiRejects('<x-ui.tooltip text=""><button>X</button></x-ui.tooltip>', 'x-ui.tooltip precisa de text');
    }
}
