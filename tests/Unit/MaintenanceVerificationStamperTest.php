<?php

namespace Tests\Unit;

use App\Models\Maintenance;
use App\Models\User;
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
}
