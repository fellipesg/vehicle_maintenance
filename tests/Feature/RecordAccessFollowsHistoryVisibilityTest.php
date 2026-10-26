<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Maintenance;
use App\Models\MaintenancePhoto;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleConsignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Quem abre um registro pela rota de detalhe é quem veria aquele registro na listagem daquele
 * veículo (Maintenance::isVisibleInHistoryTo, par em memória de Vehicle::maintenancesVisibleTo).
 *
 * O acesso cruzado que faltava: o lojista com consignação ativa enxerga a ficha do veículo desde a
 * declaração, mas o histórico que o carro já tinha é do proprietário. Sem a liberação de histórico
 * completo ele não abre manutenção, foto nem nota de dono anterior — nem enumerando ids em
 * /fotos-manutencao/{id}, /notas-fiscais/{invoice} e nas rotas equivalentes da API. O que a própria
 * loja registrou continua aberto, e com a liberação o histórico anterior se abre junto.
 */
class RecordAccessFollowsHistoryVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private Vehicle $vehicle;

    private User $currentOwner;

    private User $previousOwner;

    private User $dealer;

    private User $workshopUser;

    private VehicleConsignment $consignment;

    /** Declarada pelo dono anterior: fora do histórico que o lojista vê. */
    private Maintenance $previousOwnerRecord;

    /** OS com Selo da oficina, do tempo do dono anterior. */
    private Maintenance $sealedRecord;

    /** Declarada pela própria loja durante a consignação. */
    private Maintenance $dealerRecord;

    /** Ocultada pelo proprietário (direito de oposição). */
    private Maintenance $hiddenRecord;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        // O assunto aqui é a visibilidade do histórico, não a liberação das fotos: sem isto o
        // portão do recurso (MaintenancePhotoAccess) responderia 403 por conta própria e os 403
        // esperados passariam pelo motivo errado. Convenção de .ai/rules/features.md.
        $this->enableMaintenancePhotosForEveryone();

        $this->vehicle = Vehicle::factory()->create(['license_plate' => 'CON5I1G']);

        $this->previousOwner = User::factory()->asUser()->create()->refresh();
        $this->previousOwner->vehicles()->attach($this->vehicle->id, [
            'purchase_date' => now()->subYears(3),
            'sale_date' => now()->subMonth(),
            'is_current_owner' => false,
            'tenant_id' => $this->previousOwner->tenant_id,
        ]);

        $this->currentOwner = User::factory()->asUser()->create()->refresh();
        $this->attachVehicleToUser($this->currentOwner, $this->vehicle);

        $this->workshopUser = User::factory()->asWorkshop()->create()->refresh();

        $this->dealer = User::factory()->asGarage()->create()->refresh();
        $this->dealer->vehicles()->attach($this->vehicle->id, [
            'purchase_date' => now(),
            'is_current_owner' => false,
            'tenant_id' => $this->dealer->tenant_id,
            'ownership_type' => 'consignment',
        ]);
        $this->consignment = VehicleConsignment::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'garage_user_id' => $this->dealer->id,
            'tenant_id' => $this->dealer->tenant_id,
            'owner_user_id' => $this->currentOwner->id,
            'history_access_status' => VehicleConsignment::HISTORY_NONE,
        ]);

        $this->previousOwnerRecord = Maintenance::factory()->declaredByOwner()->create([
            'vehicle_id' => $this->vehicle->id,
            'user_id' => $this->previousOwner->id,
            'tenant_id' => $this->previousOwner->tenant_id,
            'maintenance_type' => 'Troca de embreagem do dono anterior',
        ]);

        $this->sealedRecord = Maintenance::factory()->sealedByWorkshop()->create([
            'vehicle_id' => $this->vehicle->id,
            'user_id' => $this->workshopUser->id,
            'workshop_id' => $this->workshopUser->workshop->id,
            'tenant_id' => $this->previousOwner->tenant_id,
            'maintenance_type' => 'Revisão com Selo da oficina',
        ]);

        $this->dealerRecord = Maintenance::factory()->declaredByGarage()->create([
            'vehicle_id' => $this->vehicle->id,
            'user_id' => $this->dealer->id,
            'tenant_id' => $this->dealer->tenant_id,
            'maintenance_type' => 'Revisão pré-venda da loja',
        ]);

        $this->hiddenRecord = Maintenance::factory()->declaredByOwner()->create([
            'vehicle_id' => $this->vehicle->id,
            'user_id' => $this->previousOwner->id,
            'tenant_id' => $this->previousOwner->tenant_id,
            'maintenance_type' => 'Registro ocultado pelo proprietário',
        ]);
        $this->hiddenRecord->forceFill([
            'hidden_from_public_at' => now(),
            'owner_decided_by_user_id' => $this->currentOwner->id,
        ])->save();
    }

    public function test_dealer_on_consignment_is_refused_the_records_of_a_previous_owner_on_the_web(): void
    {
        foreach ([$this->previousOwnerRecord, $this->sealedRecord] as $record) {
            $photo = $this->photoFor($record);
            $invoice = $this->invoiceFor($record);

            $this->actingAs($this->dealer)
                ->get(route('maintenance-photos.show', $photo))
                ->assertForbidden();

            $this->actingAs($this->dealer)
                ->get(route('invoices.show', $invoice))
                ->assertForbidden();

            $this->assertFalse(
                Gate::forUser($this->dealer)->allows('view', $record),
                'O lojista em consignação não vê a manutenção do dono anterior.',
            );
        }

        // A ficha do veículo continua abrindo: a consignação dá o veículo, não o histórico.
        $this->assertTrue(Gate::forUser($this->dealer)->allows('view', $this->vehicle));
    }

    public function test_dealer_on_consignment_is_refused_the_records_of_a_previous_owner_on_the_api(): void
    {
        $photo = $this->photoFor($this->previousOwnerRecord);
        $invoice = $this->invoiceFor($this->previousOwnerRecord);

        $this->actingAsApiUser($this->dealer);

        $this->getJson("/api/v1/maintenances/{$this->previousOwnerRecord->id}")->assertForbidden();
        $this->getJson(route('api.maintenance-photos.show', $photo))->assertForbidden();
        $this->getJson("/api/v1/invoices/{$invoice->id}/download")->assertForbidden();
    }

    public function test_dealer_keeps_the_records_its_own_tenant_registered(): void
    {
        $photo = $this->photoFor($this->dealerRecord);
        $invoice = $this->invoiceFor($this->dealerRecord);

        $this->assertTrue(Gate::forUser($this->dealer)->allows('view', $this->dealerRecord));

        $this->actingAs($this->dealer)
            ->get(route('maintenance-photos.show', $photo))
            ->assertOk();

        $this->actingAs($this->dealer)
            ->get(route('invoices.show', $invoice))
            ->assertOk();
    }

    public function test_dealer_keeps_the_records_its_own_tenant_registered_on_the_api(): void
    {
        $photo = $this->photoFor($this->dealerRecord);
        $invoice = $this->invoiceFor($this->dealerRecord);

        $this->actingAsApiUser($this->dealer);

        $this->getJson("/api/v1/maintenances/{$this->dealerRecord->id}")->assertOk();
        $this->get(route('api.maintenance-photos.show', $photo))->assertOk();
        $this->get("/api/v1/invoices/{$invoice->id}/download")->assertOk();
    }

    public function test_full_history_release_opens_the_previous_owner_records_to_the_dealer(): void
    {
        $photo = $this->photoFor($this->previousOwnerRecord);
        $invoice = $this->invoiceFor($this->previousOwnerRecord);
        $sealedPhoto = $this->photoFor($this->sealedRecord);

        $this->consignment->update(['history_access_status' => VehicleConsignment::HISTORY_APPROVED]);

        $this->assertTrue(Gate::forUser($this->dealer)->allows('view', $this->previousOwnerRecord));
        $this->assertTrue(Gate::forUser($this->dealer)->allows('view', $this->sealedRecord));

        $this->actingAs($this->dealer)->get(route('maintenance-photos.show', $photo))->assertOk();
        $this->actingAs($this->dealer)->get(route('invoices.show', $invoice))->assertOk();
        $this->actingAs($this->dealer)->get(route('maintenance-photos.show', $sealedPhoto))->assertOk();
    }

    public function test_full_history_release_opens_the_previous_owner_records_to_the_dealer_on_the_api(): void
    {
        $photo = $this->photoFor($this->previousOwnerRecord);
        $invoice = $this->invoiceFor($this->previousOwnerRecord);

        $this->consignment->update(['history_access_status' => VehicleConsignment::HISTORY_APPROVED]);

        $this->actingAsApiUser($this->dealer);

        $this->getJson("/api/v1/maintenances/{$this->previousOwnerRecord->id}")->assertOk();
        $this->get(route('api.maintenance-photos.show', $photo))->assertOk();
        $this->get("/api/v1/invoices/{$invoice->id}/download")->assertOk();
    }

    public function test_release_does_not_reach_what_the_owner_hid(): void
    {
        $photo = $this->photoFor($this->hiddenRecord);
        $invoice = $this->invoiceFor($this->hiddenRecord);

        $this->consignment->update(['history_access_status' => VehicleConsignment::HISTORY_APPROVED]);

        $this->assertFalse(Gate::forUser($this->dealer)->allows('view', $this->hiddenRecord));

        $this->actingAs($this->dealer)->get(route('maintenance-photos.show', $photo))->assertForbidden();
        $this->actingAs($this->dealer)->get(route('invoices.show', $invoice))->assertForbidden();
    }

    public function test_a_disputed_consignment_closes_the_history_again(): void
    {
        $photo = $this->photoFor($this->previousOwnerRecord);

        $this->consignment->update([
            'history_access_status' => VehicleConsignment::HISTORY_APPROVED,
            'owner_disputed_at' => now(),
        ]);

        $this->assertFalse(Gate::forUser($this->dealer)->allows('view', $this->previousOwnerRecord));

        $this->actingAs($this->dealer)->get(route('maintenance-photos.show', $photo))->assertForbidden();
    }

    public function test_current_owner_still_opens_the_whole_history(): void
    {
        foreach ([$this->previousOwnerRecord, $this->sealedRecord, $this->dealerRecord, $this->hiddenRecord] as $record) {
            $photo = $this->photoFor($record);
            $invoice = $this->invoiceFor($record);

            $this->assertTrue(
                Gate::forUser($this->currentOwner)->allows('view', $record),
                'O dono atual vê todo o histórico, inclusive o que ele mesmo ocultou.',
            );

            $this->actingAs($this->currentOwner)->get(route('maintenance-photos.show', $photo))->assertOk();
            $this->actingAs($this->currentOwner)->get(route('invoices.show', $invoice))->assertOk();
        }

        $this->actingAsApiUser($this->currentOwner);
        $this->getJson("/api/v1/maintenances/{$this->previousOwnerRecord->id}")->assertOk();
    }

    public function test_admin_and_the_workshop_that_did_the_service_are_not_affected(): void
    {
        $admin = User::factory()->asAdmin()->create()->refresh();
        $sealedPhoto = $this->photoFor($this->sealedRecord);
        $sealedInvoice = $this->invoiceFor($this->sealedRecord);

        $this->assertTrue(Gate::forUser($admin)->allows('view', $this->previousOwnerRecord));
        $this->assertTrue(Gate::forUser($admin)->allows('view', $this->sealedRecord));
        $this->actingAs($admin)->get(route('maintenance-photos.show', $sealedPhoto))->assertOk();
        $this->actingAs($admin)->get(route('invoices.show', $sealedInvoice))->assertOk();

        // A oficina executora vê a OS que selou, e só ela.
        $this->assertTrue(Gate::forUser($this->workshopUser)->allows('view', $this->sealedRecord));
        $this->assertFalse(Gate::forUser($this->workshopUser)->allows('view', $this->previousOwnerRecord));
        $this->actingAs($this->workshopUser)->get(route('maintenance-photos.show', $sealedPhoto))->assertOk();
        $this->actingAs($this->workshopUser)->get(route('invoices.show', $sealedInvoice))->assertOk();

        $this->actingAsApiUser($this->workshopUser);
        $this->getJson("/api/v1/maintenances/{$this->sealedRecord->id}")->assertOk();
        $this->getJson("/api/v1/maintenances/{$this->previousOwnerRecord->id}")->assertForbidden();
    }

    public function test_previous_owner_keeps_only_what_their_own_tenant_declared(): void
    {
        $this->assertTrue(Gate::forUser($this->previousOwner)->allows('view', $this->previousOwnerRecord));
        $this->assertTrue(Gate::forUser($this->previousOwner)->allows('view', $this->sealedRecord));
        $this->assertFalse(Gate::forUser($this->previousOwner)->allows('view', $this->dealerRecord));
    }

    /**
     * A guarda contra as duas formas da regra divergirem de novo: para cada perfil, o que a
     * listagem do veículo traz é exatamente o que a policy autoriza registro por registro.
     */
    public function test_authorization_matches_the_vehicle_history_listing_for_every_profile(): void
    {
        $admin = User::factory()->asAdmin()->create()->refresh();
        $records = Maintenance::query()->where('vehicle_id', $this->vehicle->id)->get();

        $profiles = [
            'dono atual' => $this->currentOwner,
            'dono anterior' => $this->previousOwner,
            'lojista sem liberação' => $this->dealer,
            'admin' => $admin,
        ];

        foreach ($profiles as $label => $viewer) {
            $this->assertSameVisibility($label, $viewer, $records);
        }

        $this->consignment->update(['history_access_status' => VehicleConsignment::HISTORY_APPROVED]);
        $this->assertSameVisibility('lojista com liberação', $this->dealer, $records);
    }

    public function test_public_search_keeps_serving_the_public_photo_without_login(): void
    {
        $photo = $this->photoFor($this->previousOwnerRecord);

        $this->getJson('/api/v1/vehicles/search/CON5I1G')
            ->assertOk()
            ->assertJsonPath('data.license_plate', 'CON5I1G')
            ->assertJsonFragment(['id' => $photo->id, 'url' => $photo->url]);

        // A exceção é só da busca: a rota autorizada continua fechada para quem só buscou a placa.
        $this->getJson(route('api.maintenance-photos.show', $photo))->assertUnauthorized();
        $this->get(route('maintenance-photos.show', $photo))->assertRedirect(route('login'));
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, Maintenance>  $records
     */
    private function assertSameVisibility(string $label, User $viewer, $records): void
    {
        $listed = $this->vehicle->maintenancesVisibleTo($viewer)->pluck('id')->sort()->values()->all();
        $authorized = $records
            ->filter(fn (Maintenance $record): bool => Gate::forUser($viewer)->allows('view', $record))
            ->pluck('id')->sort()->values()->all();

        $this->assertSame(
            $listed,
            $authorized,
            'A autorização de registro e a listagem do histórico divergiram para: '.$label,
        );
    }

    private function photoFor(Maintenance $maintenance): MaintenancePhoto
    {
        $path = 'maintenance-photos/'.$maintenance->id.'-'.uniqid().'.jpg';
        Storage::disk('public')->put($path, 'bytes-da-foto');

        return MaintenancePhoto::factory()->create([
            'maintenance_id' => $maintenance->id,
            'path' => $path,
            'original_name' => 'servico.jpg',
            'created_by' => $maintenance->user_id,
        ]);
    }

    private function invoiceFor(Maintenance $maintenance): Invoice
    {
        $path = 'invoices/'.$maintenance->id.'-'.uniqid().'.pdf';
        Storage::disk('public')->put($path, '%PDF-1.4 teste');

        return Invoice::factory()->create([
            'maintenance_id' => $maintenance->id,
            'file_path' => $path,
            'file_name' => 'nota_fiscal.pdf',
        ]);
    }
}
