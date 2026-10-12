<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * O preenchimento de endereço pelo CEP (resources/js/workshop-address-cep.js) age onde encontra
 * [data-cep-autofill] no campo de CEP e acha os destinos pelo name, dentro do mesmo formulário.
 * "Minha loja" entrou nesse contrato; sem o atributo o campo volta a ser só máscara de dígitos.
 */
class CepAutofillTest extends TestCase
{
    use RefreshDatabase;

    public function test_minha_loja_marks_the_cep_field_for_autofill(): void
    {
        $dealer = User::factory()->asGarage()->create();

        $this->actingAs($dealer)
            ->get(route('garage.profile.show'))
            ->assertOk()
            ->assertSee('data-cep-autofill', escape: false);
    }

    public function test_minha_oficina_keeps_the_same_contract(): void
    {
        $workshopUser = User::factory()->asWorkshop()->create();

        $this->actingAs($workshopUser)
            ->get(route('workshop.profile.edit'))
            ->assertOk()
            ->assertSee('data-cep-autofill', escape: false);
    }

    /**
     * O módulo procura os destinos por form.elements[name]; se um nome mudar, o preenchimento para
     * de funcionar em silêncio.
     */
    public function test_minha_loja_has_the_field_names_the_script_looks_for(): void
    {
        $dealer = User::factory()->asGarage()->create();

        $response = $this->actingAs($dealer)->get(route('garage.profile.show'))->assertOk();

        foreach (['street', 'neighborhood', 'city', 'state'] as $field) {
            $response->assertSee('name="'.$field.'"', escape: false);
        }
    }

    public function test_there_is_a_single_cep_module_started_by_app_js(): void
    {
        $appJs = File::get(resource_path('js/app.js'));

        $this->assertStringContainsString("from './workshop-address-cep'", $appJs);
        $this->assertStringContainsString('initWorkshopAddressCep();', $appJs);

        // A implementação duplicada desta branch saiu quando a da main chegou.
        $this->assertFalse(File::exists(resource_path('js/cep-autofill.js')));
    }
}
