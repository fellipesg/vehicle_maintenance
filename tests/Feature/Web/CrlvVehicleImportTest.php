<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Database\Seeders\VehicleCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CrlvVehicleImportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(VehicleCatalogSeeder::class);
        $this->user = User::factory()->asUser()->create();
    }

    /**
     * @return array<string, array{array{
     *     fixture: string,
     *     license_plate: string,
     *     renavam: string,
     *     crv_number: string,
     *     brand: string,
     *     model: string,
     *     year: int,
     *     color: string,
     *     chassis: string,
     *     engine: string,
     *     motorization: ?string
     * }}>
     */
    public static function crlvFixtureProvider(): array
    {
        return [
            'parana_mercedes_c180' => [[
                'fixture' => 'divesa_c180_pr.pdf',
                'license_plate' => 'QOS6H54',
                'renavam' => '01159110473',
                'crv_number' => '244043259050',
                'brand' => 'Mercedes-Benz',
                'model' => 'C 180',
                'year' => 2018,
                'color' => 'PRETA',
                'chassis' => '9BMWF4AW9JM008903',
                'engine' => '27491031429700',
                'motorization' => '156CV',
            ]],
            'mato_grosso_do_sul_honda_civic' => [[
                'fixture' => 'honda_civic_ms.pdf',
                'license_plate' => 'PHF9J95',
                'renavam' => '01050047521',
                'crv_number' => '264600365712',
                'brand' => 'Honda',
                'model' => 'Civic',
                'year' => 2016,
                'color' => 'PRETA',
                'chassis' => '93HFB9640GZ202125',
                'engine' => 'R20Z5-6401964',
                'motorization' => '155CV 2L',
            ]],
        ];
    }

    #[DataProvider('crlvFixtureProvider')]
    public function test_user_can_import_crlv_and_see_preview(array $expected): void
    {
        $file = new UploadedFile(
            base_path('tests/fixtures/crlv/'.$expected['fixture']),
            'CRLV-e.pdf',
            'application/pdf',
            null,
            true
        );

        $this->actingAs($this->user)
            ->post('/usuario/veiculos/importar-crlv', ['crlv' => $file])
            ->assertRedirect(route('user.vehicles.import.preview'))
            ->assertSessionHas('crlv_preview.license_plate', $expected['license_plate'])
            ->assertSessionHas('crlv_preview.renavam', $expected['renavam'])
            ->assertSessionHas('crlv_preview.brand', $expected['brand'])
            ->assertSessionHas('crlv_preview.model', $expected['model']);

        $this->actingAs($this->user)
            ->get('/usuario/veiculos/importar-crlv/preview')
            ->assertOk()
            ->assertSee($expected['license_plate'])
            ->assertSee($expected['brand'])
            ->assertSee('Confirmar e salvar veículo');
    }

    #[DataProvider('crlvFixtureProvider')]
    public function test_user_can_confirm_imported_vehicle(array $expected): void
    {
        $file = new UploadedFile(
            base_path('tests/fixtures/crlv/'.$expected['fixture']),
            'CRLV-e.pdf',
            'application/pdf',
            null,
            true
        );

        $this->actingAs($this->user)->post('/usuario/veiculos/importar-crlv', ['crlv' => $file]);

        $token = session('crlv_verification.token');

        $this->actingAs($this->user)
            ->post('/usuario/veiculos', [
                'license_plate' => $expected['license_plate'],
                'renavam' => $expected['renavam'],
                'crv_number' => $expected['crv_number'],
                'brand' => $expected['brand'],
                'model' => $expected['model'],
                'year' => $expected['year'],
                'color' => $expected['color'],
                'chassis' => $expected['chassis'],
                'engine' => $expected['engine'],
                'motorization' => $expected['motorization'],
                'crlv_verification_token' => $token,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('vehicles', [
            'license_plate' => $expected['license_plate'],
            'renavam' => $expected['renavam'],
            'brand' => $expected['brand'],
            'model' => $expected['model'],
        ]);
    }

    public function test_rejects_non_crlv_pdf(): void
    {
        $file = UploadedFile::fake()->create('documento.pdf', 100, 'application/pdf');

        $this->actingAs($this->user)
            ->from(route('user.vehicles.create'))
            ->post('/usuario/veiculos/importar-crlv', ['crlv' => $file])
            ->assertRedirect(route('user.vehicles.create'))
            ->assertSessionHasErrors('crlv');
    }

    public function test_preview_redirects_without_session(): void
    {
        $this->actingAs($this->user)
            ->get('/usuario/veiculos/importar-crlv/preview')
            ->assertRedirect(route('user.vehicles.create'));
    }

    public function test_user_can_import_crlv_on_edit_to_fill_form(): void
    {
        $vehicle = \App\Models\Vehicle::factory()->create([
            'license_plate' => 'QOS6H54',
            'renavam' => '01159110473',
            'crv_number' => '244043259050',
            'brand' => 'Honda',
            'model' => 'Civic',
            'year' => 2016,
        ]);
        $this->user->vehicles()->attach($vehicle->id, [
            'is_current_owner' => true,
            'tenant_id' => $this->user->tenant_id,
        ]);

        $file = new UploadedFile(
            base_path('tests/fixtures/crlv/divesa_c180_pr.pdf'),
            'CRLV-e.pdf',
            'application/pdf',
            null,
            true
        );

        $this->actingAs($this->user)
            ->post(route('user.vehicles.import-crlv.edit', $vehicle), ['crlv' => $file])
            ->assertRedirect(route('user.vehicles.show', $vehicle))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('vehicles', [
            'id' => $vehicle->id,
            'crv_number' => '244043259050',
            'brand' => 'Mercedes-Benz',
            'model' => 'C 180',
            'motorization' => '156CV',
        ]);
    }

    public function test_edit_import_rejects_crlv_with_different_renavam(): void
    {
        $vehicle = \App\Models\Vehicle::factory()->create([
            'license_plate' => 'AAA1A11',
            'renavam' => '99999999999',
        ]);
        $this->user->vehicles()->attach($vehicle->id, [
            'is_current_owner' => true,
            'tenant_id' => $this->user->tenant_id,
        ]);

        $file = new UploadedFile(
            base_path('tests/fixtures/crlv/divesa_c180_pr.pdf'),
            'CRLV-e.pdf',
            'application/pdf',
            null,
            true
        );

        $this->actingAs($this->user)
            ->from(route('user.vehicles.edit', $vehicle))
            ->post(route('user.vehicles.import-crlv.edit', $vehicle), ['crlv' => $file])
            ->assertRedirect(route('user.vehicles.edit', $vehicle))
            ->assertSessionHasErrors('crlv');
    }
}
