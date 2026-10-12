<?php

namespace Tests\Feature\WorkshopRecords;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Vehicle\VehicleMaintenancePdfExporter;
use App\Support\DemoWarrantyPdfValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\BuildsOwnerlessRecords;
use Tests\TestCase;

/**
 * O PDF do histórico pedido pelo proprietário: registros que ele vinculou (e ocultou da consulta
 * pública) saem completos; só os pendentes e os recusados saem na forma mínima.
 */
class OwnerPdfExportTest extends TestCase
{
    use BuildsOwnerlessRecords;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    /**
     * @return array{0: Vehicle, 1: User}
     */
    private function vehicleWithDecidedRecords(): array
    {
        $workshop = $this->workshopAccount();
        $vehicle = $this->ownerlessVehicle(['license_plate' => 'PDF1A23', 'renavam' => '12345678901']);
        $owner = $this->ownerOf($vehicle);

        $linked = $this->ownerlessRecord($workshop, $vehicle, ['description' => 'Texto livre vinculado', 'maintenance_type' => 'Serviço vinculado']);
        $linked->forceFill([
            'tenant_id' => $owner->tenant_id,
            'owner_status' => Maintenance::OWNER_LINKED,
            'attachments_status' => Maintenance::ATTACHMENTS_ACCEPTED,
            'owner_decided_by_user_id' => $owner->id,
        ])->save();

        $hidden = $this->ownerlessRecord($workshop, $vehicle, ['description' => 'Texto livre oculto', 'maintenance_type' => 'Serviço oculto']);
        $hidden->forceFill([
            'tenant_id' => $owner->tenant_id,
            'owner_status' => Maintenance::OWNER_LINKED,
            'attachments_status' => Maintenance::ATTACHMENTS_DECLINED,
            'hidden_from_public_at' => now(),
            'owner_decided_by_user_id' => $owner->id,
        ])->save();

        $this->ownerlessRecord($workshop, $vehicle, ['description' => 'Texto livre pendente', 'maintenance_type' => 'Serviço pendente']);

        $declined = $this->ownerlessRecord($workshop, $vehicle, ['description' => 'Texto livre recusado', 'maintenance_type' => 'Serviço recusado']);
        $declined->forceFill([
            'owner_status' => Maintenance::OWNER_DECLINED,
            'attachments_status' => Maintenance::ATTACHMENTS_DECLINED,
            'owner_decided_by_user_id' => $owner->id,
        ])->save();

        return [$vehicle->fresh(), $owner];
    }

    public function test_owner_export_shows_linked_records_in_full_even_when_hidden_and_pending_or_declined_minimal(): void
    {
        [$vehicle, $owner] = $this->vehicleWithDecidedRecords();

        $file = app(VehicleMaintenancePdfExporter::class)->generate($vehicle, viewer: $owner);

        try {
            $text = DemoWarrantyPdfValidator::extractText($file['content']);
        } finally {
            app(VehicleMaintenancePdfExporter::class)->cleanupTemps($file['temps']);
        }

        $this->assertStringContainsString('Texto livre vinculado', $text);
        $this->assertStringContainsString('Serviço oculto', $text);
        $this->assertStringContainsString('Texto livre oculto', $text);
        $this->assertStringContainsString('Serviço pendente', $text);
        $this->assertStringNotContainsString('Texto livre pendente', $text);
        $this->assertStringContainsString('Serviço recusado', $text);
        $this->assertStringNotContainsString('Texto livre recusado', $text);
    }

    public function test_export_for_someone_else_drops_the_hidden_record(): void
    {
        [$vehicle] = $this->vehicleWithDecidedRecords();

        $file = app(VehicleMaintenancePdfExporter::class)->generate($vehicle, viewer: $this->workshopAccount());

        try {
            $text = DemoWarrantyPdfValidator::extractText($file['content']);
        } finally {
            app(VehicleMaintenancePdfExporter::class)->cleanupTemps($file['temps']);
        }

        $this->assertStringContainsString('Serviço vinculado', $text);
        $this->assertStringNotContainsString('Serviço oculto', $text);
        $this->assertStringNotContainsString('Texto livre pendente', $text);
    }
}
