<?php

namespace Tests\Feature\Ui;

use App\Support\IconLibrary;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\View\ViewException;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * <x-ui.icon>: Heroicons v2 em SVG inline, lidos de resources/js/ui/icons.json, a mesma fonte do
 * helper icon() de resources/js/ui/icons.js.
 */
class IconTest extends TestCase
{
    /**
     * Ícones que as frentes do redesign usam. Nenhum pode faltar em nenhuma das variantes.
     *
     * @var list<string>
     */
    private const REQUIRED_ICONS = [
        'bars-3', 'x-mark', 'bell', 'magnifying-glass', 'user-circle', 'arrow-right-on-rectangle',
        'arrow-left-start-on-rectangle', 'chevron-down', 'chevron-right', 'chevron-left', 'check',
        'check-circle', 'exclamation-triangle', 'exclamation-circle', 'information-circle', 'plus',
        'pencil-square', 'trash', 'document-arrow-down', 'document-text', 'photo', 'arrow-up-tray',
        'truck', 'wrench-screwdriver', 'building-storefront', 'shield-check', 'clock', 'calendar',
        'map-pin', 'home', 'squares-2x2', 'cog-6-tooth', 'eye', 'eye-slash', 'funnel', 'arrow-path',
        'ellipsis-horizontal', 'clipboard-document', 'qr-code', 'key', 'envelope', 'phone', 'globe-alt',
        'map', 'tag', 'newspaper', 'users', 'question-mark-circle', 'lock-closed',
    ];

    protected function tearDown(): void
    {
        IconLibrary::flush();

        parent::tearDown();
    }

    public function test_outline_icon_is_the_default_and_draws_the_path_from_the_shared_json(): void
    {
        $html = (string) $this->blade('<x-ui.icon name="truck" />');
        $svg = $this->svg($html);

        $this->assertStringContainsString('viewBox="0 0 24 24"', $html);
        $this->assertSame('none', $svg->getAttribute('fill'));
        $this->assertSame('currentColor', $svg->getAttribute('stroke'));
        $this->assertSame('1.5', $svg->getAttribute('stroke-width'));
        $this->assertSame('icon', $svg->getAttribute('data-slot'));
        $this->assertSame($this->icons()['outline']['truck'], $this->innerHtml($svg));
    }

    public function test_icon_without_title_is_decorative(): void
    {
        $svg = $this->renderIcon('<x-ui.icon name="bell" />');

        $this->assertSame('true', $svg->getAttribute('aria-hidden'));
        $this->assertSame('false', $svg->getAttribute('focusable'));
        $this->assertFalse($svg->hasAttribute('role'));
        $this->assertFalse($svg->hasAttribute('aria-label'));
        $this->assertSame(0, $svg->getElementsByTagName('title')->length);
    }

    public function test_title_turns_the_icon_into_a_named_image(): void
    {
        $html = (string) $this->blade('<x-ui.icon name="shield-check" :title="$label" />', ['label' => 'Selo da oficina <verificado>']);
        $svg = $this->svg($html);

        $this->assertSame('img', $svg->getAttribute('role'));
        $this->assertSame('Selo da oficina <verificado>', $svg->getAttribute('aria-label'));
        $this->assertFalse($svg->hasAttribute('aria-hidden'));
        $this->assertSame('Selo da oficina <verificado>', $svg->getElementsByTagName('title')->item(0)?->textContent);
        $this->assertStringNotContainsString('<verificado>', $html, 'O title precisa sair escapado.');
    }

    public function test_blank_title_keeps_the_icon_decorative(): void
    {
        $svg = $this->renderIcon('<x-ui.icon name="bell" title=" " />');

        $this->assertSame('true', $svg->getAttribute('aria-hidden'));
        $this->assertFalse($svg->hasAttribute('role'));
    }

    public function test_solid_variant_uses_the_filled_20px_set(): void
    {
        $html = (string) $this->blade('<x-ui.icon name="check-circle" variant="solid" />');
        $svg = $this->svg($html);

        $this->assertStringContainsString('viewBox="0 0 20 20"', $html);
        $this->assertSame('currentColor', $svg->getAttribute('fill'));
        $this->assertFalse($svg->hasAttribute('stroke'));
        $this->assertSame($this->icons()['solid']['check-circle'], $this->innerHtml($svg));
        $this->assertNotSame($this->icons()['outline']['check-circle'], $this->innerHtml($svg));
    }

    public function test_default_size_is_size_5_and_a_size_class_replaces_it(): void
    {
        $default = $this->classes($this->renderIcon('<x-ui.icon name="plus" />'));
        $this->assertContains('size-5', $default);
        $this->assertContains('shrink-0', $default);

        $colorOnly = $this->classes($this->renderIcon('<x-ui.icon name="plus" class="text-danger" />'));
        $this->assertContains('size-5', $colorOnly);
        $this->assertContains('text-danger', $colorOnly);

        foreach (['size-4', 'h-4 w-4', '!size-6'] as $sizeClass) {
            $classes = $this->classes($this->renderIcon('<x-ui.icon name="plus" class="'.$sizeClass.' text-danger" />'));

            $this->assertNotContains('size-5', $classes, "{$sizeClass} deveria substituir o size-5.");
            $this->assertContains('shrink-0', $classes);
            $this->assertContains('text-danger', $classes);
        }

        $responsive = $this->classes($this->renderIcon('<x-ui.icon name="plus" class="sm:size-6" />'));
        $this->assertContains('size-5', $responsive, 'Um tamanho só a partir de sm: ainda precisa do tamanho base.');
        $this->assertContains('sm:size-6', $responsive);
    }

