<?php

namespace Tests\Feature\Ui;

use Tests\Feature\Ui\Concerns\InspectsUiMarkup;
use Tests\TestCase;

/**
 * <x-ui.flash> e o partial layouts.partials.flash que o envolve: a mesma semântica da Fase 0
 * (role, data-flash, prefixo para leitor de tela e "Fechar aviso"), agora com ícone e tokens.
 */
class FlashTest extends TestCase
{
    use InspectsUiMarkup;

    public function test_each_session_key_gets_its_role_prefix_icon_and_dismiss_button(): void
    {
        $expectations = [
            'error' => ['alert', 'Erro', 'bg-danger-soft', 'text-danger'],
            'warning' => ['alert', 'Atenção', 'bg-warning-soft', 'text-warning'],
            'success' => ['status', 'Sucesso', 'bg-success-soft', 'text-success'],
            'status' => ['status', 'Sucesso', 'bg-success-soft', 'text-success'],
            'info' => ['status', 'Informação', 'bg-info-soft', 'text-info'],
        ];

        foreach ($expectations as $key => [$role, $prefix, $background, $text]) {
            session()->flush();
            session()->flash($key, "Mensagem de {$key}");

            $xpath = $this->renderUi('<x-ui.flash />');
            $flash = $this->uiElement($xpath, "//div[@data-flash='{$key}']");
            $dismiss = $this->uiElement($xpath, "//div[@data-flash='{$key}']/button");

            $this->assertSame($role, $flash->getAttribute('role'), $key);
            $this->assertHasClasses(['rounded-card', 'border', $background, $text], $flash);
            $this->assertSame("{$prefix}: ", $this->uiElement($xpath, "//div[@data-flash='{$key}']//span[@class='sr-only']")->textContent);
            $this->assertSame("{$prefix}: Mensagem de {$key}", $this->uiText($this->uiElement($xpath, "//div[@data-flash='{$key}']/p")));
            $this->assertSame('true', $this->uiElement($xpath, "//div[@data-flash='{$key}']/svg")->getAttribute('aria-hidden'));
            $this->assertSame('Fechar aviso', $dismiss->getAttribute('aria-label'));
            $this->assertSame('button', $dismiss->getAttribute('type'));
            $this->assertTrue($dismiss->hasAttribute('data-flash-dismiss'));
            $this->assertHasClasses(['size-10', 'motion-reduce:transition-none'], $dismiss);
        }
    }

    public function test_messages_follow_severity_order_and_skip_blank_or_non_text_values(): void
    {
        session()->flash('info', 'Info');
        session()->flash('success', 'Salvo');
        session()->flash('error', 'Falhou');
        session()->flash('warning', '   ');
        session()->flash('status', ['não', 'é', 'texto']);

        $xpath = $this->renderUi('<x-ui.flash />');
        $keys = array_map(fn ($node): string => $node->getAttribute('data-flash'), iterator_to_array($xpath->query('//div[@data-flash]')));

        $this->assertSame(['error', 'success', 'info'], $keys);
    }

    public function test_nothing_is_rendered_without_messages(): void
    {
        $this->assertSame('', trim((string) $this->blade('<x-ui.flash />')));
    }

    public function test_wrapper_class_and_messages_prop(): void
    {
        session()->flash('error', 'Da sessão');
        $xpath = $this->renderUi('<x-ui.flash wrapper-class="mb-4" :messages="[\'success\' => \'Prévia <b>\']" />');

        $this->assertSame('mb-4', $this->uiElement($xpath, '//div[@id="ui-test-root"]/div')->getAttribute('class'));
        $this->assertSame(0, $this->uiCount($xpath, "//div[@data-flash='error']"));
        $this->assertSame('Sucesso: Prévia <b>', $this->uiText($this->uiElement($xpath, "//div[@data-flash='success']/p")));
    }

    public function test_layout_partial_wraps_the_component_and_keeps_its_wrapper_class(): void
    {
        session()->flash('warning', 'Revise os dados da marca.');

        $xpath = $this->parseHtml(view('layouts.partials.flash', ['flashTone' => 'dark', 'flashWrapperClass' => 'mb-4'])->render());

        $this->assertSame('mb-4', $this->uiElement($xpath, '//div[@id="ui-test-root"]/div')->getAttribute('class'));
        $this->assertSame('alert', $this->uiElement($xpath, "//div[@data-flash='warning']")->getAttribute('role'));
        $this->assertSame(1, $this->uiCount($xpath, '//button[@aria-label="Fechar aviso"]'));

        $default = $this->parseHtml(view('layouts.partials.flash')->render());
        $this->assertSame('mx-auto w-full max-w-7xl px-4 pt-4', $this->uiElement($default, '//div[@id="ui-test-root"]/div')->getAttribute('class'));
    }

    public function test_dismiss_script_removes_the_flash_and_moves_focus_to_main(): void
    {
        $script = file_get_contents(resource_path('js/ui/flash.js'));

        $this->assertStringContainsString("closest('[data-flash-dismiss]')", $script);
        $this->assertStringContainsString("closest('[data-flash]')", $script);
        $this->assertStringContainsString("getElementById('conteudo')?.focus({ preventScroll: true })", $script);
    }
}
