<?php

namespace Tests\Feature\Ui;

use Tests\TestCase;

/**
 * Regras dos componentes de overlay e navegação x-ui.* (Fase 1 do redesign) e da ligação deles no
 * resources/js/app.js: cor só por token semântico, foco por focus-visible, movimento curto e com
 * motion-safe/motion-reduce, textos em pt-BR, e só os plugins do Preline que o app usa.
 */
class OverlayComponentsContractTest extends TestCase
{
    /**
     * @var list<string>
     */
    private const COMPONENTS = [
        'dialog', 'confirm-dialog', 'sheet', 'dropdown', 'dropdown-item', 'tabs', 'tab', 'tab-panel', 'toaster',
        'flash', 'nav', 'nav-item', 'tooltip',
    ];

    /**
     * init de resources/js/ui/<arquivo>.js que o app.js chama no carregamento.
     *
     * @var array<string, string>
     */
    private const UI_INITS = [
        'preline' => 'initPreline',
        'dialog' => 'initDialogs',
        'confirm' => 'initConfirm',
        'toast' => 'initToasts',
        'tabs' => 'initTabs',
        'tooltip' => 'initTooltips',
        'flash' => 'initFlash',
        'file-input' => 'initFileInputs',
        'password-toggle' => 'initPasswordToggles',
        'form-errors' => 'initFormErrors',
        'switch' => 'initSwitches',
        'submit-busy' => 'initSubmitBusy',
        'command' => 'initCommandPalettes',
        'copy' => 'initCopyButtons',
        'lightbox' => 'initLightboxes',
        'textarea-counter' => 'initTextareaCounters',
        'sidebar' => 'initSidebarToggles',
    ];

    public function test_every_component_documents_its_api_with_an_example(): void
    {
        foreach ($this->componentSources(withComments: true) as $name => $source) {
            $this->assertStringStartsWith('{{--', $source, "{$name}: comece pelo comentário com props, slots e exemplo.");
            $this->assertStringContainsString('Ex.:', $source, "{$name}: o comentário precisa de um exemplo de uso.");
            $this->assertStringContainsString('@props(', $source, "{$name}: declare as props com @props e valores padrão.");
        }
    }

    public function test_colors_come_from_semantic_tokens_not_the_raw_palette(): void
    {
        $palette = 'red|green|amber|blue|yellow|orange|teal|emerald|gray|zinc|slate|neutral|stone|sky|indigo|violet|purple|pink|rose|lime|cyan|fuchsia|wrench|automotive';
        $pattern = '/(?<![\w-])(?:[\w\[\]&>*:\/-]+:)?(?:bg|text|border|ring|outline|fill|stroke|from|via|to|divide|decoration|shadow|placeholder|caret|accent)-(?:'.$palette.')-\d{2,3}(?![\w-])|(?<![\w-])(?:bg|text|border)-(?:white|black)(?![\w-])/';

        foreach ($this->componentSources() as $name => $source) {
            preg_match_all($pattern, $source, $matches);

            $this->assertSame([], $matches[0], "{$name}: use tokens semânticos (bg-surface, text-muted-foreground, text-danger...).");
        }

        foreach (['toast', 'confirm'] as $script) {
            preg_match_all($pattern, file_get_contents(resource_path("js/ui/{$script}.js")), $matches);

            $this->assertSame([], $matches[0], "js/ui/{$script}.js: use tokens semânticos.");
        }
    }

    public function test_focus_uses_focus_visible_and_motion_is_short_and_respects_reduced_motion(): void
    {
        foreach ($this->componentSources() as $name => $source) {
            $this->assertDoesNotMatchRegularExpression('/(?<![\w-])focus:/', $source, "{$name}: anel de foco só com focus-visible.");
            $this->assertStringNotContainsString('transition-all', $source, "{$name}: anime propriedades específicas.");
            $this->assertStringNotContainsString('outline-none', $source, "{$name}: não remova o contorno de foco.");
            $this->assertDoesNotMatchRegularExpression('/(?<![\w-])duration-\d/', $source, "{$name}: use duration-fast/base/slow.");

            if (preg_match('/(?<![\w-])transition-(?!none)/', $source) === 1) {
                $this->assertMatchesRegularExpression('/motion-reduce:transition-none/', $source, "{$name}: toda transição precisa de motion-reduce:transition-none.");
            }
        }
    }

