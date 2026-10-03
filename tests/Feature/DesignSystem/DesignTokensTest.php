<?php

namespace Tests\Feature\DesignSystem;

use Tests\TestCase;

/**
 * resources/css/app.css only reaches the browser after a Vite build, so no page test sees it.
 * These checks pin the design-system contract other views rely on (tokens, focus, forms plugin
 * strategy and the dark variant) so a later edit cannot silently undo it.
 */
class DesignTokensTest extends TestCase
{
    /**
     * Papéis semânticos que views e componentes x-ui.* usam.
     *
     * @var list<string>
     */
    private const SEMANTIC_COLORS = [
        'background', 'surface', 'surface-muted', 'foreground', 'muted-foreground', 'subtle-foreground',
        'border', 'border-strong', 'input', 'ring', 'primary', 'primary-hover', 'primary-foreground',
        'link', 'link-hover', 'accent', 'accent-foreground', 'accent-border', 'danger', 'danger-hover',
        'danger-soft', 'danger-foreground', 'success', 'success-soft', 'warning', 'warning-soft', 'info',
        'info-soft', 'overlay', 'sidebar', 'sidebar-foreground', 'sidebar-muted', 'sidebar-active',
        'sidebar-border', 'prov-verified', 'prov-verified-surface', 'prov-declared', 'prov-declared-surface',
    ];

    /**
     * Papéis que mudam dentro de .theme-inverse (e voltam em .theme-default). Primary, sidebar,
     * overlay e procedência são iguais nos dois lados.
     *
     * @var list<string>
     */
    private const SCOPED_COLORS = [
        'background', 'surface', 'surface-muted', 'foreground', 'muted-foreground', 'subtle-foreground',
        'border', 'border-strong', 'input', 'ring', 'link', 'link-hover', 'accent', 'accent-foreground',
        'accent-border', 'danger', 'danger-hover', 'danger-soft', 'danger-foreground', 'success',
        'success-soft', 'warning', 'warning-soft', 'info', 'info-soft',
    ];

    /**
     * Pares texto/fundo que precisam de 4,5:1 (texto) ou 3:1 (borda de controle e foco), medidos
     * sobre os valores resolvidos de cada escopo. Status (red, green, amber) vêm da paleta do
     * Tailwind em oklch e têm o contraste anotado no app.css.
     *
     * @var list<array{0: string, 1: string, 2: float}>
     */
    private const CONTRAST_PAIRS = [
        ['foreground', 'background', 4.5],
        ['foreground', 'surface', 4.5],
        ['foreground', 'surface-muted', 4.5],
        ['muted-foreground', 'background', 4.5],
        ['muted-foreground', 'surface', 4.5],
        ['muted-foreground', 'surface-muted', 4.5],
        ['subtle-foreground', 'background', 4.5],
        ['subtle-foreground', 'surface', 4.5],
        ['link', 'background', 4.5],
        ['link', 'surface', 4.5],
        ['link', 'surface-muted', 4.5],
        ['link-hover', 'surface', 4.5],
        ['ring', 'background', 3.0],
        ['ring', 'surface', 3.0],
        ['ring', 'surface-muted', 3.0],
        ['input', 'background', 3.0],
        ['input', 'surface', 3.0],
        ['primary-foreground', 'primary', 4.5],
        ['primary-foreground', 'primary-hover', 4.5],
        ['accent-foreground', 'accent', 4.5],
        ['info', 'info-soft', 4.5],
    ];

    private function stylesheet(): string
    {
        return file_get_contents(resource_path('css/app.css'));
    }

    /**
     * Body of the first rule whose selector list ends with the given selector.
     */
    private function ruleBody(string $selector): string
    {
        $matched = preg_match('/'.preg_quote($selector, '/').'\s*\{([^{}]*)\}/', $this->stylesheet(), $matches);

        $this->assertSame(1, $matched, "Rule {$selector} not found in app.css.");

        return $matches[1];
    }

    public function test_forms_plugin_keeps_the_base_strategy_so_it_does_not_override_form_components(): void
    {
        $this->assertMatchesRegularExpression(
            "/@plugin\s+'@tailwindcss\/forms'\s*\{\s*strategy:\s*'base';\s*\}/",
            $this->stylesheet(),
        );
    }

    public function test_dark_variant_only_applies_under_an_explicit_dark_class(): void
    {
        $this->assertStringContainsString('@custom-variant dark (&:where(.dark, .dark *));', $this->stylesheet());
    }

