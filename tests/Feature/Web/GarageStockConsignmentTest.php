<?php

namespace Tests\Feature\Web;

use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleConsignment;
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

    public function test_pending_consignment_appears_in_stock_with_status_and_detail_link(): void
    {
        $vehicle = $this->consignedVehicle('pending', ['brand' => 'Fiat', 'model' => 'Toro']);

        $this->actingAs($this->garage)
            ->get(route('garage.vehicles.index'))
            ->assertOk()
            ->assertSee('Fiat Toro')
            ->assertSee('Consignação')
            ->assertSee('Histórico aguardando liberação')
            ->assertSee('data-consignment-status="pending"', false)
            ->assertSee('O histórico abre aqui quando o proprietário liberar ou a equipe aprovar a procuração.')
            // A consignação declarada abre o veículo: é nele que a loja registra as manutenções.
            ->assertSee('href="'.route('garage.vehicles.show', $vehicle).'"', false);
    }

    public function test_rejected_consignment_shows_review_notes_and_resend_action(): void
    {
        $this->consignedVehicle('rejected', reviewNotes: 'Procuração sem firma reconhecida.');

        $this->actingAs($this->garage)
            ->get(route('garage.vehicles.index'))
            ->assertOk()
            ->assertSee('Pedido de histórico recusado')
            ->assertSee('data-consignment-status="rejected"', false)
            ->assertSee('Procuração sem firma reconhecida.')
            ->assertSee('Pedir liberação ao proprietário');

        // A nova tentativa é pedir ao proprietário, na ficha do veículo, não reenviar documento.
        $html = $this->actingAs($this->garage)->get(route('garage.vehicles.index'))->getContent();
        $this->assertMatchesRegularExpression('#<a[^>]*data-consignment-request#', $html);
    }

    public function test_approved_consignment_opens_detail_with_register_revision_action(): void
    {
        $vehicle = $this->consignedVehicle('approved');

        $this->actingAs($this->garage)
            ->get(route('garage.vehicles.index'))
            ->assertOk()
            ->assertSee('Histórico liberado')
            ->assertSee('href="'.route('garage.vehicles.show', $vehicle).'"', false)
            ->assertDontSee('Pedir liberação ao proprietário');

        $this->actingAs($this->garage)
            ->get(route('garage.vehicles.show', $vehicle))
            ->assertOk()
            ->assertSee('Histórico liberado')
            ->assertSee('Veículo em consignação')
            ->assertSee('href="'.e(route('garage.maintenances.create', ['vehicle_id' => $vehicle->id])).'"', false)
            ->assertSee('Registrar manutenção');
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
            ->assertSee('Histórico aguardando liberação')
            ->assertSee('1 em consignação')
            ->assertSee('data-attention="consignment_pending"', false)
            ->assertSee('href="'.route('garage.vehicles.show', $consigned).'"', false)
            ->getContent();

        $this->assertMatchesRegularExpression('/data-stat="vehicles".*?data-slot="stat-value"[^>]*>\s*2\s*</s', $html);
    }

    public function test_revision_form_lists_owned_stock_and_consignments(): void
    {
        $owned = $this->ownedVehicle(['license_plate' => 'OWN1A23']);
        $consigned = $this->consignedVehicle('approved', ['license_plate' => 'CSG2B34']);

        $this->actingAs($this->garage)
            ->get(route('garage.maintenances.create'))
            ->assertOk()
            ->assertSee('value="'.$owned->id.'"', false)
            ->assertSee('OWN1A23')
            ->assertSee('value="'.$consigned->id.'"', false)
            ->assertSee('CSG2B34')
            ->assertDontSee('data-consignment-note', false);
    }

    public function test_revision_form_hides_a_consignment_the_owner_disputed(): void
    {
        $this->ownedVehicle(['license_plate' => 'OWN1A23']);
        $disputed = $this->consignedVehicle('approved', ['license_plate' => 'CSG2B34']);
        $disputed->activeConsignment->update(['owner_disputed_at' => now()]);

        $this->actingAs($this->garage)
            ->get(route('garage.maintenances.create'))
            ->assertOk()
            ->assertDontSee('CSG2B34')
            ->assertSee('data-consignment-note', false)
            ->assertSee('o proprietário contestou a consignação');
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
            ->post(route('garage.vehicles.consignment.store'), $this->declaration([
                'power_of_attorney' => UploadedFile::fake()->create('procuracao.pdf', 120, 'application/pdf'),
            ]))
            ->assertRedirect(route('garage.vehicles.index'))
            ->assertSessionHas('success', 'Veículo em consignação adicionado. Você já pode registrar manutenções; a procuração foi enviada para análise e o histórico anterior abre depois dela.');

        $this->assertDatabaseHas('vehicle_consignments', [
            'garage_user_id' => $this->garage->id,
            'vehicle_id' => $vehicle->id,
            'status' => 'active',
            'history_access_status' => 'pending',
        ]);

        $this->actingAs($this->garage)
            ->get(route('garage.vehicles.index'))
            ->assertOk()
            ->assertSee('Honda Civic')
            ->assertSee('Histórico aguardando liberação');
    }

    public function test_power_of_attorney_form_explains_the_review_steps(): void
    {
        $this->actingAs($this->garage)
            ->withSession(['consignment_pending' => ['vehicle_id' => 1]])
            ->get(route('garage.vehicles.consignment'))
            ->assertOk()
            ->assertSee('data-consignment-steps', false)
            ->assertSeeInOrder(['Agora:', 'Aviso ao proprietário:', 'Histórico anterior:']);
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
            ->post(route('garage.vehicles.consignment.store'), $this->declaration([
                'power_of_attorney' => UploadedFile::fake()->create('procuracao.pdf', 120, 'application/pdf'),
            ]))
            ->assertRedirect(route('garage.vehicles.index'))
            ->assertSessionHas('success', 'Veículo em consignação adicionado. Você já pode registrar manutenções; a procuração foi enviada para análise e o histórico anterior abre depois dela.');

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
            ->assertSee('Histórico aguardando liberação');
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
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function declaration(array $overrides = []): array
    {
        return array_merge([
            'consignment_owner_name' => 'Rodrigo Sanches Devigo',
            'consignment_owner_email' => 'rodrigo@example.com',
            'consignment_declaration' => '1',
        ], $overrides);
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

        VehicleConsignment::factory()->create([
            'vehicle_id' => $vehicle->id,
            'garage_user_id' => $this->garage->id,
            'tenant_id' => $this->garage->tenant_id,
            'history_access_status' => $status,
            'power_of_attorney_path' => 'procuracoes/teste.pdf',
            'review_notes' => $reviewNotes,
        ]);

        return $vehicle;
    }
}
