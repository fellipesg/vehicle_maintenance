<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O shell (barra e menu) já informa a área: as páginas não repetem chip de portal no topo,
 * e títulos e botões não usam emoji como ícone.
 */
class PortalPageHeaderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var list<string>
     */
    private const PORTAL_CHIPS = ['Portal da Garagem', 'Portal da Oficina', '🔧 Oficina', '🏪 Garagem', '🏠 Garagem'];

    public function test_workshop_pages_do_not_repeat_the_portal_chip(): void
    {
        $workshop = User::factory()->asWorkshop()->create();

        foreach ([
            'workshop.dashboard',
            'workshop.maintenances.index',
            'workshop.maintenances.create',
            'workshop.profile.show',
            'workshop.profile.edit',
            'workshop.warranty-templates.index',
            'workshop.warranty-templates.create',
        ] as $routeName) {
            $html = $this->actingAs($workshop)->get(route($routeName))->assertOk()->getContent();

            $this->assertNoPortalChip($html, $routeName);
        }
    }

    public function test_garage_pages_do_not_repeat_the_portal_chip(): void
    {
        $garage = User::factory()->asGarage()->create();

        foreach ([
            'garage.dashboard',
            'garage.vehicles.index',
            'garage.vehicles.create',
            'garage.maintenances.index',
        ] as $routeName) {
            $html = $this->actingAs($garage)->get(route($routeName))->assertOk()->getContent();

            $this->assertNoPortalChip($html, $routeName);
        }
    }

    public function test_page_headings_have_no_emoji_icon(): void
    {
        $owner = User::factory()->asUser()->create();
        $workshop = User::factory()->asWorkshop()->create();
        $admin = User::factory()->asUser()->asAdmin()->create();

        $this->actingAs($owner)->get(route('user.vehicles.index'))
            ->assertOk()
            ->assertSee('data-slot="page-header-title" class="text-2xl font-bold tracking-tight text-balance text-foreground sm:text-3xl">Meus veículos</h1>', false);
        $this->actingAs($owner)->get(route('user.vehicles.create'))
            ->assertOk()
            ->assertSee('>Adicionar veículo</h1>', false);
        $this->actingAs($owner)->get(route('user.workshops.index'))
            ->assertOk()
            ->assertSee('>Oficinas da rede</h1>', false);
        $this->actingAs($owner)->get(route('vehicle.search'))
            ->assertOk()
            ->assertSee('>Buscar histórico de veículo</h1>', false)
            ->assertDontSee('🔍');
        $this->actingAs($workshop)->get(route('workshop.maintenances.create'))
            ->assertOk()
            ->assertSee('>Nova ordem de serviço</h1>', false);
        $this->actingAs($admin)->get(route('admin.brands.index'))
            ->assertOk()
            ->assertSee('>Marcas e modelos</h1>', false)
            ->assertDontSee('🏷');
        $this->actingAs($admin)->get(route('admin.blog.index'))
            ->assertOk()
            ->assertSee('>Artigos do blog</h1>', false);
    }

    public function test_search_fields_have_a_programmatic_label(): void
    {
        $owner = User::factory()->asUser()->create();
        $admin = User::factory()->asUser()->asAdmin()->create();

        $this->actingAs($admin)->get(route('admin.vehicles.index'))
            ->assertOk()
            ->assertSee('<label for="admin-vehicle-search" class="sr-only">Buscar veículo por chassi, placa ou RENAVAM</label>', false)
            ->assertSee('id="admin-vehicle-search"', false)
            ->assertDontSee('input-field', false);

        $this->actingAs($owner)->get(route('user.workshops.index'))
            ->assertOk()
            ->assertSee('<label for="workshop-search" data-slot="label" class="block text-sm font-medium text-foreground sr-only">Buscar oficina por nome, cidade ou bairro</label>', false)
            ->assertSee('id="workshop-search"', false);

        $this->actingAs($owner)->get(route('vehicle.search'))
            ->assertOk()
            ->assertSee('for="vehicle-search-identifier"', false)
            ->assertSee('Placa, chassi ou RENAVAM', false)
            ->assertSee('id="vehicle-search-identifier"', false);
    }

    private function assertNoPortalChip(string $html, string $routeName): void
    {
        foreach (self::PORTAL_CHIPS as $chip) {
            $this->assertStringNotContainsString($chip, $html, "{$routeName} ainda mostra o chip \"{$chip}\".");
        }
    }
}
