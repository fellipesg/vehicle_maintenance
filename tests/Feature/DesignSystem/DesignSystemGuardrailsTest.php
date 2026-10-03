<?php

namespace Tests\Feature\DesignSystem;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Catraca do design system: conta, em resources/views e resources/js, padrões que o review de UI/UX
 * tirou do app e falha se a contagem passar do teto medido na última varredura (Fase 3, 29/09/2026).
 * Também trava o movimento: durações só dos tokens, laço e deslocamento só com motion-safe.
 * Também confere o template de página dos portais (um H1 só, do x-ui.page-header) e que o portal do
 * proprietário não volta a montar telas pela API com "Carregando…".
 *
 * Os tetos só descem. Removeu mais um uso? Baixe a constante (ou a linha do arquivo no mapa) no
 * mesmo commit; arquivo que chegou a zero sai do mapa. Precisa de um uso novo? Não suba o teto: use
 * a alternativa indicada em cada teste. Arquivo novo entra com zero. As regras estão em
 * .ai/rules/ui-components.md e .ai/rules/design-tokens.md.
 */
class DesignSystemGuardrailsTest extends TestCase
{
    /**
     * text-wrench-600 reprova o AA como texto sobre fundo claro (2,90:1) e também como preenchimento
     * de checkbox marcado (text-* pinta o checkbox). Checkbox usa text-wrench-700 (4,20:1).
     */
    private const MAX_TEXT_WRENCH_600 = 0;

    /**
     * Emoji como ícone em título, chip ou botão muda de desenho em cada sistema. Usar
     * <x-ui.icon> / icon() de resources/js/ui/icons.js, ou só o texto.
     */
    private const MAX_EMOJI_ICONS = 0;

    /**
     * confirm() nativo não segue o visual do app nem o idioma dos botões. Ação destrutiva usa
     * data-confirm com <x-ui.confirm-dialog> (ou confirmAction() de resources/js/ui/confirm.js).
     */
    private const MAX_NATIVE_CONFIRM = 0;

    /**
     * alert() nativo idem: aviso de erro usa toast({ variant: 'error' }) de resources/js/ui/toast.js
     * ou <x-ui.alert> na página.
     */
    private const MAX_NATIVE_ALERT = 0;

    /**
     * object-cover corta a imagem. Capa de veículo nunca é cortada (.ai/rules/components.md): só
     * a miniatura (thumb) usa object-cover. Foto de serviço, logo de oficina e capa do blog (card,
     * artigo e prévia no admin) aparecem inteiras com object-contain.
     */
    private const MAX_OBJECT_COVER_OUTSIDE_THUMB = 0;

    /**
     * transition-all anima propriedades de layout e deixa a interface lenta. Liste as propriedades
     * (transition-colors, transition-[opacity,translate]) com duration-fast|base|slow e
     * ease-smooth-out, sempre com motion-reduce:transition-none. No Tailwind 4 translate-*, scale-*
     * e rotate-* escrevem translate, scale e rotate, não transform (MotionTransitionPropertiesTest).
     */
    private const MAX_TRANSITION_ALL = 0;

    /**
     * Texto em tamanho arbitrário abaixo de text-xs (text-[10px], text-[11px], text-[0.6rem]) fica
     * ilegível no celular. O menor tamanho é text-xs (12px), também nos mocks de celular da landing,
     * que usam as capturas reais do app em vez de texto miúdo.
     */
    private const MAX_TINY_ARBITRARY_TEXT = 0;

    /**
     * Toda página do admin desenha o único <h1> com <x-ui.page-header> (o título da topbar é a
     * trilha, não um cabeçalho).
     */
    private const MAX_ADMIN_H1_OUTSIDE_PAGE_HEADER = 0;

    /**
     * Cor crua da paleta do Tailwind (red-600, gray-500, amber-50...) por arquivo. Cor vem dos papéis
     * semânticos de resources/css/app.css: text-muted-foreground, bg-surface, border-input,
     * bg-danger-soft text-danger, bg-success-soft text-success, bg-warning-soft text-warning,
     * bg-info-soft text-info.
     *
     * @var array<string, int>
     */
    private const RAW_PALETTE_CEILINGS = [
        // Mock escuro da linha do tempo no hero da landing: "Declarada" em âmbar sobre fundo escuro.
        'views/components/landing/phone-timeline.blade.php' => 3,
    ];

