<?php

namespace Tests\Unit;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Workshop;
use App\Services\Maintenance\MaintenanceVerificationStamper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceVerificationStamperTest extends TestCase
{
    use RefreshDatabase;

    public function test_stamp_is_idempotent_for_verified_workshop_maintenance(): void
    {
        $workshopUser = User::factory()->asWorkshop()->create();
        $workshopUser->load('workshop');
        $workshop = $workshopUser->workshop;
        $this->assertNotNull($workshop);

        $maintenance = Maintenance::factory()->create([
            'workshop_id' => $workshop->id,
            'user_id' => $workshopUser->id,
            'tenant_id' => $workshopUser->tenant_id,
        ]);

        $stamper = app(MaintenanceVerificationStamper::class);
        $stamper->stamp($maintenance, $workshopUser);
        $maintenance->refresh();

        $this->assertNotNull($maintenance->verified_at);

        $verifiedAt = $maintenance->verified_at;
        $code = $maintenance->verification_code;
        $workshopId = $maintenance->verified_workshop_id;

        $stamper->stamp($maintenance->fresh(), $workshopUser);
        $maintenance->refresh();

        $this->assertTrue($verifiedAt->equalTo($maintenance->verified_at));
        $this->assertSame($code, $maintenance->verification_code);
        $this->assertSame($workshopId, $maintenance->verified_workshop_id);
    }

    public function test_owner_stamp_verifies_when_linked_workshop_auto_flag_enabled(): void
    {
        config(['maintenance.auto_verify_linked_workshop' => true]);

        $owner = User::factory()->asUser()->create();
        $workshop = Workshop::factory()->create();
        $maintenance = Maintenance::factory()->create([
            'workshop_id' => $workshop->id,
            'user_id' => $owner->id,
            'tenant_id' => $owner->tenant_id,
        ]);

        $stamper = app(MaintenanceVerificationStamper::class);
        $stamper->stamp($maintenance, $owner);
        $maintenance->refresh();

        $this->assertSame('workshop', $maintenance->registered_by_type);
        $this->assertNotNull($maintenance->verified_at);
        $this->assertSame($workshop->id, $maintenance->verified_workshop_id);
        $this->assertNotNull($maintenance->verification_code);
    }

    public function test_verify_linked_workshop_maintenances_backfills_unverified_rows(): void
    {
        $workshop = Workshop::factory()->create();
        Maintenance::factory()->count(2)->create([
            'workshop_id' => $workshop->id,
            'verified_at' => null,
            'verified_workshop_id' => null,
        ]);
        Maintenance::factory()->sealedByWorkshop()->create([
            'workshop_id' => $workshop->id,
        ]);

        $stamper = app(MaintenanceVerificationStamper::class);
        $count = $stamper->verifyLinkedWorkshopMaintenances();

        $this->assertSame(2, $count);
        $this->assertSame(
            3,
            Maintenance::query()->whereNotNull('verified_at')->where('verified_workshop_id', $workshop->id)->count(),
        );
    }
}