    public function test_bound_class_array_is_accepted(): void
    {
        $classes = $this->classes($this->renderIcon('<x-ui.icon name="plus" :class="[\'size-4\' => true, \'text-danger\' => false, \'text-success\']" />'));

        $this->assertEqualsCanonicalizing(['shrink-0', 'size-4', 'text-success'], $classes);
    }

    public function test_extra_attributes_reach_the_svg(): void
    {
        $svg = $this->renderIcon('<x-ui.icon name="eye" data-testid="icone-olho" />');

        $this->assertSame('icone-olho', $svg->getAttribute('data-testid'));
    }

    public function test_icon_adds_no_whitespace_around_the_svg(): void
    {
        $html = trim((string) $this->blade('<span>Antes<x-ui.icon name="check" />Depois</span>'));

        $this->assertMatchesRegularExpression('#^<span>Antes<svg [^>]+>.*</svg>Depois</span>$#s', $html);
    }

    public function test_unknown_icon_name_fails_with_a_clear_message_in_testing(): void
    {
        try {
            $this->blade('<x-ui.icon name="x-marc" />');
            $this->fail('Nome de ícone inexistente deveria lançar exceção em testing.');
        } catch (ViewException $exception) {
            $cause = $exception;

            while ($cause instanceof ViewException && $cause->getPrevious() !== null) {
                $cause = $cause->getPrevious();
            }

            $this->assertInstanceOf(InvalidArgumentException::class, $cause);
            $this->assertStringContainsString('Ícone "x-marc" não existe na variante outline de resources/js/ui/icons.json.', $cause->getMessage());
            $this->assertStringContainsString('Você quis dizer "x-mark"?', $cause->getMessage());
        }
    }

    public function test_unknown_variant_fails_with_a_clear_message(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Variante de ícone "mini" não existe. Use outline ou solid.');

        IconLibrary::body('check', 'mini');
    }

    public function test_unknown_icon_is_reported_and_not_drawn_outside_local_and_testing(): void
    {
        Exceptions::fake();
        $this->app->detectEnvironment(fn (): string => 'production');

        $html = (string) $this->blade('<span><x-ui.icon name="nao-existe" /></span>');

        $this->assertSame('<span></span>', trim($html));
        Exceptions::assertReported(fn (InvalidArgumentException $exception): bool => str_contains($exception->getMessage(), '"nao-existe"'));
    }

    public function test_json_carries_the_license_and_every_icon_in_both_variants(): void
    {
        $icons = $this->icons();

        $this->assertStringContainsString('Heroicons v2', $icons['_license']);
        $this->assertStringContainsString('MIT', $icons['_license']);
        $this->assertStringContainsString('Copyright (c) Tailwind Labs', $icons['_notice']);
        $this->assertSame(['outline', 'solid'], IconLibrary::VARIANTS);
        $this->assertSame(array_keys($icons['outline']), array_keys($icons['solid']));

        foreach (self::REQUIRED_ICONS as $name) {
            $this->assertTrue(IconLibrary::has($name, 'outline'), "Falta {$name} (outline).");
            $this->assertTrue(IconLibrary::has($name, 'solid'), "Falta {$name} (solid).");
        }

        foreach (['outline', 'solid'] as $variant) {
            foreach ($icons[$variant] as $name => $body) {
                $this->assertMatchesRegularExpression('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $name);
                $this->assertMatchesRegularExpression('#^(?:<path [^<>]+/>)+$#', $body, "{$variant}/{$name} deveria ter só <path> (sem <svg>, script ou texto).");
                $this->assertDoesNotMatchRegularExpression('/\son[a-z]+=/i', $body);
            }
        }
    }

    public function test_js_helper_reads_the_same_json_as_the_component(): void
    {
        $helper = file_get_contents(resource_path('js/ui/icons.js'));

        $this->assertStringContainsString("import iconSet from './icons.json';", $helper);
        $this->assertStringContainsString('export function icon(name, { variant = \'outline\', className = \'\', title = null } = {})', $helper);
        $this->assertStringContainsString('aria-hidden="true"', $helper);
        $this->assertStringContainsString("'size-5'", $helper);
        $this->assertStringContainsString('escapeHtml(title)', $helper);
    }

    private function renderIcon(string $template): DOMElement
    {
        return $this->svg((string) $this->blade($template));
    }

    private function svg(string $html): DOMElement
    {
        $document = new DOMDocument;
        $document->loadHTML('<?xml encoding="UTF-8"><div>'.$html.'</div>', LIBXML_NOERROR | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $svg = (new DOMXPath($document))->query('//svg')->item(0);

        $this->assertInstanceOf(DOMElement::class, $svg, 'O componente não desenhou o <svg>.');

        return $svg;
    }

    /**
     * @return list<string>
     */
    private function classes(DOMElement $svg): array
    {
        return preg_split('/\s+/', trim($svg->getAttribute('class')));
    }

    private function innerHtml(DOMElement $element): string
    {
        $html = '';

        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMElement && $child->tagName === 'title') {
                continue;
            }

            $html .= $element->ownerDocument->saveXML($child);
        }

        return $html;
    }

    /**
     * @return array{_license: string, _notice: string, _svg: array<string, array<string, string>>, outline: array<string, string>, solid: array<string, string>}
     */
    private function icons(): array
    {
        return json_decode(file_get_contents(resource_path('js/ui/icons.json')), true, 512, JSON_THROW_ON_ERROR);
    }
}
