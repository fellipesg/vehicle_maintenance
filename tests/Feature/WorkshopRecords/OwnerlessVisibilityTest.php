<?php

namespace Tests\Feature\WorkshopRecords;

use App\Models\Maintenance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\BuildsOwnerlessRecords;
use Tests\TestCase;

/**
 * LGPD: o histórico de uma OS sem proprietário mostra só o mínimo, nunca anexos, e a oculta some.
 */
class OwnerlessVisibilityTest extends TestCase
{
    use BuildsOwnerlessRecords;
    use RefreshDatabase;

    public function test_public_search_shows_only_minimal_fields_and_never_attachments(): void
    {
        $workshop = $this->workshopAccount();
        $vehicle = $this->ownerlessVehicle();
        $this->withPendingAttachments($this->ownerlessRecord($workshop, $vehicle));

        $response = $this->getJson('/api/v1/vehicles/search/'.self::OWNERLESS_CHASSIS)->assertOk();

        $record = $response->json('data.maintenances.0');
        $this->assertSame('Troca de óleo', $record['maintenance_type']);
        $this->assertSame(50_000, $record['kilometers']);
        $this->assertNull($record['description']);
        $this->assertSame([], $record['photos']);
        $this->assertStringNotContainsString('CPF', $response->getContent());
        $this->assertNull($response->json('data.license_plate'));
    }

    public function test_public_search_hides_a_record_the_owner_hid(): void
    {
        $workshop = $this->workshopAccount();
        $vehicle = $this->ownerlessVehicle();
        $record = $this->ownerlessRecord($workshop, $vehicle);
        $record->forceFill(['hidden_from_public_at' => now()])->save();

        $response = $this->getJson('/api/v1/vehicles/search/'.self::OWNERLESS_CHASSIS)->assertOk();

        $this->assertSame([], $response->json('data.maintenances'));
        $this->assertSame(0, $response->json('data.maintenances_count'));
        $this->get(route('verification.show', $record->verification_code))->assertNotFound();
    }

    public function test_seal_page_still_shows_a_pending_record_without_attachments(): void
    {
        $workshop = $this->workshopAccount();
        $record = $this->withPendingAttachments($this->ownerlessRecord($workshop, $this->ownerlessVehicle()));

        $this->get(route('verification.show', $record->verification_code))
            ->assertOk()
            ->assertSee('Troca de óleo')
            ->assertDontSee('CPF')
            ->assertDontSee('pendente.pdf');
    }

    public function test_current_owner_sees_the_minimal_form_in_the_vehicle_history_before_deciding(): void
    {
        $workshop = $this->workshopAccount();
        $vehicle = $this->ownerlessVehicle();
        $record = $this->withPendingAttachments($this->ownerlessRecord($workshop, $vehicle));
        $owner = $this->ownerOf($vehicle);

        $this->actingAsApiUser($owner);
        $json = $this->getJson("/api/v1/vehicles/{$vehicle->id}/maintenances")->assertOk()->json('data.0');

        $this->assertSame($record->id, $json['id']);
        $this->assertNull($json['description']);
        $this->assertSame([], $json['invoices']);
        $this->assertSame([], $json['photos'] ?? []);
        $this->assertNull($json['items'][0]['unit_price']);
        $this->assertTrue($json['is_ownerless_record']);

        $this->get(route('user.vehicles.show', $vehicle))->assertOk()->assertDontSee('CPF 123');
        $this->get(route('user.maintenances.show', $record))->assertOk()->assertDontSee('CPF 123');
    }

    public function test_creating_workshop_keeps_the_full_record(): void
    {
        $workshop = $this->workshopAccount();
        $record = $this->withPendingAttachments($this->ownerlessRecord($workshop, $this->ownerlessVehicle()));

        $this->actingAsApiUser($workshop);
        $json = $this->getJson("/api/v1/maintenances/{$record->id}")->assertOk()->json('data');

        $this->assertStringContainsString('CPF', $json['description']);
        $this->assertCount(1, $json['invoices']);
    }

    public function test_pending_invoice_cannot_be_downloaded_by_the_owner_before_acceptance(): void
    {
        $workshop = $this->workshopAccount();
        $vehicle = $this->ownerlessVehicle();
        $record = $this->withPendingAttachments($this->ownerlessRecord($workshop, $vehicle));
        $owner = $this->ownerOf($vehicle);

        $this->actingAsApiUser($owner);
        $this->getJson('/api/v1/invoices/'.$record->invoices()->first()->id.'/download')->assertForbidden();
    }

    public function test_other_tenant_cannot_read_the_ownerless_record_via_api(): void
    {
        $workshop = $this->workshopAccount();
        $record = $this->ownerlessRecord($workshop, $this->ownerlessVehicle());

        $this->actingAsApiUser();
        $this->getJson("/api/v1/maintenances/{$record->id}")->assertForbidden();
        $this->assertSame([], $this->getJson('/api/v1/maintenances')->json('data'));
    }

    public function test_hidden_record_stays_visible_to_the_workshop_that_made_it(): void
    {
        $workshop = $this->workshopAccount();
        $record = $this->ownerlessRecord($workshop, $this->ownerlessVehicle());
        $record->forceFill(['hidden_from_public_at' => now()])->save();

        $this->actingAsApiUser($workshop);
        $this->getJson('/api/v1/maintenances')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.hidden_from_public', true);
        $this->assertSame(Maintenance::OWNER_PENDING, $record->fresh()->owner_status);
    }
}
