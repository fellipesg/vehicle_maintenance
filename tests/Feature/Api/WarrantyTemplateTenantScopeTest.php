<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\WarrantyTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * O cadastro da oficina (nome, prazo e termo de garantia) é da conta: WorkshopPolicy::view escopa
 * por tenant, então a ability workshops:read não abre a oficina de outra pessoa.
 */
class WarrantyTemplateTenantScopeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Os dois apiResource de oficina aplicam `workshops:read` e `workshops:write` em toda rota (o
     * mapa de middleware por método é ignorado), e o token do app traz as duas: é com as duas que
     * um cliente real chega aqui.
     *
     * @var list<string>
     */
    private const ABILITIES = ['workshops:read', 'workshops:write'];

    public function test_another_workshop_cannot_list_the_warranty_templates(): void
    {
        $owner = User::factory()->asWorkshop()->create();
        WarrantyTemplate::factory()->forWorkshop($owner->workshop)->orderScope()->create();
        $intruder = User::factory()->asWorkshop()->create();

        Sanctum::actingAs($intruder, self::ABILITIES);

        $this->getJson("/api/v1/workshops/{$owner->workshop->id}/warranty-templates")->assertForbidden();
    }

    public function test_another_workshop_cannot_show_a_warranty_template(): void
    {
        $owner = User::factory()->asWorkshop()->create();
        $template = WarrantyTemplate::factory()->forWorkshop($owner->workshop)->orderScope()->create([
            'name' => 'Garantia 90 dias',
            'body' => 'Termo interno da oficina.',
        ]);
        $intruder = User::factory()->asWorkshop()->create();

        Sanctum::actingAs($intruder, self::ABILITIES);

        $response = $this->getJson("/api/v1/workshops/{$owner->workshop->id}/warranty-templates/{$template->id}");

        $response->assertForbidden();
        $response->assertDontSee('Termo interno da oficina.');
    }

    public function test_an_owner_account_cannot_read_a_workshops_warranty_templates(): void
    {
        $owner = User::factory()->asWorkshop()->create();
        WarrantyTemplate::factory()->forWorkshop($owner->workshop)->orderScope()->create();
        $driver = User::factory()->asUser()->create();

        Sanctum::actingAs($driver, self::ABILITIES);

        $this->getJson("/api/v1/workshops/{$owner->workshop->id}/warranty-templates")->assertForbidden();
    }

    public function test_the_workshop_itself_still_reads_its_own_warranty_templates(): void
    {
        $owner = User::factory()->asWorkshop()->create();
        $template = WarrantyTemplate::factory()->forWorkshop($owner->workshop)->orderScope()->create(['name' => 'Garantia 90 dias']);

        Sanctum::actingAs($owner, self::ABILITIES);

        $this->getJson("/api/v1/workshops/{$owner->workshop->id}/warranty-templates")
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Garantia 90 dias');

        $this->getJson("/api/v1/workshops/{$owner->workshop->id}/warranty-templates/{$template->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Garantia 90 dias');
    }
}
