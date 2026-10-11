<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * O preenchimento de endereço pelo CEP (resources/js/cep-autofill.js) age pelos data-* das telas.
 * Sem eles o script não encontra nada e o campo volta a ser só máscara de dígitos, que foi o
 * comportamento que existiu até aqui nos dois formulários de endereço.
 */
class CepAutofillTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<string>
     */
    private function hooks(): array
    {
        return [
            'data-cep-autofill',
            'data-cep-input',
            'data-cep-status',
            'data-cep-focus',
            'data-cep-target="street"',
            'data-cep-target="neighborhood"',
            'data-cep-target="city"',
            'data-cep-target="state"',
        ];
    }

    public function test_minha_loja_carries_every_cep_hook(): void
    {
        $dealer = User::factory()->asGarage()->create();

        $response = $this->actingAs($dealer)->get(route('garage.profile.show'))->assertOk();

        foreach ($this->hooks() as $hook) {
            $response->assertSee($hook, escape: false);
        }
    }

    public function test_minha_oficina_carries_every_cep_hook(): void
    {
        $workshopUser = User::factory()->asWorkshop()->create();

        $response = $this->actingAs($workshopUser)->get(route('workshop.profile.edit'))->assertOk();

        foreach ($this->hooks() as $hook) {
            $response->assertSee($hook, escape: false);
        }
    }

    public function test_the_script_is_started_by_app_js_and_not_inline_in_the_views(): void
    {
        $appJs = File::get(resource_path('js/app.js'));

        $this->assertStringContainsString("from './cep-autofill'", $appJs);
        $this->assertStringContainsString('initCepAutofill();', $appJs);

        foreach (['garage/profile/edit.blade.php', 'workshop/profile/_form.blade.php'] as $view) {
            $this->assertStringNotContainsString('<script', File::get(resource_path('views/'.$view)));
        }
    }

    public function test_the_script_only_touches_fields_it_filled_itself(): void
    {
        $script = File::get(resource_path('js/cep-autofill.js'));

        // Guarda a regra que impede o script de apagar o que a pessoa digitou à mão.
        $this->assertStringContainsString('cepFilled', $script);
        $this->assertStringContainsString('typedByPerson', $script);
    }
}