    public function test_pagination_no_longer_scans_the_framework_vendor_view(): void
    {
        $this->assertStringNotContainsString('Illuminate/Pagination/resources/views', $this->stylesheet());
        $this->assertFileExists(resource_path('views/vendor/pagination/tailwind.blade.php'));
        $this->assertFileExists(resource_path('views/vendor/pagination/simple-tailwind.blade.php'));
    }

    public function test_theme_defines_link_ring_and_missing_wrench_step_without_changing_the_palette(): void
    {
        $stylesheet = $this->stylesheet();

        $this->assertStringContainsString('--color-link: var(--color-wrench-800);', $stylesheet);
        $this->assertStringContainsString('--color-ring: var(--color-wrench-800);', $stylesheet);
        $this->assertStringContainsString('--color-wrench-300: #7ddfd5;', $stylesheet);
        $this->assertStringContainsString('--color-wrench-500: #2ec4b6;', $stylesheet);
        $this->assertStringContainsString('--color-wrench-800: #186b64;', $stylesheet);
        $this->assertStringContainsString('--color-automotive-400: #7a91a8;', $stylesheet);
    }

    public function test_focus_is_visible_globally_and_switches_to_light_teal_on_dark_surfaces(): void
    {
        $globalFocus = $this->ruleBody(':where(a, button, input, select, textarea, summary, [tabindex]):focus-visible');
        $this->assertStringContainsString('outline: 2px solid var(--color-ring);', $globalFocus);
        $this->assertStringContainsString('outline-offset: 2px;', $globalFocus);

        $inverse = $this->ruleBody('.theme-inverse');
        $this->assertStringContainsString('--color-ring: var(--color-wrench-400);', $inverse);
        $this->assertStringContainsString('--color-link: var(--color-wrench-400);', $inverse);
    }

    /**
     * Os botões são o <x-ui.button> (e o espelho resources/js/ui/button.js): as classes .btn-* do
     * app.css saíram quando o último uso foi migrado.
     */
    public function test_buttons_use_focus_visible_outline_instead_of_fixed_light_ring_offset(): void
    {
        foreach (['views/components/ui/button.blade.php', 'js/ui/button.js'] as $source) {
            $body = file_get_contents(resource_path($source));

            $this->assertStringContainsString('focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring', $body, "{$source} has no keyboard focus outline.");
            $this->assertDoesNotMatchRegularExpression('/(?<![\w-])focus:ring/', $body, "{$source} still shows a ring on mouse focus.");
            $this->assertStringNotContainsString('ring-offset-automotive-50', $body);
        }

        foreach (['.btn-primary', '.btn-secondary', '.btn-danger'] as $alias) {
            $this->assertStringNotContainsString($alias, $this->stylesheet());
        }

        // O destrutivo segue em red-700, pelo papel danger.
        $this->assertStringContainsString("'danger' => 'bg-danger text-danger-foreground", file_get_contents(resource_path('views/components/ui/button.blade.php')));
        $this->assertSame('var(--color-red-700)', $this->lightTokens()['danger']);
    }

    public function test_form_fields_have_three_to_one_borders_select_arrow_room_and_disabled_state(): void
    {
        foreach (['.form-input', '.form-select'] as $field) {
            $body = $this->ruleBody($field);

            $this->assertStringContainsString('border-input', $body);
            $this->assertStringNotContainsString('border-automotive-300', $body);
            $this->assertStringContainsString('focus:border-ring', $body);
            $this->assertStringContainsString('disabled:cursor-not-allowed', $body);
            $this->assertStringContainsString('disabled:bg-surface-muted', $body);

            // 16px no celular (sem o zoom do iOS ao focar) e 14px a partir de sm.
            $this->assertMatchesRegularExpression('/(?<![\w:-])text-base(?![\w-])/', $body);
            $this->assertStringContainsString('sm:text-sm', $body);
            $this->assertDoesNotMatchRegularExpression('/(?<![\w:-])text-sm(?![\w-])/', $body);
        }

        // A borda continua em automotive-400 (3,26:1) e o desabilitado em automotive-100.
        $this->assertSame('var(--color-automotive-400)', $this->lightTokens()['input']);
        $this->assertSame('var(--color-automotive-100)', $this->lightTokens()['surface-muted']);

        $select = $this->ruleBody('.form-select');
        $this->assertStringContainsString('pr-10', $select);
        $this->assertStringContainsString('pl-3', $select);
        $this->assertStringNotContainsString('px-3', $select);
    }

