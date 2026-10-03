<?php

namespace Tests\Feature\Web;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\VehicleCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminMaintenancesStatusRegionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(VehicleCatalogSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->asUser()->asAdmin()->create();
    }

    public function test_index_renders_a_short_status_region_instead_of_a_live_table(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/manutencoes')
            ->assertOk()
            ->assertSee('<p class="sr-only" role="status" aria-live="polite" aria-atomic="true" data-admin-maintenances-status></p>', false)
            // Fade de 150ms enquanto a tabela recarrega (opacity-60 do admin-maintenances-filters.js).
            ->assertSee('<div id="admin-maintenances-results" class="transition-opacity duration-fast ease-smooth-out motion-reduce:transition-none" data-admin-maintenances-results aria-busy="false">', false)
            ->assertDontSee('data-admin-maintenances-results aria-live', false);
    }

    public function test_index_renders_the_load_error_template_with_retry(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/manutencoes')
            ->assertOk()
            ->assertSee('data-admin-maintenances-error-template', false)
            ->assertSee('Não foi possível carregar as manutenções.')
            ->assertSee('data-admin-maintenances-retry', false)
            ->assertSee('Tentar novamente');
    }

    public function test_filter_buttons_expose_the_pressed_state_on_first_render(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/manutencoes?verified=1')
            ->assertOk()
            ->assertSeeInOrder([
                'data-maintenance-filter=""',
                'aria-pressed="false"',
                'data-maintenance-filter="1"',
                'aria-pressed="true"',
                'data-maintenance-filter="0"',
                'aria-pressed="false"',
            ], false);
    }

    public function test_ajax_fragment_carries_the_summary_for_the_status_region(): void
    {
        $vehicle = Vehicle::factory()->create();

        Maintenance::factory()->count(2)->for($vehicle)->sealedByWorkshop()->create();
        Maintenance::factory()->for($vehicle)->declaredByOwner()->create();

        $this->actingAs($this->admin())
            ->get('/admin/manutencoes?verified=1', ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertSee('data-admin-maintenances-summary', false)
            ->assertSee('data-status-text="2 manutenções encontradas · filtro: Selo da oficina"', false)
            ->assertSee('Mostrando 1 a 2 de 2 manutenções.')
            ->assertDontSee('data-admin-maintenances-status', false);

        $this->actingAs($this->admin())
            ->get('/admin/manutencoes?verified=0', ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertSee('data-status-text="1 manutenção encontrada · filtro: Declaradas"', false)
            ->assertSee('Mostrando 1 a 1 de 1 manutenção.');
    }

    public function test_summary_mentions_the_page_when_results_are_paginated(): void
    {
        $vehicle = Vehicle::factory()->create();
        Maintenance::factory()->count(26)->for($vehicle)->create();

        $this->actingAs($this->admin())
            ->get('/admin/manutencoes?page=2', ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertSee('data-status-text="26 manutenções encontradas · página 2 de 2"', false)
            ->assertSee('Mostrando 26 a 26 de 26 manutenções.');
    }

    public function test_summary_reports_an_empty_list(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/manutencoes')
            ->assertOk()
            ->assertSee('data-status-text="Nenhuma manutenção encontrada"', false)
            ->assertSee('Nenhuma manutenção encontrada.');
    }
}
