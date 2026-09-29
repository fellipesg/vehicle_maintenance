<?php

namespace Tests\Feature\Web;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleAccessGrant;
use App\Support\AppStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Consignação no portal do lojista: o veículo entra no estoque (dashboard, lista e select de revisão)
 * com o status da procuração, e a ficha só oferece "Registrar manutenção" a quem pode registrar.
 */
class GarageStockConsignmentTest extends TestCase
{
    use RefreshDatabase;

    private User $garage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->garage = User::factory()->asGarage()->create();
    }

    public function test_pending_consignment_appears_in_stock_with_status_and_without_detail_link(): void
    {
        $vehicle = $this->consignedVehicle('pending', ['brand' => 'Fiat', 'model' => 'Toro']);

        $this->actingAs($this->garage)
            ->get(route('garage.vehicles.index'))
            ->assertOk()
            ->assertSee('Fiat Toro')
            ->assertSee('Consignação')
            ->assertSee('Procuração em análise')
            ->assertSee('data-consignment-status="pending"', false)
            ->assertSee('O histórico do veículo abre aqui quando a análise for concluída.')
            ->assertDontSee('href="'.route('garage.vehicles.show', $vehicle).'"', false);
    }

    public function test_rejected_consignment_shows_review_notes_and_resend_action(): void
    {
        $this->consignedVehicle('rejected', reviewNotes: 'Procuração sem firma reconhecida.');

        $this->actingAs($this->garage)
            ->get(route('garage.vehicles.index'))
            ->assertOk()
            ->assertSee('Procuração recusada')
            ->assertSee('data-consignment-status="rejected"', false)
            ->assertSee('Procuração sem firma reconhecida.')
            ->assertSee('Reenviar procuração')
            ->assertSee('href="'.route('garage.vehicles.create').'"', false);

        // O link vai direto ao assistente "Adicionar ao estoque" (/vincular só redireciona para ele).
        $html = $this->actingAs($this->garage)->get(route('garage.vehicles.index'))->getContent();
        $this->assertMatchesRegularExpression('#<a[^>]*href="'.preg_quote(route('garage.vehicles.create'), '#').'"[^>]*data-consignment-resend#', $html);
        $this->assertStringNotContainsString('href="'.route('garage.vehicles.claim').'"', $html);
    }

    public function test_approved_consignment_opens_detail_without_register_revision_action(): void
    {
        $vehicle = $this->consignedVehicle('approved');

        $this->actingAs($this->garage)
            ->get(route('garage.vehicles.index'))
            ->assertOk()
            ->assertSee('Procuração aprovada')
            ->assertSee('href="'.route('garage.vehicles.show', $vehicle).'"', false)
            ->assertDontSee('Reenviar procuração');

        $this->actingAs($this->garage)
            ->get(route('garage.vehicles.show', $vehicle))
            ->assertOk()
            ->assertSee('Procuração aprovada')
            ->assertSee('Veículo em consignação: só o proprietário registra manutenções.')
            ->assertDontSee(route('garage.maintenances.create', ['vehicle_id' => $vehicle->id]), false)
            ->assertDontSee('Registrar manutenção');
    }

    public function test_owned_stock_vehicle_detail_keeps_register_revision_action(): void
    {
        $vehicle = $this->ownedVehicle();

        $this->actingAs($this->garage)
            ->get(route('garage.vehicles.show', $vehicle))
            ->assertOk()
            ->assertSee('href="'.e(route('garage.maintenances.create', ['vehicle_id' => $vehicle->id])).'"', false)
            ->assertSee('Registrar manutenção')
            ->assertDontSee('data-add-maintenance-denied', false)
            ->assertDontSee('data-consignment-status', false);
    }

    public function test_dashboard_lists_consignment_with_status_and_counts_it_in_stock(): void
    {
        $this->ownedVehicle(['brand' => 'Honda', 'model' => 'Civic']);
        $consigned = $this->consignedVehicle('pending', ['brand' => 'Fiat', 'model' => 'Toro']);

        $html = $this->actingAs($this->garage)
            ->get(route('garage.dashboard'))
            ->assertOk()
            ->assertSee('Honda Civic')
            ->assertSee('Fiat Toro')
            ->assertSee('Procuração em análise')
            ->assertSee('1 em consignação')
            ->assertSee('data-attention="consignment_pending"', false)
            ->assertDontSee('href="'.route('garage.vehicles.show', $consigned).'"', false)
            ->getContent();

        $this->assertMatchesRegularExpression('/data-stat="vehicles".*?data-slot="stat-value"[^>]*>\s*2\s*</s', $html);
    }

    public function test_revision_form_lists_owned_stock_and_explains_why_consignment_is_missing(): void
    {
        $owned = $this->ownedVehicle(['license_plate' => 'OWN1A23']);
        $this->consignedVehicle('approved', ['license_plate' => 'CSG2B34']);

        $this->actingAs($this->garage)
            ->get(route('garage.maintenances.create'))
            ->assertOk()
            ->assertSee('value="'.$owned->id.'"', false)
            ->assertSee('OWN1A23')
            ->assertDontSee('CSG2B34')
            ->assertSee('data-consignment-note', false)
            ->assertSee('O veículo em consignação não aparece na lista: só o proprietário registra manutenções nele.');
    }

    public function test_revision_form_without_consignment_has_no_consignment_note(): void
    {
        $this->ownedVehicle();

        $this->actingAs($this->garage)
            ->get(route('garage.maintenances.create'))
            ->assertOk()
            ->assertDontSee('data-consignment-note', false);
    }

    public function test_consignment_vehicle_of_another_tenant_does_not_enter_the_stock(): void
    {
        $otherGarage = User::factory()->asGarage()->create();
        $vehicle = Vehicle::factory()->create(['brand' => 'Jeep', 'model' => 'Renegade']);
        $otherGarage->vehicles()->attach($vehicle->id, [
            'is_current_owner' => false,
            'purchase_date' => now(),
            'tenant_id' => $otherGarage->tenant_id,
            'ownership_type' => 'consignment',
        ]);

        $this->actingAs($this->garage)
            ->get(route('garage.vehicles.index'))
            ->assertOk()
            ->assertDontSee('Jeep Renegade');
    }

    public function test_sold_vehicle_without_consignment_stays_out_of_the_stock(): void
    {
        $vehicle = Vehicle::factory()->create(['brand' => 'Chevrolet', 'model' => 'Onix']);
        $this->garage->vehicles()->attach($vehicle->id, [
            'is_current_owner' => false,
            'purchase_date' => now()->subYear(),
            'tenant_id' => $this->garage->tenant_id,
            'ownership_type' => 'owner',
        ]);

        $this->actingAs($this->garage)
            ->get(route('garage.vehicles.index'))
            ->assertOk()
            ->assertDontSee('Chevrolet Onix');
    }

    public function test_sending_power_of_attorney_lands_on_stock_with_the_vehicle_under_review(): void
    {
        Storage::fake(AppStorage::diskName());

        $vehicle = Vehicle::factory()->create([
            'brand' => 'Honda',
            'model' => 'Civic',
            'license_plate' => 'PHF9J95',
            'renavam' => '01050047521',
            'crv_number' => '264600365712',
            'chassis' => null,
        ]);

        $this->actingAs($this->garage)
            ->withSession([
                'consignment_pending' => [
                    'vehicle_id' => $vehicle->id,
                    'crlv_verification' => ['parsed' => $this->crlvPreview()],
                ],
            ])
            ->post(route('garage.vehicles.consignment.store'), [
                'power_of_attorney' => UploadedFile::fake()->create('procuracao.pdf', 120, 'application/pdf'),
            ])
            ->assertRedirect(route('garage.vehicles.index'))
            ->assertSessionHas('success', 'Procuração enviada para análise. O veículo aparece no estoque como "Procuração em análise" até a aprovação.');

        $this->assertDatabaseHas('vehicle_access_grants', [
            'user_id' => $this->garage->id,
            'vehicle_id' => $vehicle->id,
            'grant_type' => 'consignment',
            'status' => 'pending',
        ]);

        $this->actingAs($this->garage)
            ->get(route('garage.vehicles.index'))
            ->assertOk()
            ->assertSee('Honda Civic')
            ->assertSee('Procuração em análise');
    }

    public function test_power_of_attorney_form_explains_the_review_steps(): void
    {
        $this->actingAs($this->garage)
            ->withSession(['consignment_pending' => ['vehicle_id' => 1]])
            ->get(route('garage.vehicles.consignment'))
            ->assertOk()
            ->assertSee('data-consignment-steps', false)
            ->assertSeeInOrder(['Envio:', 'Análise:', 'Histórico liberado:'])
            ->assertSee('o veículo aparece no estoque como "Procuração em análise"', false);
    }

    public function test_new_vehicle_registered_on_consignment_lands_on_stock_under_review(): void
    {
        Storage::fake(AppStorage::diskName());

        $this->actingAs($this->garage)
            ->withSession([
                'consignment_pending' => [
                    'vehicle_data' => [
                        'license_plate' => 'PHF9J95',
                        'renavam' => '01050047521',
                        'crv_number' => '264600365712',
                        'brand' => 'Honda',
                        'model' => 'Civic',
                        'year' => 2016,
                        'current_kilometers' => 85_000,
                    ],
                    'crlv_verification' => ['parsed' => $this->crlvPreview()],
                ],
            ])
            ->post(route('garage.vehicles.consignment.store'), [
                'power_of_attorney' => UploadedFile::fake()->create('procuracao.pdf', 120, 'application/pdf'),
            ])
            ->assertRedirect(route('garage.vehicles.index'))
            ->assertSessionHas('success', 'Veículo adicionado em consignação. Ele aparece no estoque como "Procuração em análise" até a aprovação.');

        $vehicle = Vehicle::where('license_plate', 'PHF9J95')->firstOrFail();

        $this->assertDatabaseHas('user_vehicles', [
            'user_id' => $this->garage->id,
            'vehicle_id' => $vehicle->id,
            'ownership_type' => 'consignment',
            'is_current_owner' => false,
        ]);

        $this->actingAs($this->garage)
            ->get(route('garage.vehicles.index'))
            ->assertOk()
            ->assertSee('Honda Civic')
            ->assertSee('Procuração em análise');
    }

    /**
     * @return array<string, mixed>
     */
    private function crlvPreview(): array
    {
        return [
            'license_plate' => 'PHF9J95',
            'renavam' => '01050047521',
            'brand' => 'Honda',
            'model' => 'Civic',
            'year' => 2016,
            'crv_number' => '264600365712',
            'exercise_year' => (int) date('Y'),
            'owner_name' => 'Maria Proprietária',
            'owner_document' => '11144477735',
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function ownedVehicle(array $attributes = []): Vehicle
    {
        $vehicle = Vehicle::factory()->create($attributes);
        $this->garage->vehicles()->attach($vehicle->id, [
            'is_current_owner' => true,
            'purchase_date' => now(),
            'tenant_id' => $this->garage->tenant_id,
        ]);

        return $vehicle;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function consignedVehicle(string $status, array $attributes = [], ?string $reviewNotes = null): Vehicle
    {
        $owner = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create($attributes);
        $owner->vehicles()->attach($vehicle->id, [
            'is_current_owner' => true,
            'purchase_date' => now()->subYears(2),
            'tenant_id' => $owner->tenant_id,
        ]);
        $this->garage->vehicles()->attach($vehicle->id, [
            'is_current_owner' => false,
            'purchase_date' => now(),
            'tenant_id' => $this->garage->tenant_id,
            'ownership_type' => 'consignment',
        ]);

        VehicleAccessGrant::create([
            'user_id' => $this->garage->id,
            'vehicle_id' => $vehicle->id,
            'grant_type' => 'consignment',
            'status' => $status,
            'power_of_attorney_path' => 'procuracoes/teste.pdf',
            'review_notes' => $reviewNotes,
        ]);

        return $vehicle;
    }
}
