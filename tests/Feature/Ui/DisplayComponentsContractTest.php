<?php

namespace Tests\Feature\Ui;

use Tests\TestCase;

/**
 * Regras que valem para todos os componentes de exibição x-ui.* (Fase 1 do redesign): cor só por
 * token semântico, foco por focus-visible, movimento curto e sempre com motion-safe/motion-reduce,
 * sem emoji e sem corte de imagem.
 */
class DisplayComponentsContractTest extends TestCase
{
    /**
     * @var list<string>
     */
    private const COMPONENTS = [
        'alert', 'avatar', 'badge', 'breadcrumb', 'button', 'card', 'container', 'empty-state', 'icon-button',
        'link', 'page-header', 'section', 'segmented', 'skeleton', 'spinner', 'stat', 'stepper', 'table',
    ];

    public function test_every_component_exists_and_documents_its_api(): void
    {
        foreach ($this->sources(withComments: true) as $name => $source) {
            $this->assertStringStartsWith('{{--', $source, "{$name}: comece pelo comentário com props, slots e exemplo.");
            $this->assertStringContainsString('Ex.:', $source, "{$name}: o comentário precisa de um exemplo de uso.");
            $this->assertStringContainsString('@props(', $source, "{$name}: declare as props com @props e valores padrão.");
        }
    }

    public function test_colors_come_from_semantic_tokens_not_the_raw_palette(): void
    {
        $palette = 'red|green|amber|blue|yellow|orange|teal|emerald|gray|zinc|slate|neutral|stone|sky|indigo|violet|purple|pink|rose|lime|cyan|fuchsia|wrench|automotive';
        $pattern = '/(?<![\w-])(?:[\w\[\]&>*:-]+:)?(?:bg|text|border|ring|outline|fill|stroke|from|via|to|divide|decoration|shadow|placeholder|caret|accent)-(?:'.$palette.')-\d{2,3}(?![\w-])/';

        foreach ($this->sources() as $name => $source) {
            preg_match_all($pattern, $source, $matches);

            $this->assertSame([], $matches[0], "{$name}: use tokens semânticos (bg-surface, text-muted-foreground, text-danger...).");
        }
    }

    public function test_focus_uses_focus_visible_and_motion_respects_reduced_motion(): void
    {
        foreach ($this->sources() as $name => $source) {
            $this->assertDoesNotMatchRegularExpression('/(?<![\w-])focus:/', $source, "{$name}: anel de foco só com focus-visible.");
            $this->assertStringNotContainsString('transition-all', $source, "{$name}: anime propriedades específicas, nunca transition-all.");
            $this->assertDoesNotMatchRegularExpression('/(?<![\w-])duration-\d/', $source, "{$name}: use duration-fast/base/slow.");
            $this->assertDoesNotMatchRegularExpression('/(?<!motion-safe:)(?<![\w-])animate-(?!none)/', $source, "{$name}: animação contínua só com motion-safe:.");
            preg_match_all('/[^\s\'"]*hover:-?translate[^\s\'"]*/', $source, $hoverMoves);

            foreach ($hoverMoves[0] as $hoverMove) {
                $this->assertStringStartsWith('motion-safe:', $hoverMove, "{$name}: deslocamento no hover só com motion-safe:.");
            }

            if (preg_match('/(?<![\w-])transition-(?!none)/', $source) === 1) {
                $this->assertMatchesRegularExpression('/motion-reduce:(?:\[[^\]]+\]:)?transition-none/', $source, "{$name}: toda transição precisa de motion-reduce:transition-none.");
            }
        }
    }

    public function test_no_emoji_and_no_image_cropping(): void
    {
        foreach ($this->sources() as $name => $source) {
            $this->assertDoesNotMatchRegularExpression('/\p{Extended_Pictographic}/u', $source, "{$name}: sem emoji; use x-ui.icon.");
            $this->assertStringNotContainsString('object-cover', $source, "{$name}: imagem sem corte (object-contain).");
            $this->assertDoesNotMatchRegularExpression('/\sdownload(?:=|\s|>)/', $source, "{$name}: link nunca leva o atributo download.");
        }
    }

    /**
     * Código dos componentes. Sem $withComments, tira os comentários Blade e PHP (a documentação
     * cita classes e atributos proibidos para explicar a regra).
     *
     * @return array<string, string>
     */
    private function sources(bool $withComments = false): array
    {
        $sources = [];

        foreach (self::COMPONENTS as $component) {
            $path = resource_path("views/components/ui/{$component}.blade.php");

            $this->assertFileExists($path);
            $source = (string) file_get_contents($path);

            if (! $withComments) {
                $source = (string) preg_replace(['/\{\{--.*?--\}\}/s', '#^\s*//.*$#m'], '', $source);
            }

            $sources[$component] = $source;
        }

        return $sources;
    }
}
