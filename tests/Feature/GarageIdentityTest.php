<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleConsignment;
use App\Notifications\VehicleConsignmentStartedNotification;
use App\Services\TenantService;
use App\Services\Vehicle\VehicleConsignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Quem fala com o proprietário de um veículo em consignação é a loja, não a pessoa dona da conta:
 * nome, endereço, telefone e e-mail saem de "Minha loja". Esses dados nascem como cópia do contato
 * da conta (é comum a loja não ter contato próprio) e podem ser trocados depois.
 */
class GarageIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_dealer_account_copies_the_contact_into_the_store(): void
    {
        $dealer = User::factory()->asGarage()->make([
            'name' => 'Rillian',
            'email' => 'rillian@example.com',
            'phone' => '11988887777',
        ]);
        $dealer->tenant_id = null;
        $dealer->save();

        (new TenantService)->createForUser($dealer);
        $garage = $dealer->fresh()->garage;

        $this->assertSame('Rillian', $garage->name);
        $this->assertSame('rillian@example.com', $garage->email);
        $this->assertSame('11988887777', $garage->phone);
    }

    public function test_the_store_values_win_once_they_differ(): void
    {
        $dealer = User::factory()->asGarage()->create(['name' => 'Rillian', 'email' => 'rillian@example.com', 'phone' => '11988887777']);
        $dealer->garage->update([
            'name' => 'Garagem Central',
            'email' => 'contato@garagemcentral.test',
            'phone' => '1133334444',
            'street' => 'Avenida Paulista',
            'number' => '1000',
            'neighborhood' => 'Bela Vista',
            'city' => 'São Paulo',
            'state' => 'SP',
        ]);

        $identity = $dealer->fresh()->storeIdentity();

        $this->assertSame('Garagem Central', $identity['name']);
        $this->assertSame('contato@garagemcentral.test', $identity['email']);
        $this->assertSame('1133334444', $identity['phone']);
        $this->assertSame('Avenida Paulista, 1000 — Bela Vista — São Paulo/SP', $identity['address']);
    }

    public function test_an_empty_store_contact_falls_back_to_the_account(): void
    {
        $dealer = User::factory()->asGarage()->create(['name' => 'Rillian', 'email' => 'rillian@example.com', 'phone' => '11988887777']);
        $dealer->garage->update(['email' => null, 'phone' => null]);

        $identity = $dealer->fresh()->storeIdentity();

        // A loja sem contato próprio usa o da pessoa, em vez de deixar o proprietário sem a quem falar.
        $this->assertSame('rillian@example.com', $identity['email']);
        $this->assertSame('11988887777', $identity['phone']);
        $this->assertNull($identity['address']);
    }

    public function test_the_owner_email_carries_the_store_name_and_contact(): void
    {
        $consignment = $this->consign();
        $garageUser = $consignment->garageUser;

        $mail = (new VehicleConsignmentStartedNotification($consignment))
            ->toMail($consignment->ownerUser)
            ->render()
            ->toHtml();

        $this->assertStringContainsString('Garagem Central', $mail);
        $this->assertStringContainsString('contato@garagemcentral.test', $mail);
        $this->assertStringContainsString('1133334444', $mail);
        $this->assertStringContainsString('Avenida Paulista, 1000', $mail);

        // O nome da pessoa dona da conta não aparece para o proprietário.
        $this->assertStringNotContainsString($garageUser->name, $mail);
    }

    public function test_the_owner_page_shows_the_store_not_the_person(): void
    {
        $consignment = $this->consign();

        $this->get(route('consignments.owner.show', $consignment->owner_action_token))
            ->assertOk()
            ->assertSee('Garagem Central')
            ->assertSee('contato@garagemcentral.test')
            ->assertSee('Avenida Paulista, 1000 — Bela Vista — São Paulo/SP')
            ->assertDontSee($consignment->garageUser->name);
    }

    public function test_minha_conta_points_the_dealer_to_minha_loja(): void
    {
        $dealer = User::factory()->asGarage()->create();

        $this->actingAs($dealer)
            ->get(route('account.edit'))
            ->assertOk()
            ->assertSee('data-business-profile-hint', escape: false)
            ->assertSee('Minha loja')
            ->assertSee(route('garage.profile.show'));
    }

    public function test_minha_conta_points_the_workshop_to_minha_oficina(): void
    {
        $workshopUser = User::factory()->asWorkshop()->create();

        $this->actingAs($workshopUser)
            ->get(route('account.edit'))
            ->assertOk()
            ->assertSee('Minha oficina')
            ->assertSee(route('workshop.profile.show'));
    }

    public function test_owner_account_gets_no_such_hint(): void
    {
        $owner = User::factory()->asUser()->create();

        $this->actingAs($owner)
            ->get(route('account.edit'))
            ->assertOk()
            ->assertDontSee('data-business-profile-hint', escape: false);
    }

    private function consign(): VehicleConsignment
    {
        $garageUser = User::factory()->asGarage()->create(['name' => 'Pessoa Dona Da Conta']);
        $garageUser->garage->update([
            'name' => 'Garagem Central',
            'email' => 'contato@garagemcentral.test',
            'phone' => '1133334444',
            'street' => 'Avenida Paulista',
            'number' => '1000',
            'neighborhood' => 'Bela Vista',
            'city' => 'São Paulo',
            'state' => 'SP',
        ]);

        $owner = User::factory()->asUser()->create(['email' => 'dono@example.com']);
        $vehicle = Vehicle::factory()->create([
            'current_kilometers' => 50_000,
            'odometer_at_registration' => 50_000,
        ]);

        $owner->vehicles()->attach($vehicle->id, [
            'is_current_owner' => true,
            'purchase_date' => now(),
            'tenant_id' => $owner->tenant_id,
        ]);
        $garageUser->fresh()->vehicles()->attach($vehicle->id, [
            'is_current_owner' => false,
            'purchase_date' => now(),
            'tenant_id' => $garageUser->tenant_id,
            'ownership_type' => 'consignment',
        ]);

        return app(VehicleConsignmentService::class)->start($garageUser->fresh(), $vehicle, [
            'owner_name' => $owner->name,
        ]);
    }
}
