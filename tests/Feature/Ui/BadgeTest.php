<?php

namespace Tests\Feature\Ui;

use Tests\Feature\Ui\Concerns\InspectsUiMarkup;
use Tests\TestCase;

/**
 * <x-ui.badge>: status pelos tokens semânticos e procedência pelo contrato de theme.md
 * ("Selo da oficina" / "Declarada", tokens prov-* e .prov-dot).
 */
class BadgeTest extends TestCase
{
    use InspectsUiMarkup;

    public function test_neutral_is_the_default(): void
    {
        $badge = $this->uiElement($this->renderUi('<x-ui.badge>Rascunho</x-ui.badge>'), '//span[@data-slot="badge"]');

        $this->assertSame('neutral', $badge->getAttribute('data-variant'));
        $this->assertSame('Rascunho', $this->uiText($badge));
        $this->assertHasClasses(['rounded-full', 'border', 'text-xs', 'font-medium', 'bg-surface-muted', 'text-muted-foreground'], $badge);
    }

    public function test_status_variants_use_semantic_tokens(): void
    {
        $expectations = [
            'primary' => ['bg-accent', 'text-accent-foreground', 'border-accent-border'],
            'success' => ['bg-success-soft', 'text-success'],
            'warning' => ['bg-warning-soft', 'text-warning'],
            'danger' => ['bg-danger-soft', 'text-danger'],
            'info' => ['bg-info-soft', 'text-info'],
        ];

        foreach ($expectations as $variant => $classes) {
            $badge = $this->uiElement($this->renderUi('<x-ui.badge :variant="$variant">Status</x-ui.badge>', ['variant' => $variant]), '//span[@data-slot="badge"]');

            $this->assertHasClasses($classes, $badge);
        }
    }

    public function test_seal_uses_the_provenance_contract_and_default_wording(): void
    {
        $xpath = $this->renderUi('<x-ui.badge variant="seal" />');
        $badge = $this->uiElement($xpath, '//span[@data-slot="badge"]');

        $this->assertSame('Selo da oficina', $this->uiText($badge));
        $this->assertHasClasses(['border-prov-verified', 'bg-prov-verified-surface', 'text-prov-verified'], $badge);
        $this->assertLacksClasses(['border-dashed'], $badge);

        $dot = $this->uiElement($xpath, '//span[contains(@class, "prov-dot")]');
        $this->assertHasClasses(['prov-dot', 'prov-dot--verified'], $dot);
        $this->assertSame('true', $dot->getAttribute('aria-hidden'));
    }

    public function test_declared_is_dashed_and_says_declarada(): void
    {
        $xpath = $this->renderUi('<x-ui.badge variant="declared" />');
        $badge = $this->uiElement($xpath, '//span[@data-slot="badge"]');

        $this->assertSame('Declarada', $this->uiText($badge));
        $this->assertHasClasses(['border-dashed', 'border-prov-declared', 'bg-prov-declared-surface', 'text-prov-declared'], $badge);
        $this->assertHasClasses(['prov-dot--declared'], $this->uiElement($xpath, '//span[contains(@class, "prov-dot")]'));
    }

    public function test_provenance_badges_accept_a_more_specific_text(): void
    {
        $badge = $this->uiElement($this->renderUi('<x-ui.badge variant="declared">Declarada pelo lojista</x-ui.badge>'), '//span[@data-slot="badge"]');

        $this->assertSame('Declarada pelo lojista', $this->uiText($badge));
    }

    public function test_provenance_wording_never_falls_back_to_verificada(): void
    {
        $html = (string) $this->blade('<x-ui.badge variant="seal" /><x-ui.badge variant="declared" />');

        $this->assertStringNotContainsStringIgnoringCase('verificada', $html);
    }

    public function test_dot_and_icon_keep_status_from_depending_on_color_alone(): void
    {
        $dotXpath = $this->renderUi('<x-ui.badge variant="success" dot>Ativa</x-ui.badge>');
        $dot = $this->uiElement($dotXpath, '//span[@data-slot="badge-dot"]');
        $this->assertSame('true', $dot->getAttribute('aria-hidden'));
        $this->assertHasClasses(['bg-current', 'rounded-full'], $dot);

        $iconXpath = $this->renderUi('<x-ui.badge variant="info" icon="clock">Agendado</x-ui.badge>');
        $icon = $this->uiElement($iconXpath, '//svg');
        $this->assertSame('0 0 20 20', $icon->getAttribute('viewbox'), 'Badge usa o ícone solid (mini).');
        $this->assertHasClasses(['size-3.5'], $icon);
        $this->assertSame(0, $this->uiCount($iconXpath, '//span[@data-slot="badge-dot"]'));
    }

    public function test_sizes_and_extra_classes(): void
    {
        $small = $this->uiElement($this->renderUi('<x-ui.badge size="sm" class="ml-2">Admin</x-ui.badge>'), '//span[@data-slot="badge"]');
        $large = $this->uiElement($this->renderUi('<x-ui.badge size="lg">Admin</x-ui.badge>'), '//span[@data-slot="badge"]');

        $this->assertHasClasses(['px-1.5', 'text-xs', 'ml-2'], $small);
        $this->assertHasClasses(['px-3', 'text-sm'], $large);
    }

    public function test_inline_components_add_no_whitespace_after_themselves(): void
    {
        foreach ([
            '<x-ui.badge variant="success">Ativa</x-ui.badge>' => '</span>',
            '<x-ui.spinner />' => '</span>',
            '<x-ui.avatar name="Ana Lima" />' => '</span>',
            '<x-ui.button>Salvar</x-ui.button>' => '</button>',
            '<x-ui.icon-button icon="x-mark" label="Fechar" />' => '</button>',
            '<x-ui.link href="/">Início</x-ui.link>' => '</a>',
        ] as $template => $closingTag) {
            $html = (string) $this->blade('('.$template.')');

            $this->assertStringStartsWith('(<', $html, "{$template} começa com espaço.");
            $this->assertStringEndsWith($closingTag.')', $html, "{$template} termina com espaço.");
        }
    }

    public function test_unknown_variant_is_rejected(): void
    {
        $this->assertUiRejects('<x-ui.badge variant="verified">Verificada</x-ui.badge>', 'x-ui.badge: variant "verified" não existe.');
    }
}
