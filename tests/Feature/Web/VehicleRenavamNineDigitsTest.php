<?php

namespace Tests\Feature\Web;

use App\Models\User;
use App\Models\Vehicle;
use App\Services\Vehicle\VehicleOwnershipService;
use App\Support\VehiclePlateSearch;
use Database\Seeders\VehicleCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Web\Concerns\InspectsOwnerPages;
use Tests\TestCase;

/**
 * RENAVAM antigo (9 dígitos) é o de 11 com "00" na frente: o cadastro aceita de 9 a 11 dígitos e as
 * buscas e conferências tratam as duas formas como o mesmo número (Vehicle::canonicalRenavam).
 */
class VehicleRenavamNineDigitsTest extends TestCase
{
    use InspectsOwnerPages;
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(VehicleCatalogSeeder::class);
        $this->owner = User::factory()->asUser()->create();
    }

    public function test_registration_accepts_a_nine_digit_renavam_and_stores_it_as_typed(): void
    {
        $this->actingAs($this->owner)
            ->post(route('user.vehicles.store'), $this->payload(['renavam' => '123456789']))
            ->assertSessionHasNoErrors();

        $this->assertSame('123456789', Vehicle::where('license_plate', 'ABC1D23')->firstOrFail()->renavam);
    }

    public function test_registration_rejects_eight_and_twelve_digit_renavams(): void
    {
        foreach (['12345678', '123456789012'] as $renavam) {
            $this->actingAs($this->owner)
                ->post(route('user.vehicles.store'), $this->payload(['renavam' => $renavam]))
                ->assertSessionHasErrors(['renavam' => 'O RENAVAM deve ter de 9 a 11 dígitos.']);
        }

        $this->assertSame(0, Vehicle::count());
    }

    public function test_the_same_renavam_cannot_be_registered_as_nine_and_as_eleven_digits(): void
    {
        Vehicle::factory()->create(['renavam' => '00123456789', 'chassis' => '9BWZZZ377VT004251']);

        $this->actingAs($this->owner)
            ->post(route('user.vehicles.store'), $this->payload(['renavam' => '123456789']))
            ->assertSessionHasErrors(['renavam']);

        $this->assertSame(1, Vehicle::count());

        Vehicle::query()->update(['renavam' => '123456789']);

        $this->actingAs($this->owner)
            ->post(route('user.vehicles.store'), $this->payload(['renavam' => '00123456789']))
            ->assertSessionHasErrors(['renavam']);

        $this->assertSame(1, Vehicle::count());
    }

    public function test_edit_rejects_a_renavam_that_only_differs_by_leading_zeros(): void
    {
        Vehicle::factory()->create(['renavam' => '00123456789']);
        $vehicle = $this->ownedVehicle($this->owner, ['renavam' => '98765432101', 'year' => 2020]);

        $this->actingAs($this->owner)
            ->put(route('user.vehicles.update', $vehicle), [
                'license_plate' => $vehicle->license_plate,
                'renavam' => '123456789',
                'crv_number' => '123456789012',
                'brand' => $vehicle->brand,
                'model' => $vehicle->model,
                'year' => $vehicle->year,
                'chassis' => $vehicle->chassis,
            ])
            ->assertSessionHasErrors(['renavam']);
    }

    public function test_lookups_match_nine_and_eleven_digit_forms_in_both_directions(): void
    {
        $padded = Vehicle::factory()->create(['renavam' => '00123456789']);
        $short = Vehicle::factory()->create(['renavam' => '987654321']);

        $this->assertTrue($padded->is(Vehicle::findByRenavam('123456789')));
        $this->assertTrue($padded->is(Vehicle::findByRenavam('001.234.567-89')));
        $this->assertTrue($short->is(Vehicle::findByRenavam('00987654321')));
        $this->assertNull(Vehicle::findByRenavam('555555555'));
        $this->assertNull(Vehicle::findByRenavam(''));

        $this->assertTrue($padded->is(VehiclePlateSearch::findByIdentifier('123456789')?->vehicle));
        $this->assertTrue($short->is(VehiclePlateSearch::findByIdentifier('00987654321')?->vehicle));
    }

    public function test_canonical_renavam_pads_to_eleven_digits(): void
    {
        $this->assertSame('00123456789', Vehicle::canonicalRenavam('123456789'));
        $this->assertSame('00123456789', Vehicle::canonicalRenavam('001.234.567-89'));
        $this->assertSame('', Vehicle::canonicalRenavam(null));
        $this->assertTrue(Vehicle::renavamsMatch('123456789', '00123456789'));
        $this->assertFalse(Vehicle::renavamsMatch('123456789', '123456780'));
        $this->assertFalse(Vehicle::renavamsMatch('', ''));
    }

    public function test_api_link_proof_accepts_either_form_of_the_renavam(): void
    {
        $service = app(VehicleOwnershipService::class);
        $padded = Vehicle::factory()->create(['license_plate' => 'ABC1D23', 'renavam' => '00123456789']);
        $short = Vehicle::factory()->create(['license_plate' => 'DEF2G34', 'renavam' => '987654321']);

        $this->assertTrue($service->documentMatchesVehicle($padded, 'ABC1D23', '123456789'));
        $this->assertTrue($service->documentMatchesVehicle($short, 'DEF2G34', '00987654321'));
        $this->assertFalse($service->documentMatchesVehicle($short, 'DEF2G34', '00987654322'));
    }

    public function test_public_search_page_mentions_nine_to_eleven_digits(): void
    {
        $this->actingAs($this->owner)
            ->get(route('vehicle.search'))
            ->assertOk()
            ->assertSee('RENAVAM com 9 a 11 dígitos');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'license_plate' => 'ABC1D23',
            'renavam' => '12345678901',
            'crv_number' => '987654321098',
            'brand' => 'Honda',
            'model' => 'Civic',
            'year' => 2020,
            'chassis' => '9BWZZZ377VT004277',
            'current_kilometers' => 42000,
            'terms_accepted' => '1',
        ], $overrides);
    }
}
