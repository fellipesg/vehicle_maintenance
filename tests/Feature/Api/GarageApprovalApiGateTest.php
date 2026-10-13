<?php

namespace Tests\Feature\Api;

use App\Enums\GarageStatus;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O portão de aprovação da loja valia só na web, e o app fala pela API: uma loja em análise
 * conseguia criar veículo, escrever no histórico de um chassi e mandar convite para terceiros.
 */
class GarageApprovalApiGateTest extends TestCase
{
    use RefreshDatabase;

    private function dealer(GarageStatus $status): User
    {
        $dealer = User::factory()->asGarage()->create();
        $dealer->garage->forceFill(['status' => $status])->save();

        return $this->actingAsApiUser($dealer->fresh());
    }

    public function test_pending_store_cannot_create_a_vehicle(): void
    {
        $this->dealer(GarageStatus::Pending);

        $this->postJson('/api/v1/vehicles', [
            'license_plate' => 'ABC1D23',
            'brand' => 'Honda',
            'model' => 'Civic',
            'year' => 2020,
            'chassis' => '9BRVLDEMO00000099',
        ])->assertForbidden();

        $this->assertSame(0, Vehicle::count());
    }

    public function test_pending_store_cannot_register_a_maintenance(): void
    {
        $this->dealer(GarageStatus::Pending);

        $this->postJson('/api/v1/maintenances', [
            'vehicle_id' => 1,
            'maintenance_type' => 'Troca de óleo',
            'maintenance_date' => '2026-01-10',
        ])->assertForbidden();
    }

    public function test_pending_store_cannot_send_an_invite_to_a_third_party(): void
    {
        $this->dealer(GarageStatus::Pending);

        // Era a brecha mais grave: e-mail saindo em nome da RevisaLog para quem a loja digitasse.
        $this->postJson('/api/v1/maintenances/1/invites/email', ['email' => 'alguem@example.com'])
            ->assertForbidden();
    }

    public function test_pending_store_still_reads_so_the_app_can_explain_the_wait(): void
    {
        $this->dealer(GarageStatus::Pending);

        $this->getJson('/api/v1/my-vehicles')->assertOk();
    }

    public function test_the_refusal_says_why(): void
    {
        $this->dealer(GarageStatus::Pending);

        $this->postJson('/api/v1/vehicles', [])
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Your store is still under review. You cannot create or change records yet.');
    }

    public function test_approved_store_writes_normally(): void
    {
        $this->dealer(GarageStatus::Approved);

        // Chega ao controller: a resposta é de validação, não do portão.
        $this->postJson('/api/v1/vehicles', [])->assertStatus(422);
    }

    public function test_owner_account_is_untouched_by_the_gate(): void
    {
        $this->actingAsApiUser(User::factory()->asUser()->create());

        $this->postJson('/api/v1/vehicles', [])->assertStatus(422);
    }
}
