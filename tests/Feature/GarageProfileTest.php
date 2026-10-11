<?php

namespace Tests\Feature;

use App\Enums\Portal;
use App\Models\Garage;
use App\Models\User;
use App\Support\Vehicle\VehicleEntryFlow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Minha loja" (/garagem/minha-loja): onde o lojista preenche CNPJ, contato e endereço da garagem.
 * Antes dela o CNPJ só podia ser gravado no cadastro público, que recusa conta de garagem, e todo
 * CRLV-e da loja entrava como consignação.
 */
class GarageProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_dealer_sees_the_form_with_a_warning_while_the_cnpj_is_missing(): void
    {
        $dealer = User::factory()->asGarage()->create();

        $this->actingAs($dealer)
            ->get(route('garage.profile.show'))
            ->assertOk()
            ->assertSee('Minha loja')
            ->assertSee('data-garage-document-missing', escape: false);
    }

    public function test_dealer_saves_the_store_details(): void
    {
        $dealer = User::factory()->asGarage()->create();

        $this->actingAs($dealer)
            ->put(route('garage.profile.update'), [
                'name' => 'Loja do Dev',
                'document' => '12345678000190',
                'phone' => '11999998888',
                'email' => 'contato@loja.test',
                'cep' => '01310100',
                'street' => 'Avenida Paulista',
                'number' => '1000',
                'complement' => 'Sala 2',
                'neighborhood' => 'Bela Vista',
                'city' => 'São Paulo',
                'state' => 'SP',
            ])
            ->assertRedirect(route('garage.profile.show'))
            ->assertSessionHas('success');

        $garage = $dealer->fresh()->garage;

        $this->assertSame('Loja do Dev', $garage->name);
        $this->assertSame('12345678000190', $garage->document);
        $this->assertSame('São Paulo', $garage->city);
        $this->assertSame('SP', $garage->state);
        $this->assertTrue($garage->hasAddress());
    }

    public function test_masked_cnpj_cep_and_phone_are_stored_as_digits(): void
    {
        $dealer = User::factory()->asGarage()->create();

        $this->actingAs($dealer)
            ->put(route('garage.profile.update'), [
                'name' => 'Loja do Dev',
                'document' => '12.345.678/0001-90',
                'phone' => '(11) 99999-8888',
                'cep' => '01310-100',
                'state' => 'sp',
            ])
            ->assertSessionHasNoErrors();

        $garage = $dealer->fresh()->garage;

        $this->assertSame('12345678000190', $garage->document);
        $this->assertSame('11999998888', $garage->phone);
        $this->assertSame('01310100', $garage->cep);
        $this->assertSame('SP', $garage->state);
    }

    public function test_cnpj_with_the_wrong_length_is_refused(): void
    {
        $dealer = User::factory()->asGarage()->create();

        $this->actingAs($dealer)
            ->put(route('garage.profile.update'), [
                'name' => 'Loja do Dev',
                'document' => '123456',
            ])
            ->assertSessionHasErrors('document');

        $this->assertNull($dealer->fresh()->garage->document);
    }

    public function test_the_saved_cnpj_becomes_the_account_document_and_unblocks_own_stock(): void
    {
        $dealer = User::factory()->asGarage()->create();
        $flow = VehicleEntryFlow::for(Portal::Dealer);

        $this->assertSame(
            VehicleEntryFlow::OWNERSHIP_MISSING_ACCOUNT_DOCUMENT,
            $flow->ownershipFor($dealer, null),
        );

        $this->actingAs($dealer)->put(route('garage.profile.update'), [
            'name' => 'Loja do Dev',
            'document' => '12345678000190',
        ])->assertSessionHasNoErrors();

        $dealer = $dealer->fresh();

        $this->assertSame('12345678000190', $dealer->normalizedDocument());
        $this->assertSame(
            VehicleEntryFlow::OWNERSHIP_OWNER,
            $flow->ownershipFor($dealer, ['owner_document' => '12.345.678/0001-90']),
        );
    }

    public function test_a_dealer_without_a_garage_row_gets_one_on_the_first_visit(): void
    {
        $dealer = User::factory()->asGarage()->create(['document' => '12345678000190']);
        Garage::query()->where('user_id', $dealer->id)->delete();

        $this->actingAs($dealer)->get(route('garage.profile.show'))->assertOk();

        $garage = $dealer->fresh()->garage;

        $this->assertNotNull($garage);
        // O CNPJ que estava na conta é herdado pela loja, como na migration.
        $this->assertSame('12345678000190', $garage->document);
    }

    public function test_owner_account_cannot_reach_the_dealer_store_page(): void
    {
        $owner = User::factory()->asUser()->create();

        $this->actingAs($owner)
            ->get(route('garage.profile.show'))
            ->assertRedirect(route('user.dashboard'));
    }
}
