<?php

namespace Tests\Feature\Web;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\VehicleOwnershipEndedByTeamNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Web\Concerns\InspectsAdminPages;
use Tests\TestCase;

/**
 * A equipe encerra, pela ficha do veículo no admin, o vínculo de um dono atual
 * (POST admin/veiculos/{vehicle}/proprietarios/{user}/encerrar): o vínculo fica no histórico com quem
 * encerrou e o motivo, o ex-dono recebe um e-mail, as manutenções continuam no veículo e outra conta
 * passa a poder vinculá-lo.
 */
class AdminVehicleOwnershipEndingTest extends TestCase
{
    use InspectsAdminPages;
    use RefreshDatabase;

    private const RENAVAM = '12345678901';

    public function test_vehicle_page_offers_ending_each_current_owner(): void
    {
        [$owner, $vehicle] = $this->ownerWithVehicle();

        $xpath = $this->adminPage($this->actingAs($this->adminUser())->get(route('admin.vehicles.show', $vehicle)));

        $form = $this->adminElement($xpath, '//form[@data-slot="admin-end-ownership"]');
        $this->assertSame(route('admin.vehicles.end-ownership', [$vehicle, $owner]), $form->getAttribute('action'));
        $this->assertSame('danger', $form->getAttribute('data-confirm-variant'));
        $this->assertNotNull($xpath->query('.//input[@name="ownership_ended_reason"][@required]', $form)->item(0));
    }

    public function test_vehicle_without_current_owner_has_nothing_to_end(): void
    {
        $vehicle = Vehicle::factory()->create();

        $xpath = $this->adminPage($this->actingAs($this->adminUser())->get(route('admin.vehicles.show', $vehicle)));

        $this->assertSame(0, $xpath->query('//form[@data-slot="admin-end-ownership"]')->length);
    }

    public function test_admin_ends_the_ownership_keeping_the_link_history_and_the_maintenances(): void
    {
        Notification::fake();
        [$owner, $vehicle] = $this->ownerWithVehicle();
        $maintenance = Maintenance::factory()->for($vehicle)->create(['user_id' => $owner->id]);
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->from(route('admin.vehicles.show', $vehicle))
            ->post(route('admin.vehicles.end-ownership', [$vehicle, $owner]), [
                'ownership_ended_reason' => 'Vínculo feito na conta errada.',
            ])
            ->assertRedirect(route('admin.vehicles.show', $vehicle))
            ->assertSessionHas('success');

        $link = DB::table('user_vehicles')->where('vehicle_id', $vehicle->id)->where('user_id', $owner->id)->first();
        $this->assertNotNull($link, 'O vínculo fica no histórico, não é apagado.');
        $this->assertFalse((bool) $link->is_current_owner);
        $this->assertSame(now()->toDateString(), substr((string) $link->sale_date, 0, 10));
        $this->assertSame($admin->id, (int) $link->ownership_ended_by_user_id);
        $this->assertSame('Vínculo feito na conta errada.', $link->ownership_ended_reason);

        $this->assertModelExists($maintenance);
        $this->assertSame(0, $owner->currentVehicles()->count());

        Notification::assertSentTo($owner, VehicleOwnershipEndedByTeamNotification::class);
    }

    public function test_after_the_team_ends_it_another_account_can_link_the_vehicle(): void
    {
        Notification::fake();
        [$owner, $vehicle] = $this->ownerWithVehicle();

        $buyer = $this->actingAsApiUser();
        $this->postJson("/api/v1/vehicles/{$vehicle->id}/link", $this->document())->assertForbidden();

        $this->actingAs($this->adminUser())
            ->post(route('admin.vehicles.end-ownership', [$vehicle, $owner]), ['ownership_ended_reason' => 'Vendido; o comprador não tem o CRLV-e no nome ainda.'])
            ->assertRedirect();

        $this->actingAsApiUser($buyer);
        $this->postJson("/api/v1/vehicles/{$vehicle->id}/link", $this->document())->assertOk();

        $this->assertSame([$buyer->id], $vehicle->owners()->wherePivot('is_current_owner', true)->pluck('users.id')->all());
    }

    public function test_reason_is_required(): void
    {
        Notification::fake();
        [$owner, $vehicle] = $this->ownerWithVehicle();

        $this->actingAs($this->adminUser())
            ->post(route('admin.vehicles.end-ownership', [$vehicle, $owner]), ['ownership_ended_reason' => ''])
            ->assertSessionHasErrors('ownership_ended_reason');

        $this->assertSame(1, $owner->currentVehicles()->count());
        Notification::assertNothingSent();
    }

    public function test_someone_who_is_not_the_current_owner_is_reported_and_nobody_is_notified(): void
    {
        Notification::fake();
        [, $vehicle] = $this->ownerWithVehicle();
        $stranger = User::factory()->asUser()->create();

        $this->actingAs($this->adminUser())
            ->post(route('admin.vehicles.end-ownership', [$vehicle, $stranger]), ['ownership_ended_reason' => 'Teste.'])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(1, $vehicle->owners()->wherePivot('is_current_owner', true)->count());
        Notification::assertNothingSent();
    }

    public function test_only_admins_can_end_an_ownership(): void
    {
        Notification::fake();
        [$owner, $vehicle] = $this->ownerWithVehicle();
        $route = route('admin.vehicles.end-ownership', [$vehicle, $owner]);

        $this->post($route, ['ownership_ended_reason' => 'Teste.'])->assertRedirect(url('/login/admin'));

        foreach ([User::factory()->asUser()->create(), $owner] as $notAdmin) {
            // O 403 do middleware admin vira redirecionamento com aviso (App\Support\FriendlyHttpErrors).
            $this->actingAs($notAdmin)
                ->post($route, ['ownership_ended_reason' => 'Teste.'])
                ->assertRedirect()
                ->assertSessionMissing('success');
        }

        $this->assertSame(1, $owner->currentVehicles()->count());
        Notification::assertNothingSent();
    }

    public function test_the_email_tells_the_former_owner_how_to_contest(): void
    {
        [$owner, $vehicle] = $this->ownerWithVehicle();

        $mail = (new VehicleOwnershipEndedByTeamNotification($vehicle))->toMail($owner);

        $this->assertStringContainsString('ABC1D23', $mail->subject.implode(' ', $mail->introLines));
        $this->assertStringContainsString('responda este e-mail', implode(' ', $mail->introLines));
        $this->assertSame('RevisaLog', $mail->salutation);
    }

    /**
     * @return array{User, Vehicle}
     */
    private function ownerWithVehicle(): array
    {
        $owner = User::factory()->asUser()->create(['name' => 'Rillian Teste']);
        $vehicle = Vehicle::factory()->create([
            'brand' => 'VW',
            'model' => 'Polo',
            'license_plate' => 'ABC1D23',
            'renavam' => self::RENAVAM,
        ]);
        $this->attachVehicleToUser($owner, $vehicle);

        return [$owner->refresh(), $vehicle];
    }

    /**
     * @return array{license_plate: string, renavam: string}
     */
    private function document(): array
    {
        return ['license_plate' => 'ABC1D23', 'renavam' => self::RENAVAM];
    }
}
