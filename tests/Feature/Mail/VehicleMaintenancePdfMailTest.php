<?php

namespace Tests\Feature\Mail;

use App\Mail\VehicleMaintenancePdfMail;
use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * E-mail do histórico em PDF: markdown com a marca, resumo de procedência, lista dos anexos com a
 * pontuação montada em PHP e link comum (sem download) para a ficha do veículo.
 */
class VehicleMaintenancePdfMailTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_mail_is_markdown_with_summary_attachments_and_vehicle_link(): void
    {
        $user = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create(['brand' => 'Honda', 'model' => 'Civic', 'license_plate' => 'ABC1D23']);
        $this->attachVehicleToUser($user, $vehicle);

        Maintenance::factory()->count(2)->sealedByWorkshop()->create(['vehicle_id' => $vehicle->id, 'user_id' => $user->id, 'tenant_id' => $user->tenant_id]);
        Maintenance::factory()->declaredByOwner()->create(['vehicle_id' => $vehicle->id, 'user_id' => $user->id, 'tenant_id' => $user->tenant_id]);

        $mailable = new VehicleMaintenancePdfMail($vehicle, '%PDF', 'historico_manutencoes_ABC1D23.pdf', [
            ['filename' => 'nota.xml', 'content' => '<nfe/>', 'mime' => 'application/octet-stream'],
            ['filename' => 'vazia.pdf', 'content' => '', 'mime' => 'application/pdf'],
        ]);

        $this->assertSame('emails.vehicle-maintenance-pdf', $mailable->content()->markdown);
        $mailable->assertHasSubject('Histórico de manutenções — Honda Civic');
        $mailable->assertSeeInHtml('Histórico do Honda Civic');
        $mailable->assertSeeInHtml('ABC1D23');
        $mailable->assertSeeInHtml('2 com selo · 1 declarada.');
        $mailable->assertSeeInOrderInHtml([
            'historico_manutencoes_ABC1D23.pdf: histórico de manutenções em PDF',
            'nota.xml: nota fiscal',
        ]);
        $mailable->assertDontSeeInHtml('vazia.pdf');
        $mailable->assertDontSeeInHtml(' .');
        $mailable->assertSeeInHtml('href="'.route('user.vehicles.show', $vehicle).'"', false);
        $mailable->assertSeeInHtml('Ver veículo na RevisaLog');
        $mailable->assertDontSeeInHtml(' download', false);
        $mailable->assertSeeInHtml('href="'.route('verification.lookup').'"', false);
        $mailable->assertSeeInText('2 com selo · 1 declarada.');
        $mailable->assertSeeInText('nota.xml: nota fiscal');
    }

    public function test_mail_without_maintenances_says_so(): void
    {
        $vehicle = Vehicle::factory()->create(['brand' => 'Fiat', 'model' => 'Uno']);

        $mailable = new VehicleMaintenancePdfMail($vehicle, '%PDF', 'historico.pdf');

        $mailable->assertSeeInHtml('O veículo ainda não tem manutenções registradas.');
        $mailable->assertDontSeeInHtml('com selo ·');
        $this->assertSame(['historico.pdf: histórico de manutenções em PDF'], $mailable->attachmentLines());
    }

    public function test_generation_time_is_shown_in_brasilia_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-02 02:30:00', 'UTC'));

        $mailable = new VehicleMaintenancePdfMail(Vehicle::factory()->create(), '%PDF', 'historico.pdf');

        $mailable->assertSeeInHtml('gerado em 01/03/2026 às 23:30');
    }

    public function test_provenance_summary_matches_the_pdf_cover(): void
    {
        $this->assertSame('3 com selo · 1 declarada', VehicleMaintenancePdfMail::provenanceSummary(3, 1));
        $this->assertSame('0 com selo · 2 declaradas', VehicleMaintenancePdfMail::provenanceSummary(0, 2));
    }
}
