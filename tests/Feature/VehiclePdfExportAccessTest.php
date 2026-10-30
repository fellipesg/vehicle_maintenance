<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleConsignment;
use App\Models\VehiclePdfExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Baixar o PDF do histórico pede, na hora do download, o prazo do arquivo e a posse atual.
 *
 * O arquivo guardado não guarda o direito: quem vendeu o veículo para de baixar junto com a posse,
 * e o prazo (vehicle-pdf-export.file_ttl_hours) vale no ato, não só quando o
 * `vehicle-pdf-exports:cleanup` passa. Dono atual, admin e lojista com o histórico liberado seguem
 * baixando — a mesma `VehiclePolicy::viewFullHistory` que autorizou o pedido.
 */
class VehiclePdfExportAccessTest extends TestCase
{
    use RefreshDatabase;

    private const PATH = 'exports/vehicle-pdfs/test-export.pdf';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::disk('public')->put(self::PATH, '%PDF-1.4 test');
    }

    public function test_api_download_of_an_expired_export_is_gone(): void
    {
        $owner = $this->actingAsApiUser();
        $vehicle = $this->vehicleOwnedBy($owner);
        $export = $this->exportFor($owner, $vehicle, expiresAt: now()->subMinute());

        $this->getJson("/api/v1/vehicle-pdf-exports/{$export->id}/download")
            ->assertStatus(410)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'O prazo deste PDF terminou. Peça a exportação de novo.');

        Storage::disk('public')->assertExists(self::PATH);
    }

    public function test_api_status_of_an_expired_export_stops_handing_out_download_urls(): void
    {
        $owner = $this->actingAsApiUser();
        $vehicle = $this->vehicleOwnedBy($owner);
        $export = $this->exportFor($owner, $vehicle, expiresAt: now()->subMinute());

        $this->getJson("/api/v1/vehicle-pdf-exports/{$export->id}")
            ->assertStatus(410)
            ->assertJsonPath('success', false)
            ->assertJsonMissingPath('data.download_url');
    }

    public function test_web_download_of_an_expired_export_is_gone(): void
    {
        $owner = User::factory()->asUser()->create();
        $vehicle = $this->vehicleOwnedBy($owner);
        $export = $this->exportFor($owner, $vehicle, expiresAt: now()->subMinute());

        $this->actingAs($owner)
            ->get('/usuario/exportacoes-pdf/'.$export->id.'/historico_teste.pdf')
            ->assertStatus(410);

        $this->actingAs($owner)
            ->get('/usuario/exportacoes-pdf/'.$export->id.'/baixar')
            ->assertStatus(410);
    }

    public function test_api_download_stops_for_whoever_sold_the_vehicle(): void
    {
        $seller = $this->actingAsApiUser();
        $vehicle = $this->vehicleOwnedBy($seller);
        $export = $this->exportFor($seller, $vehicle);

        $this->sellVehicle($seller, $vehicle);

        $this->getJson("/api/v1/vehicle-pdf-exports/{$export->id}/download")
            ->assertForbidden()
            ->assertJsonPath('success', false);

        $this->getJson("/api/v1/vehicle-pdf-exports/{$export->id}")
            ->assertForbidden();
    }

    public function test_web_download_stops_for_whoever_sold_the_vehicle(): void
    {
        $seller = User::factory()->asUser()->create();
        $vehicle = $this->vehicleOwnedBy($seller);
        $export = $this->exportFor($seller, $vehicle);

        $this->sellVehicle($seller, $vehicle);

        $this->actingAs($seller)
            ->get('/usuario/exportacoes-pdf/'.$export->id.'/historico_teste.pdf')
            ->assertForbidden();
    }

    public function test_current_owner_still_downloads(): void
    {
        $owner = User::factory()->asUser()->create();
        $vehicle = $this->vehicleOwnedBy($owner);
        $export = $this->exportFor($owner, $vehicle);

        $this->actingAs($owner)
            ->get('/usuario/exportacoes-pdf/'.$export->id.'/historico_teste.pdf')
            ->assertOk()
            ->assertDownload('historico_teste.pdf');

        $this->actingAsApiUser($owner);

        $this->get("/api/v1/vehicle-pdf-exports/{$export->id}/download")
            ->assertOk()
            ->assertDownload('historico_teste.pdf');
    }

    public function test_admin_still_downloads(): void
    {
        $owner = User::factory()->asUser()->create();
        $vehicle = $this->vehicleOwnedBy($owner);
        $admin = User::factory()->asUser()->asAdmin()->create();
        $export = $this->exportFor($admin, $vehicle);

        $this->actingAsApiUser($admin);

        $this->get("/api/v1/vehicle-pdf-exports/{$export->id}/download")
            ->assertOk()
            ->assertDownload('historico_teste.pdf');
    }

    public function test_dealer_with_the_history_released_still_downloads(): void
    {
        $owner = User::factory()->asUser()->create();
        $vehicle = $this->vehicleOwnedBy($owner);
        $dealer = $this->dealerHolding($vehicle);
        $consignment = $this->consignmentFor($dealer, $vehicle);
        $consignment->update(['history_access_status' => VehicleConsignment::HISTORY_APPROVED]);

        $export = $this->exportFor($dealer, $vehicle);

        $this->actingAsApiUser($dealer);

        $this->get("/api/v1/vehicle-pdf-exports/{$export->id}/download")
            ->assertOk()
            ->assertDownload('historico_teste.pdf');
    }

    public function test_dealer_stops_downloading_once_the_consignment_ends(): void
    {
        $owner = User::factory()->asUser()->create();
        $vehicle = $this->vehicleOwnedBy($owner);
        $dealer = $this->dealerHolding($vehicle);
        $consignment = $this->consignmentFor($dealer, $vehicle);
        $consignment->update(['history_access_status' => VehicleConsignment::HISTORY_APPROVED]);

        $export = $this->exportFor($dealer, $vehicle);

        $consignment->update([
            'status' => VehicleConsignment::STATUS_ENDED,
            'ended_at' => now(),
            'end_reason' => 'sold',
        ]);

        $this->actingAsApiUser($dealer);

        $this->getJson("/api/v1/vehicle-pdf-exports/{$export->id}/download")
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    private function vehicleOwnedBy(User $owner): Vehicle
    {
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($owner, $vehicle);

        return $vehicle;
    }

    private function exportFor(User $user, Vehicle $vehicle, ?Carbon $expiresAt = null): VehiclePdfExport
    {
        return VehiclePdfExport::factory()->completed()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'file_path' => self::PATH,
            'filename' => 'historico_teste.pdf',
            'expires_at' => $expiresAt ?? now()->addDay(),
        ]);
    }

    private function sellVehicle(User $seller, Vehicle $vehicle): void
    {
        $seller->vehicles()->updateExistingPivot($vehicle->id, ['is_current_owner' => false]);

        $this->attachVehicleToUser(User::factory()->asUser()->create(), $vehicle);
    }

    private function dealerHolding(Vehicle $vehicle): User
    {
        $dealer = User::factory()->asGarage()->create()->refresh();

        $dealer->vehicles()->attach($vehicle->id, [
            'is_current_owner' => false,
            'purchase_date' => now(),
            'tenant_id' => $dealer->tenant_id,
            'ownership_type' => 'consignment',
        ]);

        return $dealer;
    }

    private function consignmentFor(User $dealer, Vehicle $vehicle): VehicleConsignment
    {
        return VehicleConsignment::factory()->create([
            'vehicle_id' => $vehicle->id,
            'garage_user_id' => $dealer->id,
            'tenant_id' => $dealer->tenant_id,
            'power_of_attorney_path' => 'procuracoes/teste.pdf',
        ]);
    }
}
