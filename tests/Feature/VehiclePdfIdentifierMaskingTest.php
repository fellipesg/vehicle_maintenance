<?php

namespace Tests\Feature;

use App\Mail\VehicleMaintenancePdfMail;
use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleConsignment;
use App\Models\VehiclePdfExport;
use App\Support\AppStorage;
use App\Support\DemoWarrantyPdfValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Histórico em PDF, pela API (POST /api/v1/vehicles/{id}/export-pdf) e por e-mail
 * (user.vehicles.export-pdf): chassi e RENAVAM inteiros e o código do motor só para quem pediu sendo
 * o dono atual (VehiclePolicy::update), a mesma regra da API e da ficha na web. O lojista ou a conta
 * com consignação aprovada e o admin recebem o PDF com os números parciais.
 */
class VehiclePdfIdentifierMaskingTest extends TestCase
{
    use RefreshDatabase;

    private const CHASSIS = '9BWZZZ377VT004251';

    private const RENAVAM = '12345678901';

    private const ENGINE = 'MTR7Q4X9Z21';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->fakeCoversDisk('r2');
    }

    public function test_api_export_requested_by_a_consignment_dealer_masks_the_numbers(): void
    {
        [, $vehicle] = $this->ownerWithVehicle();
        $dealer = User::factory()->asGarage()->create()->refresh();
        $dealer->vehicles()->attach($vehicle->id, [
            'is_current_owner' => false,
            'purchase_date' => now(),
            'tenant_id' => $dealer->tenant_id,
            'ownership_type' => 'consignment',
        ]);
        $this->approveConsignment($dealer, $vehicle);

        $this->assertMaskedPdf($this->apiExport($dealer, $vehicle));
    }

    public function test_api_export_requested_by_an_admin_masks_the_numbers(): void
    {
        [, $vehicle] = $this->ownerWithVehicle();

        $this->assertMaskedPdf($this->apiExport(User::factory()->asUser()->asAdmin()->create(), $vehicle));
    }

    public function test_api_export_requested_by_the_owner_keeps_the_full_numbers(): void
    {
        [$owner, $vehicle] = $this->ownerWithVehicle();

        $this->assertFullPdf($this->apiExport($owner, $vehicle));
    }

    public function test_emailed_pdf_for_an_account_without_the_vehicle_is_refused(): void
    {
        Mail::fake();
        [, $vehicle] = $this->ownerWithVehicle();
        $account = User::factory()->asUser()->create();

        // Consignação é coisa de lojista: uma conta de proprietário que não é dona do veículo não
        // exporta o histórico dele (a versão mascarada do lojista está no teste da API acima).
        $this->actingAs($account)->post(route('user.vehicles.export-pdf', $vehicle))->assertForbidden();
    }

    public function test_emailed_pdf_for_the_owner_keeps_the_full_numbers(): void
    {
        Mail::fake();
        [$owner, $vehicle] = $this->ownerWithVehicle();

        $this->actingAs($owner)->post(route('user.vehicles.export-pdf', $vehicle))->assertRedirect();

        $this->assertFullPdf($this->emailedPdf($owner));
    }

    private function apiExport(User $requester, Vehicle $vehicle): string
    {
        $this->actingAsApiUser($requester);

        $exportId = $this->postJson("/api/v1/vehicles/{$vehicle->id}/export-pdf")->assertAccepted()->json('data.export_id');
        $export = VehiclePdfExport::query()->findOrFail($exportId);

        $this->assertSame(VehiclePdfExport::STATUS_COMPLETED, $export->status);

        return DemoWarrantyPdfValidator::extractText((string) AppStorage::disk()->get($export->file_path));
    }

    private function emailedPdf(User $recipient): string
    {
        $pdf = null;

        Mail::assertSent(VehicleMaintenancePdfMail::class, function (VehicleMaintenancePdfMail $mail) use ($recipient, &$pdf): bool {
            $pdf = $mail->pdfContent;

            return $mail->hasTo($recipient->email);
        });

        return DemoWarrantyPdfValidator::extractText((string) $pdf);
    }

    private function assertMaskedPdf(string $text): void
    {
        $this->assertStringContainsString('Chassi:', $text);
        $this->assertStringContainsString('4251', $text);
        $this->assertStringContainsString('8901', $text);
        $this->assertStringNotContainsString(self::CHASSIS, $text);
        $this->assertStringNotContainsString(self::RENAVAM, $text);
        $this->assertStringNotContainsString(self::ENGINE, $text);
    }

    private function assertFullPdf(string $text): void
    {
        $this->assertStringContainsString(self::CHASSIS, $text);
        $this->assertStringContainsString(self::RENAVAM, $text);
        $this->assertStringContainsString(self::ENGINE, $text);
    }

    /**
     * @return array{User, Vehicle}
     */
    private function ownerWithVehicle(): array
    {
        $owner = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create([
            'chassis' => self::CHASSIS,
            'renavam' => self::RENAVAM,
            'engine' => self::ENGINE,
        ]);
        $this->attachVehicleToUser($owner, $vehicle);
        Maintenance::factory()->declaredByOwner()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $owner->id,
            'tenant_id' => $owner->tenant_id,
        ]);

        return [$owner->refresh(), $vehicle];
    }

    private function approveConsignment(User $account, Vehicle $vehicle): void
    {
        VehicleConsignment::factory()->create([
            'vehicle_id' => $vehicle->id,
            'garage_user_id' => $account->id,
            'tenant_id' => $account->tenant_id,
            'history_access_status' => 'approved',
            'power_of_attorney_path' => 'procuracoes/teste.pdf',
        ]);
    }
}
