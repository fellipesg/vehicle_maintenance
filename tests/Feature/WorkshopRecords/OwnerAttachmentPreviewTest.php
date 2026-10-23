<?php

namespace Tests\Feature\WorkshopRecords;

use App\Models\Invoice;
use App\Models\Maintenance;
use App\Models\MaintenancePhoto;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\BuildsOwnerlessRecords;
use Tests\TestCase;

/**
 * Prévia do proprietário: o dono atual com a posse comprovada (ownership_verified_at, via CRLV-e)
 * vê as fotos e as notas de uma OS de oficina antes de aceitar, para decidir informado. Oficina
 * autora também vê; dono sem comprovação, ex-dono, outro usuário e visitante continuam sem ver.
 */
class OwnerAttachmentPreviewTest extends TestCase
{
    use BuildsOwnerlessRecords, RefreshDatabase;

    private User $workshopUser;

    private Vehicle $vehicle;

    private Maintenance $record;

    private MaintenancePhoto $photo;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->workshopUser = $this->workshopAccount();
        $this->vehicle = $this->ownerlessVehicle();
        $this->record = $this->withPendingAttachments($this->ownerlessRecord($this->workshopUser, $this->vehicle));
        $this->photo = $this->record->photos()->firstOrFail();
        Storage::disk('public')->put($this->photo->path, 'bytes');
    }

    /**
     * @return array<string, array{0: ?User, 1: bool}>
     */
    private function viewers(): array
    {
        $former = $this->ownerOf($this->vehicle);
        $this->vehicle->owners()->updateExistingPivot($former->id, ['is_current_owner' => false, 'sale_date' => now()]);

        return [
            'workshop author' => [$this->workshopUser, true],
            'verified current owner' => [$this->ownerOf($this->vehicle), true],
            'unverified current owner' => [$this->ownerOf($this->vehicle, verified: false), false],
            'former verified owner' => [$former, false],
            'other user' => [User::factory()->asUser()->create()->refresh(), false],
            'guest' => [null, false],
        ];
    }

    public function test_model_decides_who_sees_the_attachments(): void
    {
        foreach ($this->viewers() as $label => [$viewer, $canSee]) {
            $this->assertSame(! $canSee, $this->record->hidesAttachmentsFrom($viewer), $label);
        }
    }

    public function test_photo_policy_follows_the_same_matrix(): void
    {
        foreach ($this->viewers() as $label => [$viewer, $canSee]) {
            if ($viewer === null) {
                continue;
            }

            $this->photo->unsetRelation('maintenance');
            $this->photo->load('maintenance.vehicle');

            $this->assertSame($canSee, Gate::forUser($viewer)->allows('view', $this->photo), $label);
        }
    }

    public function test_invoice_policy_follows_the_same_matrix(): void
    {
        $invoice = Invoice::query()->where('maintenance_id', $this->record->id)->firstOrFail();

        foreach ($this->viewers() as $label => [$viewer, $canSee]) {
            if ($viewer === null) {
                continue;
            }

            $this->assertSame($canSee, Gate::forUser($viewer)->allows('view', $invoice->load('maintenance.vehicle')), $label);
        }
    }

    public function test_photo_file_route_answers_per_viewer(): void
    {
        foreach ($this->viewers() as $label => [$viewer, $canSee]) {
            if ($viewer === null) {
                $this->app['auth']->forgetGuards();
                $this->getJson(route('api.maintenance-photos.show', $this->photo))->assertUnauthorized();

                continue;
            }

            $this->actingAsApiUser($viewer);
            $response = $this->getJson(route('api.maintenance-photos.show', $this->photo));

            $canSee ? $response->assertOk() : $response->assertForbidden();
            $this->assertTrue(true, $label);
        }
    }

    public function test_maintenance_detail_payload_for_the_verified_owner_has_photos_and_invoices_but_not_the_details(): void
    {
        $owner = $this->ownerOf($this->vehicle);
        $this->actingAsApiUser($owner);

        $json = $this->getJson("/api/v1/maintenances/{$this->record->id}")->assertOk()->json('data');

        $this->assertFalse($json['photos_locked']);
        $this->assertSame(1, $json['photos_count']);
        $this->assertCount(1, $json['photos']);
        $this->assertArrayHasKey('variants', $json['photos'][0]);
        $this->assertCount(1, $json['invoices']);
        $this->assertNull($json['description']);
        $this->assertNull($json['items'][0]['unit_price']);
        $this->assertSame(Maintenance::ATTACHMENTS_PENDING, $json['attachments_status']);
    }

    public function test_maintenance_detail_payload_for_the_unverified_owner_is_locked(): void
    {
        $this->actingAsApiUser($this->ownerOf($this->vehicle, verified: false));

        $json = $this->getJson("/api/v1/maintenances/{$this->record->id}")->assertOk()->json('data');

        $this->assertTrue($json['photos_locked']);
        $this->assertSame(1, $json['photos_count']);
        $this->assertSame([], $json['photos']);
        $this->assertSame([], $json['invoices']);
    }

    public function test_maintenance_detail_for_the_workshop_author_is_complete(): void
    {
        $this->actingAsApiUser($this->workshopUser);

        $json = $this->getJson("/api/v1/maintenances/{$this->record->id}")->assertOk()->json('data');

        $this->assertFalse($json['photos_locked']);
        $this->assertCount(1, $json['photos']);
        $this->assertNotNull($json['description']);
    }

    public function test_other_user_and_guest_cannot_open_the_maintenance(): void
    {
        $this->actingAsApiUser();
        $this->getJson("/api/v1/maintenances/{$this->record->id}")->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->getJson("/api/v1/maintenances/{$this->record->id}")->assertUnauthorized();
    }

    public function test_verified_owner_still_cannot_see_the_variants_of_a_record_hidden_from_public_by_someone_else(): void
    {
        $this->record->forceFill(['hidden_from_public_at' => now(), 'owner_decided_by_user_id' => User::factory()->asUser()->create()->id])->save();
        $this->actingAsApiUser($this->ownerOf($this->vehicle));

        $this->getJson("/api/v1/maintenances/{$this->record->id}")->assertForbidden();
    }
}
