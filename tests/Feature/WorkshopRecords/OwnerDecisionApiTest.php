<?php

namespace Tests\Feature\WorkshopRecords;

use App\Models\Maintenance;
use App\Models\User;
use App\Notifications\WorkshopRecordsPendingNotification;
use App\Services\FcmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Concerns\BuildsOwnerlessRecords;
use Tests\TestCase;

/**
 * API do proprietário: lista dos registros de oficinas e a decisão (vincular, anexos, ocultar).
 */
class OwnerDecisionApiTest extends TestCase
{
    use BuildsOwnerlessRecords;
    use RefreshDatabase;

    /**
     * @param  array<string, bool>  $body
     * @return array<string, bool>
     */
    private function decision(array $body = []): array
    {
        return $body + ['link' => true, 'attach_files' => false, 'hide_from_public' => false];
    }

    public function test_pending_list_returns_the_contract_without_free_text_or_prices(): void
    {
        $workshop = $this->workshopAccount();
        $vehicle = $this->ownerlessVehicle();
        $record = $this->withPendingAttachments($this->ownerlessRecord($workshop, $vehicle));
        $owner = $this->ownerOf($vehicle);

        $this->actingAsApiUser($owner);
        $response = $this->getJson('/api/v1/me/workshop-records')->assertOk();
        $item = $response->json('data.0');

        $this->assertSame($record->id, $item['id']);
        $this->assertSame(['id' => $vehicle->id, 'brand' => 'Fiat', 'model' => 'Argo', 'year' => 2021, 'chassis_masked' => $item['vehicle']['chassis_masked']], $item['vehicle']);
        $this->assertStringNotContainsString(self::OWNERLESS_CHASSIS, $response->getContent());
        $this->assertSame('Filtro de óleo', $item['items'][0]['name']);
        $this->assertSame(['invoices' => 1, 'photos' => 1], $item['attachments']);
        $this->assertSame('pending', $item['owner_status']);
        $this->assertSame('pending', $item['attachments_status']);
        $this->assertFalse($item['hidden_from_public']);
        $this->assertTrue($item['can_accept_attachments']);
        $this->assertSame($workshop->workshop->id, $item['workshop']['id']);
        $this->assertStringNotContainsString('CPF', $response->getContent());
        $this->assertStringNotContainsString('55.9', $response->getContent());
    }

    public function test_list_only_covers_vehicles_the_user_owns_and_status_all_includes_decided(): void
    {
        $workshop = $this->workshopAccount();
        $mine = $this->ownerlessVehicle();
        $other = $this->ownerlessVehicle(['chassis' => '9BWZZZ377VT009999']);
        $this->ownerlessRecord($workshop, $mine);
        $this->ownerlessRecord($workshop, $other);
        $decided = $this->ownerlessRecord($workshop, $mine, ['maintenance_type' => 'Alinhamento']);
        $decided->forceFill(['owner_status' => Maintenance::OWNER_DECLINED])->save();
        $owner = $this->ownerOf($mine);

        $this->actingAsApiUser($owner);
        $this->getJson('/api/v1/me/workshop-records')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/me/workshop-records?status=all')->assertJsonCount(2, 'data');
    }

    public function test_me_reports_the_pending_count(): void
    {
        $vehicle = $this->ownerlessVehicle();
        $this->ownerlessRecord($this->workshopAccount(), $vehicle);
        $owner = $this->ownerOf($vehicle);

        $this->actingAsApiUser($owner);
        $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.pending_workshop_records_count', 1);
    }

    public function test_link_moves_the_record_to_the_owner_tenant(): void
    {
        $vehicle = $this->ownerlessVehicle();
        $record = $this->ownerlessRecord($this->workshopAccount(), $vehicle);
        $owner = $this->ownerOf($vehicle);

        $this->actingAsApiUser($owner);
        $this->postJson("/api/v1/maintenances/{$record->id}/owner-decision", $this->decision())
            ->assertOk()
            ->assertJsonPath('data.owner_status', 'linked')
            ->assertJsonPath('data.attachments_status', 'none');

        $this->assertSame($owner->tenant_id, $record->fresh()->tenant_id);
        $this->getJson('/api/v1/maintenances')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/me')->assertJsonPath('data.pending_workshop_records_count', 0);
    }

