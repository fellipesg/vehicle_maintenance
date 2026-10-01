<?php

namespace Tests\Feature\Ui;

use Tests\Feature\Ui\Concerns\InspectsUiMarkup;
use Tests\TestCase;

/**
 * <x-ui.alert>: papel ARIA por gravidade, tipo que não depende só da cor e fechamento acessível.
 */
class AlertTest extends TestCase
{
    use InspectsUiMarkup;

    public function test_roles_icons_and_prefixes_per_variant(): void
    {
        $expectations = [
            'info' => ['status', 'Informação:', ['bg-info-soft', 'text-info', 'border-info/25']],
            'success' => ['status', 'Sucesso:', ['bg-success-soft', 'text-success', 'border-success/25']],
            'warning' => ['alert', 'Atenção:', ['bg-warning-soft', 'text-warning', 'border-warning/25']],
            'danger' => ['alert', 'Erro:', ['bg-danger-soft', 'text-danger', 'border-danger/25']],
        ];

        foreach ($expectations as $variant => [$role, $prefix, $classes]) {
            $xpath = $this->renderUi('<x-ui.alert :variant="$variant">Mensagem</x-ui.alert>', ['variant' => $variant]);
            $alert = $this->uiElement($xpath, '//div[@data-slot="alert"]');

            $this->assertSame($role, $alert->getAttribute('role'), "Papel de {$variant}.");
            $this->assertSame($variant, $alert->getAttribute('data-variant'));
            $this->assertHasClasses(array_merge(['rounded-card', 'border', 'p-4', 'text-sm', 'grid'], $classes), $alert);
            $this->assertSame("{$prefix} Mensagem", $this->uiText($alert));

            $icon = $this->uiElement($xpath, '//div[@data-slot="alert"]/svg');
            $this->assertSame('true', $icon->getAttribute('aria-hidden'));
            $this->assertSame('0 0 20 20', $icon->getAttribute('viewbox'));
        }
    }

    public function test_title_description_and_actions(): void
    {
        $xpath = $this->renderUi(<<<'BLADE'
            <x-ui.alert variant="danger" title="Não foi possível carregar seus veículos.">
                Verifique a conexão e <a href="/ajuda">veja a ajuda</a>.
                <x-slot:actions>
                    <x-ui.button variant="secondary" size="sm">Tentar novamente</x-ui.button>
                </x-slot:actions>
            </x-ui.alert>
            BLADE);

        $title = $this->uiElement($xpath, '//p[@data-slot="alert-title"]');
        $this->assertSame('Erro: Não foi possível carregar seus veículos.', $this->uiText($title));
        $this->assertHasClasses(['font-semibold'], $title);

        $description = $this->uiElement($xpath, '//div[@data-slot="alert-description"]');
        $this->assertSame('Verifique a conexão e veja a ajuda.', $this->uiText($description), 'Com título, o prefixo sai só uma vez.');
        $this->assertHasClasses(['[&_a]:underline'], $description);

        $this->assertSame('Tentar novamente', $this->uiText($this->uiElement($xpath, '//div[@data-slot="alert-actions"]/button')));
        $this->assertSame(0, $this->uiCount($xpath, '//button[@data-slot="alert-dismiss"]'));
        $this->assertFalse($this->uiElement($xpath, '//div[@data-slot="alert"]')->hasAttribute('id'));
    }

    public function test_dismissible_alert_has_a_named_close_button_wired_to_preline(): void
    {
        $xpath = $this->renderUi('<x-ui.alert variant="warning" dismissible>Complete o cadastro da oficina.</x-ui.alert>');
        $alert = $this->uiElement($xpath, '//div[@data-slot="alert"]');
        $button = $this->uiElement($xpath, '//button[@data-slot="alert-dismiss"]');

        $this->assertMatchesRegularExpression('/^aviso-[a-z0-9]{8}$/', $alert->getAttribute('id'));
        $this->assertSame('button', $button->getAttribute('type'));
        $this->assertSame('Fechar aviso', $button->getAttribute('aria-label'));
        $this->assertSame('#'.$alert->getAttribute('id'), $button->getAttribute('data-hs-remove-element'));
        $this->assertTrue($button->hasAttribute('data-alert-dismiss'));
        $this->assertHasClasses(['size-10', 'focus-visible:outline-ring'], $button);
        $this->assertHasClasses([
            'grid-cols-[auto_minmax(0,1fr)_auto]',
            'hs-removing:opacity-0',
            'motion-safe:hs-removing:-translate-y-1',
            'duration-fast',
            'ease-smooth-out',
            'motion-reduce:transition-none',
        ], $alert);
        $this->assertLacksClasses(['grid-cols-[auto_minmax(0,1fr)]'], $alert);
    }

    /**
     * O data-hs-remove-element só funciona com o HSRemoveElement no bundle (preline.js importa só
     * os plugins em uso). Depois do clique o foco sai do botão que some: flash.js o leva ao #conteudo.
     */
    public function test_dismiss_button_is_handled_by_the_bundle(): void
    {
        $preline = file_get_contents(resource_path('js/ui/preline.js'));
        $flash = file_get_contents(resource_path('js/ui/flash.js'));

        $this->assertStringContainsString("import HSRemoveElement from 'preline/plugins/remove-element-non-auto';", $preline);
        $this->assertStringContainsString('HSRemoveElement.autoInit();', $preline);
        $this->assertMatchesRegularExpression('/export\s*\{\s*\w+ as default\s*\}/', file_get_contents(base_path('node_modules/preline/dist/remove-element-non-auto.mjs')));

        $this->assertStringContainsString("closest('[data-alert-dismiss]')", $flash);
        $this->assertStringContainsString("closest('[data-slot=\"alert\"]')", $flash);
        $this->assertStringContainsString("getElementById('conteudo')?.focus({ preventScroll: true })", $flash);
    }

    public function test_custom_id_icon_and_role_override(): void
    {
        $xpath = $this->renderUi('<x-ui.alert id="aviso-placa" variant="danger" icon="shield-check" role="status" dismissible>Placa anterior encontrada.</x-ui.alert>');
        $alert = $this->uiElement($xpath, '//div[@data-slot="alert"]');

        $this->assertSame('aviso-placa', $alert->getAttribute('id'));
        $this->assertSame('status', $alert->getAttribute('role'));
        $this->assertSame('#aviso-placa', $this->uiElement($xpath, '//button')->getAttribute('data-hs-remove-element'));
    }

    public function test_unknown_variant_is_rejected(): void
    {
        $this->assertUiRejects('<x-ui.alert variant="error">x</x-ui.alert>', 'x-ui.alert: variant "error" não existe. Use info, success, warning, danger.');
    }
}