    /**
     * '!' (important) em atributo class por arquivo: sobrescrever o componente à força esconde que
     * falta uma variante. Use a prop do x-ui.* (size="sm", variant) ou, dentro de .theme-inverse,
     * deixe os tokens trocarem de valor sozinhos.
     *
     * @var array<string, int>
     */
    private const IMPORTANT_OVERRIDE_CEILINGS = [];

    /**
     * Telas dos portais (Proprietário, Lojista, Oficina, Admin, Minha conta, notificações), o
     * assistente "Adicionar veículo" e as páginas públicas do produto (busca e /verificar). Cada
     * página segue o template: o único <h1> vem de <x-ui.page-header> (ou de <x-vehicle.detail>,
     * que o desenha), com trilha e ações; nunca um <h1> solto na view.
     *
     * @var list<string>
     */
    private const PORTAL_VIEW_DIRECTORIES = [
        'views/account/',
        'views/admin/',
        'views/garage/',
        'views/maintenances/',
        'views/notifications/',
        'views/public/',
        'views/user/',
        'views/vehicles/',
        'views/workshop/',
    ];

    /**
     * Portal do proprietário renderizado no servidor (resources/js/user-portal.js só acrescenta
     * interatividade): as views e os scripts dele não têm "Carregando". Envio usa o loading-label
     * do x-ui.button ("Salvando…"), e lista vazia usa x-ui.empty-state.
     *
     * @var list<string>
     */
    private const OWNER_SOURCES_WITHOUT_LOADING = [
        'views/user/',
        'js/user-portal.js',
        'js/owner-maintenance-form.js',
        'js/maintenance-kilometer-range.js',
    ];

    /**
     * Matizes da paleta padrão do Tailwind 4. automotive e wrench são as escalas da marca.
     */
    private const RAW_HUES = 'slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose';

    /**
     * Emoji que já foram usados como ícone nas telas e não têm apresentação de emoji por padrão (os
     * demais o teste acha por \p{Emoji_Presentation}). ✓ (U+2713) e ○ ficam de fora: são glifos de
     * texto nas listas de benefícios e nos critérios de senha, sempre com aria-hidden.
     *
     * @var list<string>
     */
    private const EMOJI_ICONS = ['🚗', '🔧', '🏷', '📝', '📄', '🏠', '🏪', '🏭', '🔍', '📞', '✉', '✏', '🧾'];

    public function test_teal_600_is_not_used_as_text_or_checkbox_color(): void
    {
        $count = $this->countMatches('/(?<![\w-])text-wrench-600(?![\w-])/');

        $this->assertLessThanOrEqual(
            self::MAX_TEXT_WRENCH_600,
            $count,
            'text-wrench-600 sobre fundo claro dá 2,90:1. Para link ou texto de ação use class="link" ou text-link; em checkbox, text-wrench-700.',
        );
    }

    /**
     * O indicador de foco precisa de 3:1 (WCAG 1.4.11). O contorno global e os campos já usam
     * --color-ring; recolorir o anel ou o contorno com wrench-500/600 (2,17:1 e 2,90:1 no branco)
     * derruba o contraste. Use focus:ring-ring ou deixe o contorno global.
     */
    public function test_focus_ring_and_outline_are_not_recolored_with_light_teal(): void
    {
        $this->assertSame(
            0,
            $this->countMatches('/(?<![\w-])focus(?:-visible|-within)?:(?:ring|outline)-wrench-[1-6]00(?![\w-])/'),
            'Foco em wrench-100 a 600 fica abaixo de 3:1 no fundo claro: use focus:ring-ring ou o contorno global.',
        );
    }

    /**
     * Campo escuro que força a borda com !border-* anula o focus:border-ring do .form-input, e o
     * focus:outline-hidden tira o contorno global: sem focus:!border-ring, o foco some.
     */
    public function test_dark_form_inputs_that_force_the_border_keep_a_visible_focus_border(): void
    {
        $offenders = [];

        foreach ($this->sources() as $path => $source) {
            preg_match_all('/class="([^"]*\bform-(?:input|select)\b[^"]*)"/', $source, $matches);

            foreach ($matches[1] as $classes) {
                if (preg_match('/(?<![\w:-])!border-/', $classes) && ! str_contains($classes, 'focus:!border-ring')) {
                    $offenders[] = "{$path}: {$classes}";
                }
            }
        }

        $this->assertSame([], $offenders, 'Acrescente focus:!border-ring (e um anel mais forte, como focus:ring-ring/60) ao campo.');
    }

