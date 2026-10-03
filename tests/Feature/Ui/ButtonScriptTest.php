<?php

namespace Tests\Feature\Ui;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Symfony\Component\Process\ExecutableFinder;
use Tests\Feature\Ui\Concerns\InspectsUiMarkup;
use Tests\TestCase;

/**
 * resources/js/ui/button.js é o espelho do <x-ui.button> para os botões que o JS cria (diálogo de
 * confirmação sem o layout, "Carregar mais") ou troca de variante ("Confirmar" destrutivo). As
 * classes precisam ser as mesmas do componente, e as antigas .btn-* não voltam.
 */
class ButtonScriptTest extends TestCase
{
    use InspectsUiMarkup;

    private ?string $sandbox = null;

    protected function tearDown(): void
    {
        if ($this->sandbox !== null) {
            File::deleteDirectory($this->sandbox);
        }

        parent::tearDown();
    }

    public function test_script_classes_match_the_blade_button_for_every_variant_and_size(): void
    {
        $output = $this->runButtonScript();

        foreach (['primary', 'secondary', 'ghost', 'danger', 'link'] as $variant) {
            foreach (['sm', 'md', 'lg'] as $size) {
                $button = $this->uiElement(
                    $this->renderUi('<x-ui.button :variant="$variant" :size="$size">Salvar</x-ui.button>', ['variant' => $variant, 'size' => $size]),
                    '//button',
                );
                $bladeClasses = $this->uiClasses($button);
                $scriptClasses = preg_split('/\s+/', trim($output['classes']["{$variant}-{$size}"]));
                sort($bladeClasses);
                sort($scriptClasses);

                $this->assertSame($bladeClasses, $scriptClasses, "buttonClass('{$variant}', '{$size}') diverge do <x-ui.button>.");
            }
        }
    }

    public function test_set_button_variant_swaps_only_the_color_classes(): void
    {
        $output = $this->runButtonScript();

        $this->assertSame('danger', $output['swap']['variant']);
        $this->assertContains('bg-danger', $output['swap']['classes']);
        $this->assertContains('hover:bg-danger-hover', $output['swap']['classes']);
        $this->assertNotContains('bg-primary', $output['swap']['classes']);
        $this->assertNotContains('hover:bg-primary-hover', $output['swap']['classes']);
        $this->assertContains('min-h-10', $output['swap']['classes'], 'Tamanho e classes extras ficam.');
        $this->assertContains('w-full', $output['swap']['classes']);

        $this->assertSame('primary', $output['back']['variant']);
        $this->assertContains('bg-primary', $output['back']['classes']);
        $this->assertNotContains('bg-danger', $output['back']['classes']);
        $this->assertSame('primary', $output['unknown'], 'Variante desconhecida cai em primary, como no Blade.');
    }

    public function test_legacy_button_and_badge_aliases_are_gone(): void
    {
        $stylesheet = File::get(resource_path('css/app.css'));

        foreach (['.btn-primary', '.btn-secondary', '.btn-danger', '.badge-blue', '.badge-orange', '.badge-green', '.badge-accent', '.stat-card'] as $alias) {
            $this->assertStringNotContainsString($alias, $stylesheet, "{$alias} saiu do app.css: use <x-ui.button>, <x-ui.badge> ou <x-ui.stat>.");
        }

        foreach ([resource_path('views'), resource_path('js')] as $directory) {
            foreach (File::allFiles($directory) as $file) {
                $this->assertDoesNotMatchRegularExpression(
                    '/(?<![\w-])(?:btn-(?:primary|secondary|danger)|badge-(?:blue|orange|green|accent))(?![\w-])/',
                    $file->getContents(),
                    $file->getRelativePathname().': use <x-ui.button>/<x-ui.badge> (ou buttonClass() de resources/js/ui/button.js).',
                );
            }
        }
    }

    /**
     * @return array{classes: array<string, string>, swap: array{variant: string, classes: list<string>}, back: array{variant: string, classes: list<string>}, unknown: string}
     */
    private function runButtonScript(): array
    {
        if ((new ExecutableFinder)->find('node') === null) {
            $this->markTestSkipped('Node.js is required to run resources/js/ui/button.js.');
        }

        $this->sandbox = storage_path('framework/testing/button-'.Str::random(12));
        File::ensureDirectoryExists($this->sandbox);
        File::put("{$this->sandbox}/package.json", '{"type": "module"}');
        File::put("{$this->sandbox}/button.js", File::get(resource_path('js/ui/button.js')));
        File::put("{$this->sandbox}/runner.js", <<<'JS'
            import { buttonClass, setButtonVariant } from './button.js';

            class FakeButton {
                constructor(className) {
                    this.classes = new Set(className.split(/\s+/).filter(Boolean));
                    this.dataset = {};
                    this.classList = {
                        add: (...names) => names.forEach((name) => this.classes.add(name)),
                        remove: (...names) => names.forEach((name) => this.classes.delete(name)),
                    };
                }
            }

            const classes = {};
            for (const variant of ['primary', 'secondary', 'ghost', 'danger', 'link']) {
                for (const size of ['sm', 'md', 'lg']) {
                    classes[`${variant}-${size}`] = buttonClass(variant, size);
                }
            }

            const button = new FakeButton(`${buttonClass('primary')} w-full`);
            setButtonVariant(button, 'danger');
            const swap = { variant: button.dataset.variant, classes: [...button.classes] };
            setButtonVariant(button, 'primary');
            const back = { variant: button.dataset.variant, classes: [...button.classes] };
            setButtonVariant(button, 'destructive');

            console.log(JSON.stringify({ classes, swap, back, unknown: button.dataset.variant }));
            JS);

        $process = Process::path($this->sandbox)->run(['node', 'runner.js']);

        $this->assertTrue($process->successful(), $process->errorOutput());

        return json_decode($process->output(), true, 512, JSON_THROW_ON_ERROR);
    }
}