    public function test_decline_keeps_the_minimal_record_and_deletes_the_files(): void
    {
        $vehicle = $this->ownerlessVehicle();
        $record = $this->withPendingAttachments($this->ownerlessRecord($this->workshopAccount(), $vehicle));
        $owner = $this->ownerOf($vehicle);

        $this->actingAsApiUser($owner);
        $this->postJson("/api/v1/maintenances/{$record->id}/owner-decision", $this->decision(['link' => false]))
            ->assertOk()
            ->assertJsonPath('data.owner_status', 'declined')
            ->assertJsonPath('data.attachments_status', 'declined');

        $fresh = $record->fresh();
        $this->assertNull($fresh->tenant_id);
        $this->assertSame(0, $fresh->invoices()->count() + $fresh->photos()->count());
        $this->assertNotNull(Maintenance::find($record->id));
    }

    public function test_hide_sets_the_timestamp_and_can_be_undone(): void
    {
        $vehicle = $this->ownerlessVehicle();
        $record = $this->ownerlessRecord($this->workshopAccount(), $vehicle);
        $owner = $this->ownerOf($vehicle);

        $this->actingAsApiUser($owner);
        $this->postJson("/api/v1/maintenances/{$record->id}/owner-decision", $this->decision(['hide_from_public' => true]))
            ->assertOk()->assertJsonPath('data.hidden_from_public', true);
        $this->assertNotNull($record->fresh()->hidden_from_public_at);
        $this->assertSame($owner->id, $record->fresh()->owner_decided_by_user_id);

        $this->postJson("/api/v1/maintenances/{$record->id}/owner-decision", $this->decision())
            ->assertOk()->assertJsonPath('data.hidden_from_public', false);
    }

    public function test_attach_with_verified_ownership_makes_the_files_normal_attachments(): void
    {
        $vehicle = $this->ownerlessVehicle();
        $record = $this->withPendingAttachments($this->ownerlessRecord($this->workshopAccount(), $vehicle));
        $owner = $this->ownerOf($vehicle, verified: true);

        $this->actingAsApiUser($owner);
        $this->postJson("/api/v1/maintenances/{$record->id}/owner-decision", $this->decision(['attach_files' => true]))
            ->assertOk()
            ->assertJsonPath('data.attachments_status', 'accepted');

        $json = $this->getJson("/api/v1/maintenances/{$record->id}")->assertOk()->json('data');
        $this->assertCount(1, $json['invoices']);
        $this->assertNotNull($json['description']);
    }

    public function test_attach_is_refused_when_ownership_is_not_verified(): void
    {
        $vehicle = $this->ownerlessVehicle();
        $record = $this->withPendingAttachments($this->ownerlessRecord($this->workshopAccount(), $vehicle));
        $owner = $this->ownerOf($vehicle, verified: false);

        $this->actingAsApiUser($owner);
        $this->getJson('/api/v1/me/workshop-records')
            ->assertJsonPath('data.0.can_accept_attachments', false)
            ->assertJsonPath('data.0.can_decide', false);
        $this->postJson("/api/v1/maintenances/{$record->id}/owner-decision", $this->decision(['attach_files' => true]))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Para decidir sobre os registros da oficina, confirme que o veículo é seu enviando o CRLV-e.');

        $this->assertSame('pending', $record->fresh()->attachments_status);
        $this->assertSame(1, $record->fresh()->invoices()->count());
    }

    public function test_unverified_owner_cannot_decline_hide_or_link(): void
    {
        $vehicle = $this->ownerlessVehicle();
        $record = $this->withPendingAttachments($this->ownerlessRecord($this->workshopAccount(), $vehicle));
        $owner = $this->ownerOf($vehicle, verified: false);

        $this->actingAsApiUser($owner);
        foreach ([['link' => false], ['hide_from_public' => true], []] as $choice) {
            $this->postJson("/api/v1/maintenances/{$record->id}/owner-decision", $this->decision($choice))
                ->assertStatus(422)
                ->assertJsonPath('message', 'Para decidir sobre os registros da oficina, confirme que o veículo é seu enviando o CRLV-e.');
        }

        $fresh = $record->fresh();
        $this->assertSame('pending', $fresh->owner_status ?? 'pending');
        $this->assertSame('pending', $fresh->attachments_status);
        $this->assertNull($fresh->hidden_from_public_at);
        $this->assertNull($fresh->tenant_id);
        $this->assertSame(1, $fresh->invoices()->count());
    }

    public function test_attach_without_link_is_refused(): void
    {
        $vehicle = $this->ownerlessVehicle();
        $record = $this->withPendingAttachments($this->ownerlessRecord($this->workshopAccount(), $vehicle));
        $owner = $this->ownerOf($vehicle);

        $this->actingAsApiUser($owner);
        $this->postJson("/api/v1/maintenances/{$record->id}/owner-decision", $this->decision(['link' => false, 'attach_files' => true]))
            ->assertStatus(422);
    }

