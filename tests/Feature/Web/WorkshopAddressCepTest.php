<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CEP preenchendo o endereço no perfil da oficina: o campo marcado com data-cep-autofill e o
 * módulo resources/js/workshop-address-cep.js, que app.js inicia junto com os outros scripts de
 * tela. Sem o script o formulário continua funcionando — por isso nada aqui exige JS para salvar.
 */
class WorkshopAddressCepTest extends TestCase
{
    use RefreshDatabase;

    private function moduleSource(): string
    {
        return file_get_contents(resource_path('js/workshop-address-cep.js'));
    }

    public function test_cep_field_of_the_workshop_form_asks_for_the_autofill(): void
    {
        $workshopUser = User::factory()->asWorkshop()->create();

        $response = $this->actingAs($workshopUser)
            ->get(route('workshop.profile.edit'))
            ->assertOk();

        $html = $response->getContent();

        $this->assertStringContainsString('data-cep-autofill', $html);
        $this->assertMatchesRegularExpression(
            '/<input[^>]*name="cep"[^>]*data-cep-autofill/',
            $html,
            'O atributo precisa estar no campo de CEP, não em outro lugar do formulário.'
        );
    }

    public function test_the_other_address_fields_stay_reachable_by_name(): void
    {
        $workshopUser = User::factory()->asWorkshop()->create();

        $html = $this->actingAs($workshopUser)
            ->get(route('workshop.profile.edit'))
            ->assertOk()
            ->getContent();

        // O módulo acha os campos por name dentro do mesmo formulário.
        foreach (['street', 'neighborhood', 'city'] as $field) {
            $this->assertMatchesRegularExpression("/<input[^>]*name=\"{$field}\"/", $html);
        }

        $this->assertMatchesRegularExpression('/<select[^>]*name="state"/', $html);
    }

    public function test_app_entry_starts_the_module(): void
    {
        $entry = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString(
            "import { initWorkshopAddressCep } from './workshop-address-cep';",
            $entry
        );
        $this->assertStringContainsString('initWorkshopAddressCep();', $entry);
    }

    public function test_module_is_idempotent_and_avoids_native_boxes(): void
    {
        $source = $this->moduleSource();

        $this->assertStringContainsString('dataset.cepAutofillReady', $source);
        $this->assertDoesNotMatchRegularExpression(
            '/(?<![\w.$-])(?:window\.)?(?:confirm|alert)\(/',
            $source,
            'Avisos saem por toast(), nunca por caixa nativa.'
        );
        $this->assertStringContainsString("import { toast } from './ui/toast';", $source);
    }

    public function test_module_fills_every_address_field_from_the_viacep_payload(): void
    {
        $source = $this->moduleSource();

        foreach ([
            'street' => 'logradouro',
            'neighborhood' => 'bairro',
            'city' => 'localidade',
            'state' => 'uf',
        ] as $field => $viaCepKey) {
            $this->assertMatchesRegularExpression(
                "/{$field}:\s*'{$viaCepKey}'/",
                $source,
                "O campo {$field} precisa vir de {$viaCepKey}."
            );
        }
    }

    public function test_module_only_queries_a_complete_and_new_cep(): void
    {
        $source = $this->moduleSource();

        $this->assertStringContainsString('const CEP_DIGITS = 8;', $source);
        $this->assertStringContainsString('cep.length !== CEP_DIGITS', $source);
        $this->assertStringContainsString('cep === lastQueried', $source);
    }

    public function test_module_cancels_the_previous_lookup(): void
    {
        $source = $this->moduleSource();

        $this->assertStringContainsString('pending?.abort();', $source);
        $this->assertStringContainsString('new AbortController()', $source);
    }

    public function test_module_notifies_live_validation_after_filling(): void
    {
        $source = $this->moduleSource();

        // Campo obrigatório preenchido por script continua "vazio" para o form-ux.js sem o evento.
        $this->assertStringContainsString("new Event('input', { bubbles: true })", $source);
        $this->assertStringContainsString("new Event('change', { bubbles: true })", $source);
    }
}
