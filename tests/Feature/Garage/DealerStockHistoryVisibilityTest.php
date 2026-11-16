<?php

namespace Tests\Feature\Garage;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleConsignment;
use App\Support\Vehicle\DealerStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O Estoque não conta nem data o que o lojista não pode ler.
 *
 * Veículo em consignação tem o histórico anterior fechado até o proprietário liberar ou a equipe
 * aprovar a procuração (`.ai/rules/consignment.md`). A ficha do veículo já respeitava isso, mas os
 * agregados da lista — contagens e `maintenances_max_maintenance_date` — eram calculados sobre todas
 * as manutenções: medido, o card dizia "Última manutenção em 15/03/2026" (o serviço do proprietário,
 * anterior à consignação) ao lado do aviso "Só o que você registrou", e a tabela ordenava o estoque
 * inteiro por essa data.
 */
class DealerStockHistoryVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private const OWNER_DATE = '2026-03-15';

    private const DEALER_DATE = '2026-09-20';

    private User $dealer;

    private Vehicle $vehicle;

    private VehicleConsignment $consignment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vehicle = Vehicle::factory()->create(['license_plate' => 'CON5I1G']);

        $owner = User::factory()->asUser()->create()->refresh();
        $this->attachVehicleToUser($owner, $this->vehicle);

        $this->dealer = User::factory()->asGarage()->create()->refresh();
        $this->dealer->vehicles()->attach($this->vehicle->id, [
            'purchase_date' => now(),
            'is_current_owner' => false,
            'tenant_id' => $this->dealer->tenant_id,
            'ownership_type' => 'consignment',
        ]);

        $this->consignment = VehicleConsignment::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'garage_user_id' => $this->dealer->id,
            'tenant_id' => $this->dealer->tenant_id,
            'owner_user_id' => $owner->id,
            'history_access_status' => VehicleConsignment::HISTORY_NONE,
        ]);

        // Serviço do proprietário, anterior à consignação: é o que o portão protege.
        Maintenance::factory()->declaredByOwner()->create([
            'vehicle_id' => $this->vehicle->id,
            'user_id' => $owner->id,
            'tenant_id' => $owner->tenant_id,
            'maintenance_type' => 'Retífica de motor',
            'maintenance_date' => self::OWNER_DATE,
        ]);
    }

    private function stockHtml(string $view = DealerStock::VIEW_CARDS): string
    {
        return $this->withoutVite()
            ->actingAs($this->dealer)
            ->get(route('garage.vehicles.index', ['visao' => $view]))
            ->assertOk()
            ->getContent();
    }

    public function test_the_cards_do_not_date_a_service_the_dealer_cannot_read(): void
    {
        $this->assertFalse($this->dealer->canViewStockVehicleHistory($this->vehicle));

        $html = $this->stockHtml();

        $this->assertStringNotContainsString('15/03/2026', $html);
        $this->assertStringNotContainsString('Última manutenção em', $html);
    }

    public function test_the_table_does_not_date_it_either(): void
    {
        $html = $this->stockHtml(DealerStock::VIEW_TABLE);

        $this->assertStringNotContainsString('15/03/2026', $html);
        $this->assertStringContainsString('Só o que você registrou', $html);
    }

    public function test_the_dealers_own_record_is_dated_even_without_access(): void
    {
        Maintenance::factory()->declaredByGarage()->create([
            'vehicle_id' => $this->vehicle->id,
            'user_id' => $this->dealer->id,
            'tenant_id' => $this->dealer->tenant_id,
            'maintenance_type' => 'Revisão feita pela loja',
            'maintenance_date' => self::DEALER_DATE,
        ]);

        $html = $this->stockHtml();

        $this->assertStringContainsString('20/09/2026', $html);
        $this->assertStringNotContainsString('15/03/2026', $html);
    }

    public function test_once_the_owner_releases_the_history_the_date_appears(): void
    {
        $this->consignment->forceFill([
            'history_access_status' => VehicleConsignment::HISTORY_APPROVED,
            'history_approved_via' => 'owner',
        ])->save();

        $this->assertTrue($this->dealer->fresh()->canViewStockVehicleHistory($this->vehicle));

        $this->assertStringContainsString('15/03/2026', $this->stockHtml());
    }

    /**
     * Contestação congela a loja na hora: o que já estava liberado fecha de novo.
     */
    public function test_a_disputed_consignment_closes_the_date_again(): void
    {
        $this->consignment->forceFill([
            'history_access_status' => VehicleConsignment::HISTORY_APPROVED,
            'owner_disputed_at' => now(),
        ])->save();

        $this->assertStringNotContainsString('15/03/2026', $this->stockHtml());
    }

    public function test_the_dealers_own_stock_keeps_its_dates(): void
    {
        $owned = Vehicle::factory()->create(['license_plate' => 'EST0Q1E']);
        $this->dealer->vehicles()->attach($owned->id, [
            'purchase_date' => now(),
            'is_current_owner' => true,
            'tenant_id' => $this->dealer->tenant_id,
        ]);

        // Serviço de outro tenant, anterior à compra: no estoque próprio o histórico é aberto.
        $previous = User::factory()->asUser()->create()->refresh();
        Maintenance::factory()->declaredByOwner()->create([
            'vehicle_id' => $owned->id,
            'user_id' => $previous->id,
            'tenant_id' => $previous->tenant_id,
            'maintenance_date' => '2026-01-10',
        ]);

        $this->assertStringContainsString('10/01/2026', $this->stockHtml());
    }
}
