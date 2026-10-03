<?php

namespace Tests\Feature\Ui;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\ExecutableFinder;
use Tests\TestCase;

/**
 * Componentes das ondas 2 e 3 do design system (command, copy-button, lightbox, radio-cards,
 * stepper, sidebar-toggle, contador do textarea) e os scripts deles em resources/js/ui: o mesmo
 * contrato dos componentes da Fase 1 (tokens semânticos, foco por focus-visible, movimento curto
 * com motion-reduce, pt-BR, sem emoji) e a lógica pura dos scripts rodando no Node.
 */
class UiWaveScriptsTest extends TestCase
{
    /**
     * @var list<string>
     */
    private const COMPONENTS = ['command', 'copy-button', 'lightbox', 'radio-cards', 'stepper', 'sidebar-toggle'];

    /**
     * Script => init que o app.js chama e a marca de idempotência.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const SCRIPTS = [
        'command' => ['initCommandPalettes', 'readyPalettes'],
        'copy' => ['initCopyButtons', 'copyButtonReady'],
        'lightbox' => ['initLightboxes', 'lightboxReady'],
        'textarea-counter' => ['initTextareaCounters', 'textareaCounterReady'],
        'sidebar' => ['initSidebarToggles', 'sidebarToggleReady'],
    ];

    private ?string $sandbox = null;

    protected function tearDown(): void
    {
        if ($this->sandbox !== null) {
            File::deleteDirectory($this->sandbox);
        }

        parent::tearDown();
    }

    public function test_components_follow_the_design_system_contract(): void
    {
        $palette = 'red|green|amber|blue|yellow|orange|teal|emerald|gray|zinc|slate|neutral|stone|sky|indigo|violet|purple|pink|rose|lime|cyan|fuchsia|wrench|automotive';
        $rawColor = '/(?<![\w-])(?:[\w\[\]&>*:\/-]+:)?(?:bg|text|border|ring|outline|fill|stroke|divide|decoration|shadow|placeholder|caret|accent)-(?:'.$palette.')-\d{2,3}(?![\w-])|(?<![\w-])(?:bg|text|border)-(?:white|black)(?![\w-])/';

        foreach (self::COMPONENTS as $component) {
            $source = file_get_contents(resource_path("views/components/ui/{$component}.blade.php"));
            $code = (string) preg_replace('/\{\{--.*?--\}\}/s', '', $source);

            $this->assertStringStartsWith('{{--', $source, "{$component}: comece pelo comentário com props e exemplo.");
            $this->assertStringContainsString('Ex.:', $source, "{$component}: o comentário precisa de exemplo.");
            $this->assertStringContainsString('@props(', $source, "{$component}: declare as props.");
            $this->assertStringContainsString('UiProps::', $code, "{$component}: valide as props com App\\Support\\UiProps.");
            $this->assertSame(0, preg_match_all($rawColor, $code), "{$component}: use tokens semânticos.");
            $this->assertDoesNotMatchRegularExpression('/(?<![\w-])focus:/', $code, "{$component}: foco só com focus-visible.");
            $this->assertStringNotContainsString('transition-all', $code, "{$component}: anime propriedades específicas.");
            $this->assertStringNotContainsString('outline-none', $code, "{$component}: não remova o contorno de foco.");
            $this->assertDoesNotMatchRegularExpression('/(?<![\w-])duration-\d/', $code, "{$component}: use duration-fast/base/slow.");
            $this->assertDoesNotMatchRegularExpression('/(?<![\w-])!\w|\w!(?=\s|")/', $code, "{$component}: sem '!' em classe.");
            $this->assertDoesNotMatchRegularExpression('/\p{Extended_Pictographic}/u', $source, "{$component}: sem emoji.");
            $this->assertDoesNotMatchRegularExpression('/aria-label="(?:Close|Open|Menu|Next|Previous|Copy)/', $source, "{$component}: rótulos em pt-BR.");
            $this->assertStringNotContainsString('object-cover', $code, "{$component}: foto não é cortada.");

            if (preg_match('/(?<![\w-])transition-(?!none)/', $code) === 1) {
                $this->assertStringContainsString('motion-reduce:transition-none', $code, "{$component}: toda transição precisa de motion-reduce:transition-none.");
            }
        }
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function scripts(): array
    {
        $cases = [];

        foreach (self::SCRIPTS as $file => [$function, $readyFlag]) {
            $cases[$file] = [$file, $function, $readyFlag];
        }

        return $cases;
    }

    #[DataProvider('scripts')]
    public function test_each_script_exports_an_idempotent_initializer_and_writes_no_html(string $file, string $function, string $readyFlag): void
    {
        $source = file_get_contents(resource_path("js/ui/{$file}.js"));

        $this->assertStringContainsString("export function {$function}(root = document)", $source);
        $this->assertStringContainsString('elementsWithin', $source);
        $this->assertStringContainsString($readyFlag, $source, 'Ligar duas vezes o mesmo elemento duplicaria os ouvintes.');
        $this->assertStringNotContainsString('innerHTML', $source);
        $this->assertDoesNotMatchRegularExpression('/(?<![\w.$-])(?:window\.)?(?:confirm|alert|prompt)\(/', $source);
        $this->assertDoesNotMatchRegularExpression('/\p{Extended_Pictographic}/u', $source);
    }

    public function test_command_palette_filters_without_accents_and_detects_vehicle_identifiers(): void
    {
        $result = $this->runScripts()['command'];

        $this->assertSame('manutencoes da frota', $result['normalize']);
        $this->assertSame([true, true, false, true], $result['matches']);
        $this->assertSame(
            ['ABC1D23' => true, 'abc-1234' => true, '9BWZZZ377VT004251' => true, '12345678901' => true, 'manutencoes' => false, 'ABCDEFGHJKLMNPRST' => false, 'OFICINA' => false],
            $result['identifiers'],
        );
        $this->assertSame(['mac-meta' => true, 'mac-ctrl' => false, 'pc-ctrl' => true, 'pc-meta' => false, 'shift' => false, 'other-key' => false], $result['shortcuts']);
        $this->assertSame('https://revisalog.test/buscar-veiculo?identifier=ABC1D23', $result['searchUrl']);
        $this->assertSame('https://revisalog.test/admin/veiculos?ordenar=veiculo&search=9BW+ZZZ', $result['searchUrlKeepsQuery']);
        $this->assertSame('https://revisalog.test/buscar-veiculo', $result['searchUrlEmpty']);
    }

    public function test_textarea_counter_announces_each_tenth_of_the_limit(): void
    {
        $result = $this->runScripts()['counter'];

        $this->assertSame('4.000', $result['format']);
        $this->assertSame([['bucket' => 0, 'state' => 'ok'], ['bucket' => 5, 'state' => 'ok'], ['bucket' => 9, 'state' => 'near'], ['bucket' => 10, 'state' => 'limit']], $result['states']);
        $this->assertSame('50% do limite: 2.000 de 4.000 caracteres.', $result['half']);
        $this->assertSame('Limite de 4.000 caracteres atingido.', $result['limit']);
    }

    public function test_lightbox_maps_the_scroll_position_to_a_photo(): void
    {
        $result = $this->runScripts()['lightbox'];

        $this->assertSame([0, 1, 1, 3, 3, 0], $result['fromScroll']);
        $this->assertSame([0, 2, 3, 0], $result['clamp']);
    }

    public function test_sidebar_state_survives_blocked_or_missing_storage(): void
    {
        $result = $this->runScripts()['sidebar'];

        $this->assertSame('collapsed', $result['readSaved']);
        $this->assertSame('expanded', $result['readEmpty']);
        $this->assertSame('expanded', $result['readThrows'], 'Armazenamento bloqueado vale como expandida, sem erro.');
        $this->assertSame('expanded', $result['readMissing']);
        $this->assertTrue($result['write']);
        $this->assertSame('collapsed', $result['written']);
        $this->assertFalse($result['writeThrows']);
        $this->assertFalse($result['writeMissing']);
    }

    public function test_copy_reports_failure_instead_of_throwing(): void
    {
        $result = $this->runScripts()['copy'];

        $this->assertTrue($result['granted']);
        $this->assertSame('9BWZZZ377VT004251', $result['copied']);
        $this->assertFalse($result['denied']);
        $this->assertFalse($result['missing']);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function runScripts(): array
    {
        if ((new ExecutableFinder)->find('node') === null) {
            $this->markTestSkipped('Node.js is required to run the resources/js/ui scripts.');
        }

        $this->sandbox = storage_path('framework/testing/ui-wave-'.Str::random(12));
        File::ensureDirectoryExists($this->sandbox);
        File::put("{$this->sandbox}/package.json", '{"type": "module"}');

        // O Vite resolve './dialog'; o Node exige a extensão.
        foreach (['dom', 'dialog', 'command', 'copy', 'lightbox', 'textarea-counter', 'sidebar'] as $file) {
            File::put(
                "{$this->sandbox}/{$file}.js",
                (string) preg_replace("/from '(\\.\\/[\\w-]+)';/", "from '$1.js';", File::get(resource_path("js/ui/{$file}.js"))),
            );
        }

        File::put("{$this->sandbox}/runner.js", <<<'JS'
            globalThis.window = {
                location: { href: 'https://revisalog.test/admin/manutencoes' },
                setTimeout,
                clearTimeout,
                requestAnimationFrame: (callback) => setTimeout(callback, 0),
            };

            const setNavigator = (value) => Object.defineProperty(globalThis, 'navigator', { value, configurable: true, writable: true });

            const command = await import('./command.js');
            const counter = await import('./textarea-counter.js');
            const lightbox = await import('./lightbox.js');
            const sidebar = await import('./sidebar.js');
            const copy = await import('./copy.js');

            const key = (overrides) => ({ key: 'k', metaKey: false, ctrlKey: false, altKey: false, shiftKey: false, isComposing: false, ...overrides });
            const storage = (initial = {}) => {
                const data = { ...initial };

                return { data, getItem: (name) => data[name] ?? null, setItem: (name, value) => { data[name] = String(value); } };
            };
            const throwing = { getItem() { throw new Error('SecurityError'); }, setItem() { throw new Error('QuotaExceededError'); } };

            const written = storage();
            let copied = null;
            setNavigator({ clipboard: { writeText: async (text) => { copied = text; } } });
            const granted = await copy.writeToClipboard('9BWZZZ377VT004251');
            setNavigator({ clipboard: { writeText: async () => { throw new Error('NotAllowedError'); } } });
            const denied = await copy.writeToClipboard('x');
            setNavigator({});
            const missing = await copy.writeToClipboard('x');

            console.log(JSON.stringify({
                command: {
                    normalize: command.normalizeText('  Manutenções   da FROTA '),
                    matches: [
                        command.matchesQuery('Manutenções Frota', 'manut'),
                        command.matchesQuery('Manutenções Frota', 'frota manu'),
                        command.matchesQuery('Veículos Frota', 'oficina'),
                        command.matchesQuery('Artigos do blog Conteúdo', 'conteudo'),
                    ],
                    identifiers: Object.fromEntries(['ABC1D23', 'abc-1234', '9BWZZZ377VT004251', '12345678901', 'manutencoes', 'ABCDEFGHJKLMNPRST', 'OFICINA']
                        .map((value) => [value, command.looksLikeVehicleIdentifier(value)])),
                    shortcuts: {
                        'mac-meta': command.isPaletteShortcut(key({ metaKey: true }), true),
                        'mac-ctrl': command.isPaletteShortcut(key({ ctrlKey: true }), true),
                        'pc-ctrl': command.isPaletteShortcut(key({ ctrlKey: true, key: 'K' }), false),
                        'pc-meta': command.isPaletteShortcut(key({ metaKey: true }), false),
                        shift: command.isPaletteShortcut(key({ ctrlKey: true, shiftKey: true }), false),
                        'other-key': command.isPaletteShortcut(key({ ctrlKey: true, key: 'j' }), false),
                    },
                    searchUrl: command.searchUrl('https://revisalog.test/buscar-veiculo', 'identifier', ' ABC1D23 '),
                    searchUrlKeepsQuery: command.searchUrl('/admin/veiculos?ordenar=veiculo', 'search', '9BW ZZZ'),
                    searchUrlEmpty: command.searchUrl('https://revisalog.test/buscar-veiculo', 'identifier', '   '),
                },
                counter: {
                    format: counter.formatCount(4000),
                    states: [0, 2000, 3600, 4000].map((length) => counter.counterState(length, 4000)),
                    half: counter.counterAnnouncement(2000, 4000),
                    limit: counter.counterAnnouncement(4000, 4000),
                },
                lightbox: {
                    fromScroll: [[0, 400, 4], [390, 400, 4], [410, 400, 4], [1200, 400, 4], [5000, 400, 4], [300, 0, 4]]
                        .map(([left, width, total]) => lightbox.indexFromScroll(left, width, total)),
                    clamp: [[-2, 4], [2, 4], [9, 4], [3, 0]].map(([index, total]) => lightbox.clampIndex(index, total)),
                },
                sidebar: {
                    readSaved: sidebar.readSidebarState(storage({ 'revisalog:admin-sidebar': 'collapsed' }), 'revisalog:admin-sidebar'),
                    readEmpty: sidebar.readSidebarState(storage(), 'revisalog:admin-sidebar'),
                    readThrows: sidebar.readSidebarState(throwing, 'revisalog:admin-sidebar'),
                    readMissing: sidebar.readSidebarState(null, 'revisalog:admin-sidebar'),
                    write: sidebar.writeSidebarState(written, 'revisalog:admin-sidebar', 'collapsed'),
                    written: written.data['revisalog:admin-sidebar'],
                    writeThrows: sidebar.writeSidebarState(throwing, 'revisalog:admin-sidebar', 'collapsed'),
                    writeMissing: sidebar.writeSidebarState(null, 'revisalog:admin-sidebar', 'expanded'),
                },
                copy: { granted, copied, denied, missing },
            }));
            JS);

        $process = Process::path($this->sandbox)->run(['node', 'runner.js']);

        $this->assertTrue($process->successful(), $process->errorOutput());

        return json_decode(trim($process->output()), true, 512, JSON_THROW_ON_ERROR);
    }
}