    public function test_link_badge_and_blog_links_use_accessible_text_colors(): void
    {
        $this->assertStringContainsString('text-link', $this->ruleBody('.link'));

        // Links do artigo e dos documentos legais: a mesma regra (.blog-content a, .doc-content a).
        $this->assertMatchesRegularExpression('/\.blog-content a,\s*\.doc-content a\s*\{/', $this->stylesheet());
        $readingLinks = $this->ruleBody('.doc-content a');
        $this->assertStringContainsString('text-link', $readingLinks);
        $this->assertStringContainsString('hover:text-link-hover', $readingLinks);
        $this->assertStringNotContainsString('text-wrench-700', $readingLinks);

        // O badge de destaque da marca é o <x-ui.badge variant="primary"> (o .badge-orange saiu).
        $badge = file_get_contents(resource_path('views/components/ui/badge.blade.php'));
        $this->assertStringContainsString("'primary' => 'border-accent-border bg-accent text-accent-foreground'", $badge);
        $this->assertStringNotContainsString('text-wrench-600', $badge);
        $this->assertStringNotContainsString('.badge-orange', $this->stylesheet());
        $this->assertSame('var(--color-wrench-800)', $this->lightTokens()['accent-foreground']);
    }

    public function test_skip_link_is_hidden_until_focused(): void
    {
        $body = $this->ruleBody('.skip-link');

        $this->assertStringContainsString('sr-only', $body);
        $this->assertStringContainsString('focus:not-sr-only', $body);
        $this->assertStringContainsString('focus:fixed', $body);
    }

    public function test_motion_has_tokens_pausable_marquee_and_reduced_motion_fallback(): void
    {
        $stylesheet = $this->stylesheet();

        $this->assertStringContainsString('--duration-hero: 500ms;', $stylesheet);
        $this->assertStringContainsString('--ease-smooth-out: cubic-bezier(0.22, 1, 0.36, 1);', $stylesheet);
        $this->assertStringContainsString('.landing-marquee:focus-within .landing-marquee-track', $stylesheet);
        $this->assertStringContainsString('.landing-marquee:hover .landing-marquee-track', $stylesheet);

        $reducedMotionStart = strpos($stylesheet, '@media (prefers-reduced-motion: reduce)');
        $this->assertNotFalse($reducedMotionStart);

        $reducedMotion = substr($stylesheet, $reducedMotionStart);
        $this->assertStringContainsString('.landing-marquee-track', $reducedMotion);
        $this->assertStringContainsString('scroll-behavior: auto !important;', $reducedMotion);
        $this->assertStringContainsString('transition-duration: 0.01ms !important;', $reducedMotion);
    }

    public function test_semantic_tokens_live_in_a_static_theme_so_they_always_reach_root(): void
    {
        $tokens = $this->lightTokens();

        foreach (self::SEMANTIC_COLORS as $name) {
            $this->assertArrayHasKey($name, $tokens, "--color-{$name} não está no @theme static.");
        }

        $this->assertSame('var(--color-automotive-50)', $tokens['background']);
        $this->assertSame('#ffffff', $tokens['surface']);
        $this->assertSame('var(--color-automotive-900)', $tokens['foreground']);
        $this->assertSame('var(--color-automotive-600)', $tokens['muted-foreground']);
        $this->assertSame('var(--color-automotive-500)', $tokens['subtle-foreground']);
        $this->assertSame('var(--color-automotive-200)', $tokens['border']);
        $this->assertSame('var(--color-wrench-500)', $tokens['primary']);
        $this->assertSame('var(--color-automotive-950)', $tokens['primary-foreground']);
        $this->assertSame('var(--color-red-50)', $tokens['danger-soft']);
        $this->assertSame('#ffffff', $tokens['danger-foreground']);
        $this->assertSame('var(--color-green-800)', $tokens['success']);
        $this->assertSame('var(--color-amber-900)', $tokens['warning']);
        $this->assertSame('var(--color-automotive-950)', $tokens['sidebar']);
    }

    public function test_provenance_tokens_mirror_provenance_css(): void
    {
        $provenance = file_get_contents(resource_path('css/provenance.css'));
        $tokens = $this->lightTokens();

        $this->assertMatchesRegularExpression('/\.prov-verified\s*\{\s*--prov-ink:\s*#0f766e;\s*--prov-surface:\s*#ffffff;/', $provenance);
        $this->assertMatchesRegularExpression('/\.prov-declared\s*\{\s*--prov-ink:\s*#92400e;\s*--prov-surface:\s*#fffbeb;/', $provenance);
        $this->assertSame('#0f766e', $tokens['prov-verified']);
        $this->assertSame('#ffffff', $tokens['prov-verified-surface']);
        $this->assertSame('#92400e', $tokens['prov-declared']);
        $this->assertSame('#fffbeb', $tokens['prov-declared-surface']);
    }