    public function test_revoke_after_accepting_deletes_the_files(): void
    {
        $vehicle = $this->ownerlessVehicle();
        $record = $this->withPendingAttachments($this->ownerlessRecord($this->workshopAccount(), $vehicle));
        $owner = $this->ownerOf($vehicle);

        $this->actingAsApiUser($owner);
        $this->postJson("/api/v1/maintenances/{$record->id}/owner-decision", $this->decision(['attach_files' => true]))->assertOk();
        $this->postJson("/api/v1/maintenances/{$record->id}/owner-decision", $this->decision(['attach_files' => false]))
            ->assertOk()->assertJsonPath('data.attachments_status', 'revoked');

        $this->assertSame(0, $record->fresh()->invoices()->count() + $record->fresh()->photos()->count());
    }

    public function test_only_the_current_owner_decides(): void
    {
        $vehicle = $this->ownerlessVehicle();
        $record = $this->ownerlessRecord($this->workshopAccount(), $vehicle);
        $this->ownerOf($vehicle);

        $this->actingAsApiUser(User::factory()->asUser()->create());
        $this->postJson("/api/v1/maintenances/{$record->id}/owner-decision", $this->decision())->assertForbidden();
        $this->assertSame('pending', $record->fresh()->owner_status);
    }

    public function test_decision_on_an_ordinary_maintenance_is_refused(): void
    {
        $vehicle = $this->ownerlessVehicle();
        $owner = $this->ownerOf($vehicle);
        $ordinary = Maintenance::factory()->create(['vehicle_id' => $vehicle->id, 'user_id' => $owner->id, 'tenant_id' => $owner->tenant_id]);

        $this->actingAsApiUser($owner);
        $this->postJson("/api/v1/maintenances/{$ordinary->id}/owner-decision", $this->decision())->assertStatus(422);
    }

    public function test_arrival_by_api_manual_create_links_unverified_and_notifies(): void
    {
        Notification::fake();
        $this->mock(FcmService::class)->shouldReceive('sendToUser')->once();
        $vehicle = $this->ownerlessVehicle();
        $this->ownerlessRecord($this->workshopAccount(), $vehicle);
        $owner = $this->actingAsApiUser();

        $this->postJson('/api/v1/vehicles', [
            'license_plate' => 'ABC1D23',
            'renavam' => '12345678901',
            'brand' => 'Fiat',
            'model' => 'Argo',
            'year' => 2021,
            'chassis' => self::OWNERLESS_CHASSIS,
            'current_kilometers' => 50_000,
            'terms_accepted' => true,
        ])->assertCreated()->assertJsonPath('data.id', $vehicle->id);

        $this->assertSame(1, \App\Models\Vehicle::count());
        $this->assertSame('ABC1D23', $vehicle->fresh()->license_plate);
        $this->assertNull($owner->vehicles()->first()->pivot->ownership_verified_at);

        Notification::assertSentTo($owner, WorkshopRecordsPendingNotification::class, function ($notification) use ($vehicle) {
            $data = $notification->toArray($notification);

            return $data['type'] === 'workshop_records_pending' && $data['vehicle_id'] === $vehicle->id && $data['count'] === 1;
        });
    }

    public function test_manual_create_with_the_chassis_of_an_owned_vehicle_is_still_refused(): void
    {
        $vehicle = $this->ownerlessVehicle();
        $this->ownerOf($vehicle);
        $this->actingAsApiUser();

        $this->postJson('/api/v1/vehicles', [
            'license_plate' => 'ABC1D23', 'renavam' => '12345678901', 'brand' => 'Fiat', 'model' => 'Argo',
            'year' => 2021, 'chassis' => self::OWNERLESS_CHASSIS, 'current_kilometers' => 1, 'terms_accepted' => true,
        ])->assertStatus(422);
    }

    public function test_no_notification_when_the_vehicle_has_no_pending_records(): void
    {
        Notification::fake();
        $vehicle = $this->ownerlessVehicle();
        $owner = $this->actingAsApiUser();

        $this->postJson('/api/v1/vehicles', [
            'license_plate' => 'ABC1D23', 'renavam' => '12345678901', 'brand' => 'Fiat', 'model' => 'Argo',
            'year' => 2021, 'chassis' => self::OWNERLESS_CHASSIS, 'current_kilometers' => 50_000, 'terms_accepted' => true,
        ])->assertCreated();

        Notification::assertNothingSentTo($owner);
        $this->assertNotNull($vehicle);
    }
}