    public function test_emoji_are_not_used_as_icons(): void
    {
        $listed = implode('|', array_map(fn (string $emoji): string => preg_quote($emoji, '/'), self::EMOJI_ICONS));

        $this->assertLessThanOrEqual(
            self::MAX_EMOJI_ICONS,
            $this->countMatches('/\p{Emoji_Presentation}|'.$listed.'/u'),
            'Emoji como ícone: use <x-ui.icon> (ou icon() no JS) com aria-hidden, ou só o texto.',
        );
    }

    public function test_native_confirm_dialogs_do_not_grow(): void
    {
        $this->assertLessThanOrEqual(
            self::MAX_NATIVE_CONFIRM,
            $this->countMatches('/(?<![\w.$-])(?:window\.)?confirm\(/'),
            'confirm() nativo: use data-confirm (com data-confirm-title, data-confirm-action-label e data-confirm-variant="danger") ou confirmAction().',
        );
    }

    public function test_native_alert_boxes_are_not_used(): void
    {
        $this->assertLessThanOrEqual(
            self::MAX_NATIVE_ALERT,
            $this->countMatches('/(?<![\w.$-])(?:window\.)?alert\(/'),
            'alert() nativo: use toast({ variant: \'error\' }) de resources/js/ui/toast.js ou <x-ui.alert>.',
        );
    }

    public function test_object_cover_stays_out_of_vehicle_cover_cards_and_heroes(): void
    {
        $count = 0;

        foreach ($this->sources() as $path => $source) {
            $lines = explode("\n", $this->withoutComments($path, $source));

            foreach ($lines as $index => $line) {
                if (! str_contains($line, 'object-cover')) {
                    continue;
                }

                // Fundo desfocado atrás da capa inteira: decorativo, pode preencher a moldura.
                if (str_contains($line, 'blur-')) {
                    continue;
                }

                // Ramo da miniatura em x-vehicle-cover: `$isThumb ? '...object-cover...'`.
                if (str_contains($lines[$index - 1] ?? '', 'isThumb') && str_starts_with(trim($line), '?')) {
                    continue;
                }

                $count += substr_count($line, 'object-cover');
            }
        }

        $this->assertLessThanOrEqual(
            self::MAX_OBJECT_COVER_OUTSIDE_THUMB,
            $count,
            'object-cover corta a imagem. Capa de veículo em card ou hero usa object-contain (x-vehicle-cover).',
        );
    }

    public function test_raw_palette_colors_only_go_down_file_by_file(): void
    {
        $this->assertPerFileCeilings(
            self::RAW_PALETTE_CEILINGS,
            fn (string $source): int => preg_match_all($this->rawPalettePattern(), $source),
            'Cor crua da paleta: use os papéis semânticos (text-muted-foreground, bg-danger-soft text-danger, border-input...).',
        );
    }

    public function test_important_overrides_in_class_attributes_only_go_down_file_by_file(): void
    {
        $this->assertPerFileCeilings(
            self::IMPORTANT_OVERRIDE_CEILINGS,
            fn (string $source): int => $this->countImportantOverrides($source),
            "'!' em class: use a prop do componente x-ui.* (size, variant) ou deixe .theme-inverse trocar os tokens.",
        );
    }

    public function test_transition_all_does_not_grow(): void
    {
        $this->assertLessThanOrEqual(
            self::MAX_TRANSITION_ALL,
            $this->countMatches('/(?<![\w-])transition-all(?![\w-])/'),
            'transition-all: liste as propriedades (transition-colors, transition-[opacity,translate]) com duration-fast|base|slow e ease-smooth-out.',
        );
    }