    public function test_theme_inverse_redefines_the_scoped_roles_and_theme_default_restores_the_light_values(): void
    {
        $light = $this->lightTokens();
        $inverse = $this->declarations($this->ruleBody('.theme-inverse'));
        $default = $this->declarations($this->ruleBody('.theme-default'));

        $this->assertEqualsCanonicalizing(self::SCOPED_COLORS, array_keys($inverse));
        $this->assertEqualsCanonicalizing(self::SCOPED_COLORS, array_keys($default));

        foreach (self::SCOPED_COLORS as $name) {
            $this->assertSame($light[$name], $default[$name], ".theme-default --color-{$name} diverge do @theme.");

            // input fica em automotive-400 nos dois lados (3,26:1 no branco, 4,72:1 em automotive-900).
            if ($name !== 'input') {
                $this->assertNotSame($light[$name], $inverse[$name], ".theme-inverse não muda --color-{$name}.");
            }
        }

        $this->assertSame('var(--color-automotive-900)', $inverse['surface']);
        $this->assertSame('#ffffff', $inverse['foreground']);
        $this->assertSame('var(--color-automotive-300)', $inverse['muted-foreground']);
        $this->assertSame('var(--color-automotive-400)', $inverse['subtle-foreground']);
        $this->assertSame('var(--color-red-300)', $inverse['danger']);
        $this->assertSame('var(--color-automotive-950)', $inverse['danger-foreground']);
    }

    public function test_semantic_text_and_control_pairs_meet_wcag_aa_in_both_scopes(): void
    {
        $light = $this->lightTokens();
        $inverse = array_merge($light, $this->declarations($this->ruleBody('.theme-inverse')));

        foreach (['claro' => $light, '.theme-inverse' => $inverse] as $scope => $tokens) {
            foreach (self::CONTRAST_PAIRS as [$foreground, $background, $minimum]) {
                $backdrop = $this->resolveColor($tokens['background'], [1.0, 1.0, 1.0]);
                $backgroundColor = $this->resolveColor($tokens[$background], $backdrop);
                $foregroundColor = $this->resolveColor($tokens[$foreground], $backgroundColor);
                $ratio = $this->contrastRatio($foregroundColor, $backgroundColor);

                $this->assertGreaterThanOrEqual(
                    $minimum,
                    round($ratio, 2),
                    sprintf('%s: %s sobre %s dá %.2f:1 (mínimo %.1f:1).', $scope, $foreground, $background, $ratio, $minimum),
                );
            }
        }

        foreach (['sidebar-foreground', 'sidebar-muted', 'sidebar-active'] as $sidebarText) {
            $sidebar = $this->resolveColor($light['sidebar'], [1.0, 1.0, 1.0]);

            $this->assertGreaterThanOrEqual(4.5, $this->contrastRatio($this->resolveColor($light[$sidebarText], $sidebar), $sidebar), "{$sidebarText} sobre sidebar.");
        }

        // subtle-foreground sobre surface-muted reprova (4,24:1): o app.css proíbe esse par.
        $this->assertLessThan(4.5, $this->contrastRatio(
            $this->resolveColor($light['subtle-foreground'], [1.0, 1.0, 1.0]),
            $this->resolveColor($light['surface-muted'], [1.0, 1.0, 1.0]),
        ));
    }

    public function test_components_are_built_on_the_semantic_roles_without_raw_palette(): void
    {
        $expectations = [
            '.card' => ['rounded-card', 'border-border', 'bg-surface'],
            '.form-label' => ['text-foreground'],
            '.form-input' => ['rounded-control', 'border-input', 'bg-surface', 'text-foreground', 'placeholder:text-subtle-foreground', 'focus:ring-ring/30'],
            '.form-select' => ['rounded-control', 'border-input', 'bg-surface', 'text-foreground'],
        ];

        foreach ($expectations as $selector => $classes) {
            $body = $this->ruleBody($selector);

            foreach ($classes as $class) {
                $this->assertMatchesRegularExpression('/(?<![\w:\/-])'.preg_quote($class, '/').'(?![\w\/-])/', $body, "{$selector} sem {$class}.");
            }

            $this->assertDoesNotMatchRegularExpression('/-(?:automotive|wrench|red|white)(?:-|\b)/', $body, "{$selector} ainda usa a paleta crua.");
        }

        // O badge brand troca de cor dentro de .theme-inverse pelos papéis, sem regra própria.
        $this->assertStringNotContainsString('.theme-inverse .badge-accent', $this->stylesheet());
    }

