<?php

namespace Tests\Feature\Ui;

use DOMXPath;
use Tests\Feature\Ui\Concerns\InspectsUiMarkup;
use Tests\TestCase;

/**
 * <x-ui.toaster>: região dos toasts com as regiões vivas já na página e os toasts do servidor
 * (session('toast')) passados ao resources/js/ui/toast.js por data-toasts.
 */
class ToasterTest extends TestCase
{
    use InspectsUiMarkup;

    public function test_region_has_polite_and_assertive_live_regions_ready_before_any_toast(): void
    {
        $xpath = $this->renderUi('<x-ui.toaster />');
        $region = $this->uiElement($xpath, '//div[@data-ui-toaster]');
        $polite = $this->uiElement($xpath, '//div[@data-ui-toast-announcer="polite"]');
        $assertive = $this->uiElement($xpath, '//div[@data-ui-toast-announcer="assertive"]');

        $this->assertFalse($region->hasAttribute('data-toasts'));
        $this->assertHasClasses(['theme-default', 'pointer-events-none', 'fixed', 'bottom-4', 'inset-x-4', 'sm:right-4', 'sm:w-96', 'z-[60]'], $region);
        $this->assertSame(1, $this->uiCount($xpath, '//ol[@data-ui-toast-list]'));
        $this->assertSame('status', $polite->getAttribute('role'));
        $this->assertSame('polite', $polite->getAttribute('aria-live'));
        $this->assertSame('true', $polite->getAttribute('aria-atomic'));
        $this->assertSame('alert', $assertive->getAttribute('role'));
        $this->assertSame('assertive', $assertive->getAttribute('aria-live'));
        $this->assertHasClasses(['sr-only'], $polite);
        $this->assertSame('', trim($polite->textContent));
    }

    public function test_session_toast_text_becomes_a_success_toast(): void
    {
        session()->flash('toast', 'Veículo salvo.');

        $this->assertSame([['title' => 'Veículo salvo.', 'description' => '', 'variant' => 'success']], $this->initialToasts('<x-ui.toaster />'));
    }

    public function test_session_toast_array_and_list_keep_title_description_and_variant(): void
    {
        session()->flash('toast', ['title' => 'PDF pronto', 'description' => 'O arquivo está na lista de exportações.', 'variant' => 'info']);

        $this->assertSame([['title' => 'PDF pronto', 'description' => 'O arquivo está na lista de exportações.', 'variant' => 'info']], $this->initialToasts('<x-ui.toaster />'));

        session()->flash('toast', [
            ['title' => 'Modelo ativado', 'variant' => 'success'],
            ['description' => 'Confira a quilometragem.', 'variant' => 'warning'],
            'Link copiado',
        ]);

        $this->assertSame([
            ['title' => 'Modelo ativado', 'description' => '', 'variant' => 'success'],
            ['title' => '', 'description' => 'Confira a quilometragem.', 'variant' => 'warning'],
            ['title' => 'Link copiado', 'description' => '', 'variant' => 'success'],
        ], $this->initialToasts('<x-ui.toaster />'));
    }

    public function test_unknown_variants_fall_back_and_empty_entries_are_dropped(): void
    {
        $toasts = $this->initialToasts('<x-ui.toaster :toasts="$toasts" />', ['toasts' => [
            ['title' => 'Erro', 'variant' => 'error'],
            ['title' => 'Novo status', 'variant' => 'celebracao'],
            ['title' => '   ', 'description' => ''],
            ['variant' => 'info'],
            42,
        ]]);

        $this->assertSame([
            ['title' => 'Erro', 'description' => '', 'variant' => 'error'],
            ['title' => 'Novo status', 'description' => '', 'variant' => 'success'],
        ], $toasts);
    }

    public function test_prop_overrides_the_session_and_text_is_escaped_in_the_attribute(): void
    {
        session()->flash('toast', 'Da sessão');
        $html = (string) $this->blade('<x-ui.toaster :toasts="$toasts" />', ['toasts' => ['title' => 'Placa "ABC" <b>salva</b>']]);

        $this->assertStringNotContainsString('<b>salva</b>', $html);
        $this->assertStringNotContainsString('Da sessão', $html);
        $this->assertSame([['title' => 'Placa "ABC" <b>salva</b>', 'description' => '', 'variant' => 'success']], $this->decodeToasts($this->parseHtml($html)));
    }

    public function test_toast_script_follows_the_component_contract(): void
    {
        $script = file_get_contents(resource_path('js/ui/toast.js'));

        foreach (['[data-ui-toaster]', '[data-ui-toast-list]', 'data-ui-toast-announcer', "'data-toasts'", 'window.revisalogToast = toast;', 'const MAX_VISIBLE = 3;', 'duration: 5000', 'aria-label="Fechar aviso"', 'escapeHtml(title)', 'escapeHtml(description)', 'prefersReducedMotion()', "'visibilitychange'", "'mouseenter'", "'focusin'"] as $fragment) {
            $this->assertStringContainsString($fragment, $script);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array{title: string, description: string, variant: string}>
     */
    private function initialToasts(string $template, array $data = []): array
    {
        return $this->decodeToasts($this->renderUi($template, $data));
    }

    /**
     * @return list<array{title: string, description: string, variant: string}>
     */
    private function decodeToasts(DOMXPath $xpath): array
    {
        $region = $this->uiElement($xpath, '//div[@data-ui-toaster]');

        $this->assertTrue($region->hasAttribute('data-toasts'), 'Faltou data-toasts no toaster.');

        return json_decode($region->getAttribute('data-toasts'), true, 512, JSON_THROW_ON_ERROR);
    }
}