    public function test_arbitrary_text_below_text_xs_does_not_grow(): void
    {
        $this->assertLessThanOrEqual(
            self::MAX_TINY_ARBITRARY_TEXT,
            $this->countMatches('/(?<![\w-])text-\[(?:[0-9]|10|11)px\]|(?<![\w-])text-\[0?\.[0-6]\d*rem\]/'),
            'Texto menor que text-xs (12px): use text-xs.',
        );
    }

    /**
     * Movimento curto e dos tokens: duration-fast (150ms), duration-base (200ms) e duration-slow
     * (250ms), com ease-smooth-out. Sem duration-300/500/700 ou [0.6s] soltos: a entrada do hero
     * (500ms) e a revelação da landing usam --duration-hero no próprio CSS.
     */
    public function test_transition_durations_come_from_the_motion_tokens(): void
    {
        $offenders = [];

        foreach ($this->sources() as $path => $source) {
            $code = $this->withoutComments($path, $source);

            if (preg_match_all('/(?<![\w\[-])(?:[\w\-\[\]&>*:\/]+:)?duration-(?!fast(?![\w-])|base(?![\w-])|slow(?![\w-]))[\w\[\].]+/', $code, $matches) > 0) {
                $offenders[] = $path.': '.implode(', ', array_unique($matches[0]));
            }
        }

        $this->assertSame([], $offenders, 'Use duration-fast|base|slow (tokens de resources/css/app.css) com ease-smooth-out.');
    }

    /**
     * Laço (animate-spin/pulse/bounce/ping) e deslocamento no hover, foco ou clique (hover:-translate,
     * hover:scale, group-hover:scale, active:scale) só com motion-safe:, para prefers-reduced-motion
     * desligar. animate-bounce e animate-ping não são usados: o único laço decorativo é a flutuação
     * do mock do hero, que tem o botão "Pausar animação".
     */
    public function test_loops_and_hover_movement_only_run_with_motion_safe(): void
    {
        $offenders = [];

        foreach ($this->sources() as $path => $source) {
            $code = $this->withoutComments($path, $source);
            $pattern = '/(?<![\w-])((?:[\w\-\[\]&>*\/]+:)*)(animate-(?:spin|pulse|bounce|ping)|-?translate-[xy]-[\w\[\].-]+|scale-[\w\[\].-]+)(?![\w-])/';

            preg_match_all($pattern, $code, $matches, PREG_SET_ORDER);

            foreach ($matches as [$token, $variants, $utility]) {
                $isLoop = str_starts_with($utility, 'animate-');
                $isInteractive = preg_match('/(?:^|:)(?:group-|peer-)?(?:hover|focus|focus-within|focus-visible|active)(?:\/\w+)?:/', $variants) === 1;

                if (($isLoop || $isInteractive) && ! str_contains($variants, 'motion-safe:')) {
                    $offenders[] = "{$path}: {$token}";
                }

                if (in_array($utility, ['animate-bounce', 'animate-ping'], true)) {
                    $offenders[] = "{$path}: {$token} (laço decorativo)";
                }
            }
        }

        $this->assertSame([], $offenders, 'Anime com motion-safe: (motion-safe:animate-spin, motion-safe:hover:-translate-y-0.5) e só elementos clicáveis levantam no hover.');
    }

    /**
     * Arquivo que anima (transition-*) também desliga a animação com prefers-reduced-motion:
     * motion-reduce:transition-none (ou a transição inteira atrás de motion-safe:). O app.css ainda
     * tem a rede global, mas cada componente dá o exemplo.
     */
    public function test_files_with_transitions_turn_them_off_for_reduced_motion(): void
    {
        $offenders = [];

        foreach ($this->sources() as $path => $source) {
            $code = $this->withoutComments($path, $source);

            if (preg_match('/(?<![\w-])(?:[\w\-\[\]&>*=\/]+:)*transition(?:-(?!none)[\w\[\],]+)?(?=[\s"\'`]|$)/m', $code) !== 1) {
                continue;
            }

            if (preg_match('/motion-reduce:(?:[^\s"\'`]*:)?transition-none|motion-safe:(?:[^\s"\'`]*:)?transition/', $code) !== 1) {
                $offenders[] = $path;
            }
        }

        $this->assertSame([], $offenders, 'Some motion-reduce:transition-none à transição (ou use motion-safe:transition-*).');
    }

