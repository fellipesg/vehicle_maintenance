<?php

namespace Tests\Feature;

use App\Jobs\EmailVehicleMaintenancePdf;
use App\Mail\VehicleMaintenancePdfMail;
use App\Models\Invoice;
use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EmailVehicleMaintenancePdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_emails_generated_pdf_to_the_owner(): void
    {
        Mail::fake();
        Storage::fake('public');

        $user = User::factory()->asUser()->create(['email' => 'dono@example.com']);
        $vehicle = Vehicle::factory()->create([
            'license_plate' => 'ABC1D23',
            'brand' => 'Honda',
        ]);
        $this->attachVehicleToUser($user, $vehicle);

        $maintenance = Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'maintenance_type' => 'Revisão',
        ]);

        $xmlPath = 'invoices/nota.xml';
        Storage::disk('public')->put($xmlPath, '<nfe/>');
        Invoice::factory()->create([
            'maintenance_id' => $maintenance->id,
            'file_path' => $xmlPath,
            'file_name' => 'nota.xml',
        ]);

        (new EmailVehicleMaintenancePdf($user, $vehicle))->handle(app(\App\Services\Vehicle\VehicleMaintenancePdfExporter::class));

        Mail::assertSent(VehicleMaintenancePdfMail::class, function (VehicleMaintenancePdfMail $mail) use ($user, $vehicle) {
            $names = collect($mail->attachments())->map(fn ($attachment) => $attachment->as);
            $invoiceNames = collect($mail->invoiceAttachments)->pluck('filename');

            return $mail->hasTo($user->email)
                && $mail->vehicle->is($vehicle)
                && str_contains($mail->filename, 'ABC1D23')
                && $names->contains('historico_manutencoes_ABC1D23_Honda_'.now()->format('Y-m-d').'.pdf')
                && $invoiceNames->contains('nota.xml')
                && $names->contains('nota.xml');
        });

        Storage::disk('public')->assertExists($xmlPath);
    }
}
