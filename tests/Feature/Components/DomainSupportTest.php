<?php

namespace Tests\Feature\Components;

use App\Enums\Portal;
use App\Enums\ServiceCategory;
use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Workshop;
use App\Support\Maintenance\MaintenanceLinks;
use App\Support\Maintenance\MaintenanceListFilters;
use App\Support\Vehicle\VehicleIdentifierMask;
use App\Support\Vehicle\VehicleMaintenanceHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Regras compartilhadas pelos componentes de domínio: categoria, máscara de identificadores, ordem
 * e filtro do histórico, links por portal e filtros da lista.
 */
class DomainSupportTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_category_labels_in_portuguese(): void
    {
        $this->assertSame('Mecânica', ServiceCategory::labelFor('mechanical'));
        $this->assertSame('Outros', ServiceCategory::labelFor('other'));
        $this->assertNull(ServiceCategory::labelFor('unknown'));
        $this->assertNull(ServiceCategory::labelFor(null));
        $this->assertSame(['mechanical', 'electrical', 'suspension', 'painting', 'finishing', 'interior', 'other'], array_keys(ServiceCategory::options()));
        $this->assertSame(array_keys(ServiceCategory::options()), ServiceCategory::values());
    }

    public function test_identifiers_are_masked_for_non_owners(): void
    {
        $this->assertSame('9BW••••••••••4251', VehicleIdentifierMask::chassis('9BWZZZ377VT004251'));
        $this->assertSame('•••••••8901', VehicleIdentifierMask::renavam('12345678901'));
        $this->assertSame('••3', VehicleIdentifierMask::renavam('123'));
        $this->assertNull(VehicleIdentifierMask::chassis(null));
        $this->assertNull(VehicleIdentifierMask::chassis('  '));
    }

    public function test_short_legacy_chassis_shows_at_most_forty_percent_at_the_end(): void
    {
        $this->assertSame('••••••567', VehicleIdentifierMask::chassis('BA1234567'));
        $this->assertSame('••••••5678', VehicleIdentifierMask::chassis('BA12345678'));
        $this->assertSame('••••••••••••MNOP', VehicleIdentifierMask::chassis('ABCDEFGHIJKLMNOP'));
        $this->assertSame('••', VehicleIdentifierMask::chassis('AB'));

        foreach (['BA1234567', 'BA12345678', 'BA123456789', 'ABCDEFGHIJKLMNOP'] as $legacyChassis) {
            $hidden = mb_substr_count((string) VehicleIdentifierMask::chassis($legacyChassis), '•');

            $this->assertGreaterThanOrEqual(0.6, $hidden / mb_strlen($legacyChassis), $legacyChassis);
        }
    }

    public function test_history_follows_the_timeline_order(): void
    {
        $vehicle = Vehicle::factory()->create();
        $withoutKm = new Maintenance(['maintenance_date' => '2020-01-01', 'kilometers' => null]);
        $withoutKm->id = 9;
        $later = Maintenance::factory()->declaredByOwner()->create(['vehicle_id' => $vehicle->id, 'kilometers' => 30000, 'maintenance_date' => '2024-01-01']);
        $sameKmEarlier = Maintenance::factory()->declaredByOwner()->create(['vehicle_id' => $vehicle->id, 'kilometers' => 30000, 'maintenance_date' => '2023-12-01']);
        $first = Maintenance::factory()->sealedByWorkshop()->create(['vehicle_id' => $vehicle->id, 'kilometers' => 10000, 'maintenance_date' => '2025-01-01']);

        $ordered = VehicleMaintenanceHistory::inTimelineOrder([$later, $withoutKm, $first, $sameKmEarlier]);

        $this->assertSame([$first->id, $sameKmEarlier->id, $later->id, 9], $ordered->pluck('id')->all());
    }

    public function test_provenance_filter_is_normalized_and_matched(): void
    {
        $this->assertSame('1', VehicleMaintenanceHistory::normalizeFilter('1'));
        $this->assertSame('0', VehicleMaintenanceHistory::normalizeFilter(0));
        $this->assertSame('', VehicleMaintenanceHistory::normalizeFilter('todas'));
        $this->assertSame('', VehicleMaintenanceHistory::normalizeFilter(['1']));
        $this->assertTrue(VehicleMaintenanceHistory::matchesFilter(true, '1'));
        $this->assertFalse(VehicleMaintenanceHistory::matchesFilter(true, '0'));
        $this->assertTrue(VehicleMaintenanceHistory::matchesFilter(false, ''));
        $this->assertSame('1 manutenção', VehicleMaintenanceHistory::countLabel(1));
        $this->assertSame('1.200 manutenções', VehicleMaintenanceHistory::countLabel(1200));
        $this->assertSame('1 declarada', VehicleMaintenanceHistory::declaredLabel(1));
    }

    public function test_links_follow_the_portal(): void
    {
        $maintenance = Maintenance::factory()->declaredByOwner()->create();
        $vehicle = $maintenance->vehicle;

        $this->assertSame(route('user.maintenances.show', $maintenance), MaintenanceLinks::detailUrl($maintenance, Portal::Owner));
        $this->assertSame(route('user.maintenances.show', $maintenance), MaintenanceLinks::detailUrl($maintenance, 'user'));
        $this->assertNull(MaintenanceLinks::detailUrl($maintenance, null));
        $this->assertSame(route('garage.vehicles.show', $vehicle), MaintenanceLinks::vehicleUrl($vehicle, Portal::Dealer));
        $this->assertNull(MaintenanceLinks::vehicleUrl($vehicle, Portal::Workshop), 'A oficina não tem ficha de veículo.');
        $this->assertSame(
            \Illuminate\Support\Facades\Route::has('garage.maintenances.show') ? route('garage.maintenances.show', $maintenance) : null,
            MaintenanceLinks::detailUrl($maintenance, Portal::Dealer),
        );
    }

    public function test_workshop_only_links_to_its_own_orders(): void
    {
        $workshopUser = User::factory()->asWorkshop()->create();
        $own = Maintenance::factory()->sealedByWorkshop()->create(['workshop_id' => $workshopUser->workshop->id]);
        $other = Maintenance::factory()->sealedByWorkshop()->create(['workshop_id' => Workshop::factory()->create()->id]);

        $this->assertSame(route('workshop.maintenances.show', $own), MaintenanceLinks::detailUrl($own, Portal::Workshop, $workshopUser));
        $this->assertNull(MaintenanceLinks::detailUrl($other, Portal::Workshop, $workshopUser));
    }

    public function test_resolver_accepts_default_pattern_closure_and_false(): void
    {
        $maintenance = Maintenance::factory()->declaredByOwner()->create();
        $default = fn (Maintenance $model): string => '/padrao/'.$model->id;

        $this->assertSame('/padrao/'.$maintenance->id, MaintenanceLinks::resolver(null, $default)($maintenance));
        $this->assertSame('/x/'.$maintenance->id, MaintenanceLinks::resolver('/x/{id}', $default)($maintenance));
        $this->assertSame('/c', MaintenanceLinks::resolver(fn (): string => '/c', $default)($maintenance));
        $this->assertNull(MaintenanceLinks::resolver(fn (): string => '', $default)($maintenance));
        $this->assertNull(MaintenanceLinks::resolver(false, $default)($maintenance));
    }

    public function test_list_filters_read_the_query_string_and_filter_the_query(): void
    {
        $vehicle = Vehicle::factory()->create();
        $sealed = Maintenance::factory()->sealedByWorkshop()->create(['vehicle_id' => $vehicle->id]);
        Maintenance::factory()->declaredByOwner()->create(['vehicle_id' => $vehicle->id]);
        Maintenance::factory()->sealedByWorkshop()->create();

        $filters = MaintenanceListFilters::fromRequest(Request::create('/x', 'GET', ['verified' => '1', 'veiculo' => (string) $vehicle->id]));

        $this->assertTrue($filters->isFiltered());
        $this->assertSame([$sealed->id], $filters->apply(Maintenance::query())->pluck('id')->all());
        $this->assertSame([$sealed->id], $filters->apply($vehicle->maintenances())->pluck('id')->all());

        $invalid = MaintenanceListFilters::fromRequest(Request::create('/x', 'GET', ['verified' => 'x', 'veiculo' => 'abc']));
        $this->assertFalse($invalid->isFiltered());
        $this->assertSame(3, $invalid->apply(Maintenance::query())->count());
    }
}