    public function test_admin_pages_draw_their_only_h1_with_the_page_header(): void
    {
        $count = 0;

        foreach ($this->sources() as $path => $source) {
            if (str_starts_with($path, 'views/admin/')) {
                $count += preg_match_all('/<h1[\s>]/i', $this->withoutComments($path, $source));
            }
        }

        $this->assertLessThanOrEqual(self::MAX_ADMIN_H1_OUTSIDE_PAGE_HEADER, $count, 'Página do admin: o <h1> vem de <x-ui.page-header title="...">.');
        $this->assertStringContainsString('<h1', File::get(resource_path('views/components/ui/page-header.blade.php')));
    }

    public function test_portal_pages_draw_their_only_h1_with_the_page_header(): void
    {
        $sources = $this->sources();
        $offenders = [];
        $pages = 0;

        foreach ($sources as $path => $source) {
            if (! Str::startsWith($path, self::PORTAL_VIEW_DIRECTORIES)) {
                continue;
            }

            $code = $this->withoutComments($path, $source);

            if (preg_match('/<h1[\s>]/i', $code) === 1) {
                $offenders[] = "{$path}: <h1> escrito na view";
            }

            // Partials (_form, _detail...) e componentes não estendem layout: quem desenha o
            // cabeçalho é a página que os inclui.
            if (preg_match("/@extends\(\s*'([^']+)'/", $code, $extends) !== 1) {
                continue;
            }

            $pages++;
            $candidates = [$code];

            // Layout próprio de uma área (vehicles.entry.layout) desenha o cabeçalho das páginas dela.
            if (! str_starts_with($extends[1], 'layouts.')) {
                $layoutPath = 'views/'.str_replace('.', '/', $extends[1]).'.blade.php';
                $candidates[] = $this->withoutComments($layoutPath, $sources[$layoutPath] ?? '');
            }

            $drawsHeader = collect($candidates)->contains(
                fn (string $candidate): bool => preg_match('/<x-(?:ui\.page-header|vehicle\.detail)[\s>]/', $candidate) === 1,
            );

            if (! $drawsHeader) {
                $offenders[] = "{$path}: sem <x-ui.page-header>";
            }
        }

        $this->assertGreaterThan(40, $pages, 'A varredura precisa achar as páginas dos portais.');
        $this->assertSame([], $offenders, 'Página de portal: o único <h1> vem de <x-ui.page-header title="..."> (trilha no breadcrumbs, ações no slot actions).');
    }

    public function test_owner_portal_has_no_loading_placeholders(): void
    {
        $offenders = [];
        $scanned = 0;

        foreach ($this->sources() as $path => $source) {
            if (! Str::startsWith($path, self::OWNER_SOURCES_WITHOUT_LOADING)) {
                continue;
            }

            $scanned++;

            if (preg_match('/carregando/iu', $this->withoutComments($path, $source)) === 1) {
                $offenders[] = $path;
            }
        }

        $this->assertGreaterThan(10, $scanned);
        $this->assertSame([], $offenders, 'Portal do proprietário: renderize no servidor (x-vehicle.card, x-maintenance.list, x-ui.empty-state), sem "Carregando…".');
    }

    /**
     * Os componentes do design system dão o exemplo: nenhum padrão da catraca dentro de
     * resources/views/components/ui.
     */
    public function test_ui_components_have_none_of_the_legacy_patterns(): void
    {
        $offenders = [];

        foreach ($this->sources() as $path => $source) {
            if (! str_starts_with($path, 'views/components/ui/')) {
                continue;
            }

            $checks = [
                'cor crua' => preg_match_all($this->rawPalettePattern(), $source),
                "'!' em class" => $this->countImportantOverrides($source),
                'transition-all' => preg_match_all('/(?<![\w-])transition-all(?![\w-])/', $source),
                'confirm()/alert()' => preg_match_all('/(?<![\w.$-])(?:window\.)?(?:confirm|alert)\(/', $source),
                'emoji' => preg_match_all('/\p{Emoji_Presentation}/u', $source),
                'text-wrench-600' => preg_match_all('/(?<![\w-])text-wrench-600(?![\w-])/', $source),
            ];

            foreach (array_filter($checks) as $pattern => $matches) {
                $offenders[] = "{$path}: {$pattern} ({$matches})";
            }
        }

        $this->assertSame([], $offenders);
    }

