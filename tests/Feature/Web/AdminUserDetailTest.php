<?php

namespace Tests\Feature\Web;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Web\Concerns\InspectsAdminPages;
use Tests\TestCase;

/**
 * Detalhe do usuário no admin, por perfil: métricas com nome explícito ("lançadas por esta conta" ×
 * "nos veículos atuais"), veículos com link para o detalhe do admin, manutenções com procedência e,
 * para oficinas, a oficina vinculada.
 */
class AdminUserDetailTest extends TestCase
{
    use InspectsAdminPages;
    use RefreshDatabase;

    public function test_owner_detail_separates_launched_from_current_vehicle_maintenances(): void
    {
        $owner = User::factory()->asUser()->create(['name' => 'Paula', 'phone' => '(81) 99999-1234', 'city' => 'Recife', 'state' => 'PE']);
        $previousOwner = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create(['brand' => 'Toyota', 'model' => 'Corolla', 'license_plate' => 'ABC1D23']);
        $owner->vehicles()->attach($vehicle->id, ['purchase_date' => now(), 'is_current_owner' => true, 'tenant_id' => $owner->tenant_id]);
        Maintenance::factory()->for($vehicle)->for($owner)->declaredByOwner()->create(['maintenance_type' => 'Troca de óleo']);
        Maintenance::factory()->for($vehicle)->for($previousOwner)->sealedByWorkshop()->create(['maintenance_type' => 'Revisão 20 mil']);

        $xpath = $this->adminPage($this->actingAs($this->adminUser())->get(route('admin.users.show', $owner)));

        $metrics = [];
        foreach ($this->adminElements($xpath, '//*[@data-slot="user-metrics"]/*[@data-slot="stat"]') as $stat) {
            $metrics[$this->adminText($this->adminElement($xpath, './/dt', $stat))] = $this->adminText($this->adminElement($xpath, './/dd', $stat));
        }
        $this->assertSame([
            'Veículos atuais' => '1',
            'Manutenções nos veículos atuais' => '2',
            'Manutenções lançadas por esta conta' => '1',
        ], $metrics);

        $details = $this->adminElement($xpath, '//*[@data-slot="user-account-details"]');
        $this->assertStringContainsString('Recife/PE', $this->adminText($details));
        $this->assertSame('tel:81999991234', $this->adminElement($xpath, './/a[contains(., "99999-1234")]', $details)->getAttribute('href'));

        $vehicleLink = $this->adminElement($xpath, '//*[@id="veiculos"]//ul[@data-slot="user-vehicles"]//a[@data-slot="vehicle-card-link"]');
        $this->assertSame('Toyota Corolla', $this->adminText($vehicleLink));
        $this->assertSame(route('admin.vehicles.show', $vehicle), $vehicleLink->getAttribute('href'));

        $launched = $this->adminElement($xpath, '//*[@id="manutencoes-lancadas"]');
        $this->assertStringContainsString('Troca de óleo', $this->adminText($launched));
        $this->assertStringNotContainsString('Revisão 20 mil', $this->adminText($launched), 'Registro do dono anterior não é lançado por esta conta.');
        $this->adminElement($xpath, './/*[@data-slot="provenance-legend"]', $launched);

        $tabs = array_map(fn ($tab): string => $this->adminText($tab), $this->adminElements($xpath, '//*[@role="tablist"]//*[@role="tab"]'));
        $this->assertStringStartsWith('Veículos atuais', $tabs[0]);
        $this->assertStringStartsWith('Manutenções lançadas', $tabs[1]);
        $this->assertSame([['Cadastros', route('admin.users.index')]], [[$this->adminTrail($xpath)[0]['label'], $this->adminTrail($xpath)[1]['href']]]);
    }

    public function test_workshop_detail_shows_the_linked_workshop_and_what_it_launched(): void
    {
        $account = User::factory()->asWorkshop()->create(['name' => 'Oficina do Zé']);
        $workshop = $account->workshop()->firstOrFail();
        $workshop->update(['street' => 'Rua A', 'number' => '10', 'city' => 'Recife', 'state' => 'PE', 'latitude' => null, 'longitude' => null]);
        $vehicle = Vehicle::factory()->create();
        Maintenance::factory()->count(2)->for($vehicle)->for($account)->sealedByWorkshop()->create(['workshop_id' => $workshop->id]);

        $xpath = $this->adminPage($this->actingAs($this->adminUser())->get(route('admin.users.show', $account)));

        $metrics = [];
        foreach ($this->adminElements($xpath, '//*[@data-slot="user-metrics"]/*[@data-slot="stat"]') as $stat) {
            $metrics[$this->adminText($this->adminElement($xpath, './/dt', $stat))] = $this->adminText($this->adminElement($xpath, './/dd', $stat));
        }
        $this->assertSame('2', $metrics['Manutenções lançadas por esta conta']);
        $this->assertSame('2', $metrics['Com Selo da oficina']);
        $this->assertSame('Sem coordenadas', $metrics['Localização']);

        $linked = $this->adminElement($xpath, '//section[@id="oficina-vinculada"]');
        $this->assertStringContainsString('Rua A, 10', $this->adminText($linked));
        $this->assertStringContainsString('Recife/PE', $this->adminText($linked));
        $this->assertStringContainsString('Sem coordenadas', $this->adminText($linked));

        $firstTab = $this->adminElement($xpath, '//*[@role="tablist"]//*[@role="tab"][1]');
        $this->assertStringStartsWith('Manutenções lançadas', $this->adminText($firstTab), 'Na oficina, o que ela lançou vem primeiro.');
        $this->assertSame(2, $xpath->query('//*[@id="manutencoes-lancadas"]//*[@data-maintenance-card][@data-verified="1"]')->length);
    }

    public function test_workshop_account_without_workshop_says_so(): void
    {
        $account = User::factory()->asWorkshop()->create();
        Workshop::query()->where('user_id', $account->id)->delete();

        $xpath = $this->adminPage($this->actingAs($this->adminUser())->get(route('admin.users.show', $account)));

        $this->assertStringContainsString('Oficina ainda não cadastrada', $this->adminText($this->adminElement($xpath, '//section[@id="oficina-vinculada"]')));
    }

    public function test_long_history_shows_the_ten_latest_and_links_to_all(): void
    {
        $owner = User::factory()->asUser()->create();
        Maintenance::factory()->count(12)->for($owner)->create();

        $xpath = $this->adminPage($this->actingAs($this->adminUser())->get(route('admin.users.show', $owner)));

        $launched = $this->adminElement($xpath, '//*[@id="manutencoes-lancadas"]');
        $this->assertSame(10, $xpath->query('.//*[@data-maintenance-card]', $launched)->length);
        $this->assertSame(route('admin.maintenances.index', ['usuario' => $owner->id]), $this->adminElement($xpath, './/a[contains(., "Ver todas as 12")]', $launched)->getAttribute('href'));
    }

    public function test_empty_account_shows_empty_states(): void
    {
        $owner = User::factory()->asUser()->create();

        $xpath = $this->adminPage($this->actingAs($this->adminUser())->get(route('admin.users.show', $owner)));

        $this->assertStringContainsString('Nenhum veículo atual', $this->adminText($this->adminElement($xpath, '//*[@id="veiculos"]')));
        $this->assertStringContainsString('Nenhuma manutenção lançada', $this->adminText($this->adminElement($xpath, '//*[@id="manutencoes-lancadas"]')));
        $this->assertSame(0, $xpath->query('//*[contains(., "📞")]')->length);
    }
}