    public function test_radius_and_duration_tokens(): void
    {
        $theme = $this->staticTheme();
        $stylesheet = $this->stylesheet();

        $this->assertStringContainsString('--radius-control: 0.5rem;', $theme);
        $this->assertStringContainsString('--radius-card: 0.75rem;', $theme);
        $this->assertStringContainsString('--radius-overlay: 1rem;', $theme);
        $this->assertStringContainsString('--ease-smooth-out: cubic-bezier(0.22, 1, 0.36, 1);', $theme);

        foreach (['fast' => '150ms', 'base' => '200ms', 'slow' => '250ms'] as $name => $duration) {
            $this->assertStringContainsString("--duration-{$name}: {$duration};", $stylesheet);
            $this->assertStringContainsString("--transition-duration-{$name}: var(--duration-{$name});", $theme);
        }
    }

    /**
     * Corpo do bloco @theme static (papéis semânticos, raios, easing e durações).
     */
    private function staticTheme(): string
    {
        $matched = preg_match('/@theme static\s*\{(.*?)\n\}/s', $this->stylesheet(), $matches);

        $this->assertSame(1, $matched, 'Bloco @theme static não encontrado em app.css.');

        return $matches[1];
    }

    /**
     * @return array<string, string> Valores claros dos --color-* do @theme static, sem o prefixo.
     */
    private function lightTokens(): array
    {
        return $this->declarations($this->staticTheme());
    }

    /**
     * @return array<string, string>
     */
    private function declarations(string $body): array
    {
        $body = preg_replace('#/\*.*?\*/#s', '', $body) ?? $body;
        preg_match_all('/--color-([\w-]+):\s*([^;]+);/', $body, $matches, PREG_SET_ORDER);

        $declarations = [];

        foreach ($matches as [, $name, $value]) {
            $declarations[$name] = trim($value);
        }

        return $declarations;
    }

    /**
     * Resolve um valor de token para RGB (0 a 1) já composto sobre $backdrop. Entende hex,
     * var(--color-automotive|wrench-*) (paleta hex do app.css) e
     * color-mix(in oklab, var(...) N%, transparent).
     *
     * @param  array{0: float, 1: float, 2: float}  $backdrop
     * @return array{0: float, 1: float, 2: float}
     */
    private function resolveColor(string $value, array $backdrop): array
    {
        if (preg_match('/^color-mix\(in oklab, (var\(--color-[\w-]+\)) (\d+)%, transparent\)$/', $value, $mix)) {
            $color = $this->resolveColor($mix[1], $backdrop);
            $alpha = ((int) $mix[2]) / 100;

            return [
                $alpha * $color[0] + (1 - $alpha) * $backdrop[0],
                $alpha * $color[1] + (1 - $alpha) * $backdrop[1],
                $alpha * $color[2] + (1 - $alpha) * $backdrop[2],
            ];
        }

        if (preg_match('/^var\(--color-((?:automotive|wrench)-\d+)\)$/', $value, $palette)) {
            $matched = preg_match('/--color-'.preg_quote($palette[1], '/').':\s*(#[0-9a-f]{6});/i', $this->stylesheet(), $hex);
            $this->assertSame(1, $matched, "Degrau {$palette[1]} não encontrado na paleta.");

            return $this->resolveColor($hex[1], $backdrop);
        }

        $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/i', $value, "Valor {$value} fora da paleta da marca: o teste de contraste não o resolve.");

        return array_map(fn (string $channel): float => hexdec($channel) / 255, str_split(substr($value, 1), 2));
    }

    /**
     * @param  array{0: float, 1: float, 2: float}  $first
     * @param  array{0: float, 1: float, 2: float}  $second
     */
    private function contrastRatio(array $first, array $second): float
    {
        $luminance = function (array $rgb): float {
            [$red, $green, $blue] = array_map(
                fn (float $channel): float => $channel <= 0.04045 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4,
                $rgb,
            );

            return 0.2126 * $red + 0.7152 * $green + 0.0722 * $blue;
        };

        $lighter = max($luminance($first), $luminance($second));
        $darker = min($luminance($first), $luminance($second));

        return ($lighter + 0.05) / ($darker + 0.05);
    }
}