    public function test_no_emoji_and_accessible_names_are_in_portuguese(): void
    {
        foreach ($this->componentSources(withComments: true) as $name => $source) {
            $this->assertDoesNotMatchRegularExpression('/\p{Extended_Pictographic}/u', $source, "{$name}: sem emoji; use x-ui.icon.");
            $this->assertDoesNotMatchRegularExpression('/aria-label="(?:Close|Open|Menu|Dismiss)/', $source, "{$name}: rótulos acessíveis em pt-BR.");
        }

        foreach (array_keys(self::UI_INITS) as $script) {
            $this->assertDoesNotMatchRegularExpression('/\p{Extended_Pictographic}/u', file_get_contents(resource_path("js/ui/{$script}.js")), "js/ui/{$script}.js: sem emoji.");
        }
    }

    public function test_app_js_initialises_every_ui_module_and_no_longer_loads_the_whole_preline_bundle(): void
    {
        $app = file_get_contents(resource_path('js/app.js'));

        $this->assertDoesNotMatchRegularExpression("/^import .*'preline'/m", $app, 'O pacote inteiro do Preline saiu: use resources/js/ui/preline.js.');
        $this->assertStringNotContainsString('HSStaticMethods', $app);

        foreach (self::UI_INITS as $file => $function) {
            $this->assertStringContainsString("import { {$function} } from './ui/{$file}';", $app);
            $this->assertMatchesRegularExpression('/^\s+'.$function.'\(\);$/m', $app, "app.js precisa chamar {$function}().");
            $this->assertFileExists(resource_path("js/ui/{$file}.js"));
            $this->assertMatchesRegularExpression('/export function '.$function.'\(/', file_get_contents(resource_path("js/ui/{$file}.js")));
        }

        $this->assertStringContainsString("document.readyState === 'loading'", $app);
    }

    /**
     * As entradas automáticas do Preline (dist/overlay.mjs) não têm export e o package.json as marca
     * como sem side effects: o build descartaria um import só por efeito. As "non-auto" exportam a
     * classe, e o app inicializa por conta própria.
     */
    public function test_preline_plugins_come_from_the_non_auto_entries_that_the_build_keeps(): void
    {
        $preline = file_get_contents(resource_path('js/ui/preline.js'));
        $package = json_decode(file_get_contents(base_path('node_modules/preline/package.json')), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('./dist/*.mjs', $package['exports']['./plugins/*']['import']);

        foreach (['overlay' => 'HSOverlay', 'dropdown' => 'HSDropdown'] as $plugin => $class) {
            $this->assertStringContainsString("import {$class} from 'preline/plugins/{$plugin}-non-auto';", $preline);
            $this->assertMatchesRegularExpression('/export\s*\{\s*\w+ as default\s*\}/', file_get_contents(base_path("node_modules/preline/dist/{$plugin}-non-auto.mjs")));
            $this->assertStringContainsString("{$class}.autoInit();", $preline);
        }

        $this->assertDoesNotMatchRegularExpression("/^import 'preline/m", $preline, 'Import só por efeito some no build.');
        $this->assertStringNotContainsString('preline/plugins/tabs', $preline, 'Abas usam resources/js/ui/tabs.js.');
        $this->assertStringNotContainsString('preline/plugins/tooltip', $preline, 'Dicas usam resources/js/ui/tooltip.js.');
    }

    /**
     * O Preline 4.2 engole o Enter de links e botões dentro de overlay e dropdown; preline.js devolve
     * a ativação nativa e prende o Shift+Tab no painel aberto.
     */
    public function test_preline_keyboard_fixes_are_in_place(): void
    {
        $preline = file_get_contents(resource_path('js/ui/preline.js'));

        $this->assertStringContainsString("elementsWithin(root, '.hs-overlay, .hs-dropdown-menu').forEach(keepNativeActivation);", $preline);
        $this->assertStringContainsString("event.key === 'Enter'", $preline);
        $this->assertStringContainsString('event.stopPropagation();', $preline);
        $this->assertStringContainsString("event.key === 'Tab' && event.shiftKey", $preline);
        $this->assertStringContainsString("'.hs-overlay[data-ui-sheet][data-close-from]'", $preline);
    }

    /**
     * @return array<string, string>
     */
    private function componentSources(bool $withComments = false): array
    {
        $sources = [];

        foreach (self::COMPONENTS as $component) {
            $path = resource_path("views/components/ui/{$component}.blade.php");

            $this->assertFileExists($path);

            $source = file_get_contents($path);

            if (! $withComments) {
                $source = (string) preg_replace('/\{\{--.*?--\}\}/s', '', $source);
                $source = (string) preg_replace('#^\s*//.*$#m', '', $source);
            }

            $sources[$component] = $source;
        }

        return $sources;
    }
}
