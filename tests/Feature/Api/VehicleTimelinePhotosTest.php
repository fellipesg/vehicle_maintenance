<?php

namespace Tests\Feature\Api;

use App\Models\Maintenance;
use App\Models\MaintenancePhoto;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\BuildsOwnerlessRecords;
use Tests\TestCase;

/**
 * photos_count, photos_locked e photo_preview na linha do tempo (GET /api/v1/vehicles/{id}/timeline)
 * e photos_count/photos_locked no detalhe da manutenção.
 */
class VehicleTimelinePhotosTest extends TestCase
{
    use BuildsOwnerlessRecords, RefreshDatabase;

    private User $owner;

    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableMaintenancePhotosForEveryone();

        Storage::fake('public');
        $this->owner = User::factory()->asUser()->create()->refresh();
        $this->vehicle = Vehicle::factory()->create(['current_kilometers' => 90_000]);
        $this->attachVehicleToUser($this->owner, $this->vehicle);
    }

    private function maintenanceFor(?User $declaredBy, int $kilometers, ?int $tenantId = null): Maintenance
    {
        return Maintenance::factory()->declaredByOwner()->create([
            'vehicle_id' => $this->vehicle->id,
            'user_id' => $declaredBy?->id ?? $this->owner->id,
            'tenant_id' => $tenantId ?? $declaredBy?->tenant_id ?? $this->owner->tenant_id,
            'kilometers' => $kilometers,
        ]);
    }

    private function addPhoto(Maintenance $maintenance, string $subject, string $stage, int $sort): MaintenancePhoto
    {
        return MaintenancePhoto::factory()->processed()->create([
            'maintenance_id' => $maintenance->id,
            'subject' => $subject,
            'stage' => $stage,
            'sort' => $sort,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function eventFor(Maintenance $maintenance, User $viewer): array
    {
        $this->actingAsApiUser($viewer);

        $events = $this->getJson("/api/v1/vehicles/{$this->vehicle->id}/timeline")->assertOk()->json('data.events');

        return collect($events)->firstWhere('id', $maintenance->id);
    }

    public function test_own_record_counts_every_photo_and_previews_first_before_and_first_after(): void
    {
        $maintenance = $this->maintenanceFor($this->owner, 50_000);
        $this->addPhoto($maintenance, 'vehicle', 'before', 2);
        $firstBefore = $this->addPhoto($maintenance, 'vehicle', 'before', 1);
        $firstAfter = $this->addPhoto($maintenance, 'vehicle', 'after', 1);
        $this->addPhoto($maintenance, 'vehicle', 'after', 2);
        $this->addPhoto($maintenance, 'part', 'before', 0);
        $this->addPhoto($maintenance, 'vehicle', 'during', 0);

        $event = $this->eventFor($maintenance, $this->owner);

        $this->assertSame(6, $event['photos_count']);
        $this->assertFalse($event['photos_locked']);
        $this->assertSame([$firstBefore->id, $firstAfter->id], array_column($event['photo_preview'], 'id'));
        $this->assertSame(['thumb', 'display', 'master'], array_keys($event['photo_preview'][0]['variants']));
        $this->assertArrayNotHasKey('path', $event['photo_preview'][0]);
    }

    public function test_record_without_photos_reports_zero(): void
    {
        $maintenance = $this->maintenanceFor($this->owner, 50_000);

        $event = $this->eventFor($maintenance, $this->owner);

        $this->assertSame(0, $event['photos_count']);
        $this->assertFalse($event['photos_locked']);
        $this->assertSame([], $event['photo_preview']);
    }

    public function test_record_from_a_previous_owner_only_exposes_vehicle_after_photos(): void
    {
        $previousOwner = User::factory()->asUser()->create()->refresh();
        $maintenance = $this->maintenanceFor($previousOwner, 30_000);
        $this->addPhoto($maintenance, 'vehicle', 'before', 0);
        $this->addPhoto($maintenance, 'part', 'after', 0);
        $afterOne = $this->addPhoto($maintenance, 'vehicle', 'after', 1);
        $afterTwo = $this->addPhoto($maintenance, 'vehicle', 'after', 2);
        $this->addPhoto($maintenance, 'vehicle', 'after', 3);

        $event = $this->eventFor($maintenance, $this->owner);

        $this->assertSame(3, $event['photos_count']);
        $this->assertFalse($event['photos_locked']);
        $this->assertSame([$afterOne->id, $afterTwo->id], array_column($event['photo_preview'], 'id'));
    }

    public function test_pending_ownerless_record_is_locked_for_an_unverified_owner(): void
    {
        $this->vehicle->owners()->detach();
        $workshopUser = $this->workshopAccount();
        $record = $this->ownerlessRecord($workshopUser, $this->vehicle);
        $this->addPhoto($record, 'vehicle', 'after', 0);
        $this->addPhoto($record, 'vehicle', 'before', 0);
        $unverified = $this->ownerOf($this->vehicle, verified: false);

        $event = $this->eventFor($record, $unverified);

        $this->assertTrue($event['photos_locked']);
        $this->assertSame(2, $event['photos_count']);
        $this->assertSame([], $event['photo_preview']);
    }

    public function test_pending_ownerless_record_previews_for_the_verified_owner(): void
    {
        $this->vehicle->owners()->detach();
        $workshopUser = $this->workshopAccount();
        $record = $this->ownerlessRecord($workshopUser, $this->vehicle);
        $before = $this->addPhoto($record, 'vehicle', 'before', 0);
        $after = $this->addPhoto($record, 'vehicle', 'after', 0);
        $this->addPhoto($record, 'part', 'before', 0);
        $verified = $this->ownerOf($this->vehicle);

        $event = $this->eventFor($record, $verified);

        $this->assertFalse($event['photos_locked']);
        $this->assertSame(3, $event['photos_count']);
        $this->assertSame([$before->id, $after->id], array_column($event['photo_preview'], 'id'));
    }

    public function test_accepted_ownerless_record_is_not_locked(): void
    {
        $this->vehicle->owners()->detach();
        $workshopUser = $this->workshopAccount();
        $record = $this->ownerlessRecord($workshopUser, $this->vehicle);
        $record->forceFill(['attachments_status' => Maintenance::ATTACHMENTS_ACCEPTED])->save();
        $this->addPhoto($record, 'vehicle', 'after', 0);
        $unverified = $this->ownerOf($this->vehicle, verified: false);

        $event = $this->eventFor($record, $unverified);

        $this->assertFalse($event['photos_locked']);
        $this->assertCount(1, $event['photo_preview']);
    }

    public function test_timeline_query_count_does_not_grow_with_the_number_of_maintenances(): void
    {
        $previousOwner = User::factory()->asUser()->create()->refresh();
        $first = $this->maintenanceFor($this->owner, 10_000);
        $this->addPhoto($first, 'vehicle', 'before', 0);
        $this->addPhoto($first, 'vehicle', 'after', 0);
        $this->actingAsApiUser($this->owner);
        $uri = "/api/v1/vehicles/{$this->vehicle->id}/timeline";

        $this->getJson($uri)->assertOk();
        $queriesWithOne = $this->countQueries(fn () => $this->getJson($uri)->assertOk());

        foreach ([20_000, 30_000, 40_000, 50_000] as $index => $kilometers) {
            $maintenance = $this->maintenanceFor($index % 2 === 0 ? $this->owner : $previousOwner, $kilometers);
            $this->addPhoto($maintenance, 'vehicle', 'before', 0);
            $this->addPhoto($maintenance, 'vehicle', 'after', 0);
            $this->addPhoto($maintenance, 'vehicle', 'after', 1);
        }

        $queriesWithFive = $this->countQueries(fn () => $this->getJson($uri)->assertOk());

        $this->assertSame($queriesWithOne, $queriesWithFive);
        $this->assertSame(5, collect($this->getJson($uri)->json('data.events'))->where('type', 'maintenance')->count());
    }

    public function test_maintenance_detail_reports_count_and_locked_state(): void
    {
        $maintenance = $this->maintenanceFor($this->owner, 50_000);
        $this->addPhoto($maintenance, 'vehicle', 'after', 0);
        $this->addPhoto($maintenance, 'part', 'before', 0);
        $this->actingAsApiUser($this->owner);

        $this->getJson("/api/v1/maintenances/{$maintenance->id}")
            ->assertOk()
            ->assertJsonPath('data.photos_count', 2)
            ->assertJsonPath('data.photos_locked', false);
    }

    public function test_vehicle_maintenance_list_reports_photos_count_without_loading_photos(): void
    {
        $maintenance = $this->maintenanceFor($this->owner, 50_000);
        $this->addPhoto($maintenance, 'vehicle', 'after', 0);
        $this->actingAsApiUser($this->owner);

        $json = $this->getJson("/api/v1/vehicles/{$this->vehicle->id}/maintenances")->assertOk()->json('data.0');

        $this->assertSame(1, $json['photos_count']);
        $this->assertArrayNotHasKey('photos', $json);
    }
}