    public function test_undefined_input_field_class_does_not_come_back(): void
    {
        $this->assertSame(0, $this->countMatches('/(?<![\w-])input-field(?![\w-])/'), 'input-field não existe no CSS: use form-input.');
    }

    public function test_guardrails_scan_the_view_and_script_sources(): void
    {
        $sources = $this->sources();

        $this->assertArrayHasKey('views/components/vehicle-cover.blade.php', $sources);
        $this->assertArrayHasKey('views/components/ui/button.blade.php', $sources);
        $this->assertArrayHasKey('js/user-portal.js', $sources);
        $this->assertArrayHasKey('js/ui/confirm.js', $sources);
        $this->assertGreaterThan(100, count($sources));

        // O padrão de '!' pega prefixo, sufixo e variante, e ignora a negação do JS e do Blade.
        $this->assertSame(3, $this->countImportantOverrides('<a class="btn !py-1 hover:!bg-surface text-xs!">x</a> @if(! $x) {{ !empty($y) }}'));
        $this->assertSame(2, preg_match_all($this->rawPalettePattern(), 'bg-red-50 text-automotive-900 hover:border-amber-300 badge-green'));
    }

    /**
     * @param  array<string, int>  $ceilings
     * @param  callable(string): int  $counter
     */
    private function assertPerFileCeilings(array $ceilings, callable $counter, string $advice): void
    {
        $offenders = [];

        foreach ($this->sources() as $path => $source) {
            $count = $counter($source);
            $ceiling = $ceilings[$path] ?? 0;

            if ($count > $ceiling) {
                $offenders[] = "{$path}: {$count} (teto {$ceiling})";
            }
        }

        $this->assertSame([], $offenders, $advice);
    }

    private function rawPalettePattern(): string
    {
        return '/(?<=[a-z]-)(?:'.self::RAW_HUES.')-(?:50|[1-9]00|950)(?![\w-])/';
    }

    /**
     * Classes com '!' (prefixo !p-4, sufixo p-4! ou depois de variante hover:!bg-x) dentro de
     * class="..." e class='...'.
     */
    private function countImportantOverrides(string $source): int
    {
        preg_match_all('/(?<![\w:-])class\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/', $source, $matches);

        $count = 0;

        foreach (array_merge($matches[1], $matches[2]) as $classes) {
            foreach (preg_split('/\s+/', $classes) ?: [] as $token) {
                if (preg_match('/(?:^|:)!-?[a-z]|[a-z0-9\])%]!$/', $token)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    private function countMatches(string $pattern): int
    {
        $count = 0;

        foreach ($this->sources() as $source) {
            $count += preg_match_all($pattern, $source);
        }

        return $count;
    }

    /**
     * Conteúdo dos arquivos de resources/views e resources/js, indexado pelo caminho relativo a
     * resources/.
     *
     * @return array<string, string>
     */
    private function sources(): array
    {
        static $sources = null;

        if ($sources !== null) {
            return $sources;
        }

        $sources = [];

        foreach (['views', 'js'] as $directory) {
            foreach (File::allFiles(resource_path($directory)) as $file) {
                if (! in_array($file->getExtension(), ['php', 'js'], true)) {
                    continue;
                }

                $sources[$directory.'/'.str_replace('\\', '/', $file->getRelativePathname())] = $file->getContents();
            }
        }

        ksort($sources);

        return $sources;
    }

    /**
     * Tira comentários (Blade e HTML nas views, de bloco no JS), mantendo as quebras de linha para o
     * teste de object-cover olhar a linha anterior. Nas views o /* fica, por causa de accept="image/*".
     */
    private function withoutComments(string $path, string $source): string
    {
        $keepNewlines = fn (array $match): string => str_repeat("\n", substr_count($match[0], "\n"));

        if (str_ends_with($path, '.js')) {
            return preg_replace_callback('#/\*.*?\*/#s', $keepNewlines, $source) ?? $source;
        }

        $source = preg_replace_callback('/\{\{--.*?--\}\}/s', $keepNewlines, $source) ?? $source;

        return preg_replace_callback('/<!--.*?-->/s', $keepNewlines, $source) ?? $source;
    }
}
