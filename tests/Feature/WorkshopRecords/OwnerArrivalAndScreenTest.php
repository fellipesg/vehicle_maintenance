<?php

namespace Tests\Feature\WorkshopRecords;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\WorkshopRecordsPendingNotification;
use App\Services\Crlv\CrlvParseResult;
use App\Services\Crlv\CrlvPdfParser;
use App\Services\FcmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Concerns\BuildsOwnerlessRecords;
use Tests\TestCase;

/**
 * Chegada do proprietário (CRLV-e na web, sem auto-adotar) e a tela "Registros de oficinas".
 */
class OwnerArrivalAndScreenTest extends TestCase
{
    use BuildsOwnerlessRecords;
    use RefreshDatabase;

    private function crlv(?string $chassis = self::OWNERLESS_CHASSIS, string $renavam = '12345678901'): CrlvParseResult
    {
        return new CrlvParseResult(
            licensePlate: 'ABC1D23',
            renavam: $renavam,
            brand: 'Fiat',
            model: 'Argo',
            year: 2021,
            chassis: $chassis,
            crvNumber: '246813579024',
            exerciseYear: (int) now()->year,
            ownerName: 'Titular',
            ownerDocument: '52998224725',
        );
    }

    private function claimWithCrlv(User $user, CrlvParseResult $crlv): \Illuminate\Testing\TestResponse
    {
        $this->mock(CrlvPdfParser::class, function ($parser) use ($crlv): void {
            $parser->shouldReceive('isCrlvDocument')->andReturn(true);
            $parser->shouldReceive('parseUpload')->andReturn($crlv);
        });

        $this->actingAs($user)
            ->post(route('user.vehicles.import-crlv'), ['crlv' => UploadedFile::fake()->create('CRLV-e.pdf', 100, 'application/pdf')])
            ->assertRedirect(route('user.vehicles.claim.preview'));

        return $this->actingAs($user)
            ->from(route('user.vehicles.claim.preview'))
            ->post(route('user.vehicles.claim.store'), ['crlv_verification_token' => session('crlv_verification.token')]);
    }

    public function test_crlv_claim_works_without_plate_or_renavam_verifies_and_notifies_without_adopting(): void
    {
        Notification::fake();
        $this->mock(FcmService::class)->shouldReceive('sendToUser')->once();
        $vehicle = $this->ownerlessVehicle();
        $record = $this->ownerlessRecord($this->workshopAccount(), $vehicle);
        $owner = User::factory()->asUser()->create(['document' => '52998224725'])->refresh();

        $this->claimWithCrlv($owner, $this->crlv())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('user.workshop-records.index'));

        $fresh = $vehicle->fresh();
        $this->assertSame('ABC1D23', $fresh->license_plate);
        $this->assertSame('12345678901', $fresh->renavam);
        $this->assertNotNull($owner->vehicles()->first()->pivot->ownership_verified_at);

        // Sem auto-adoção: o registro continua pendente e sem tenant até a escolha do proprietário.
        $this->assertNull($record->fresh()->tenant_id);
        $this->assertSame(Maintenance::OWNER_PENDING, $record->fresh()->owner_status);
        Notification::assertSentTo($owner, WorkshopRecordsPendingNotification::class);
    }

    public function test_crlv_with_another_chassis_does_not_reach_the_workshop_vehicle(): void
    {
        $vehicle = $this->ownerlessVehicle();
        $owner = User::factory()->asUser()->create()->refresh();
        $this->mock(CrlvPdfParser::class, function ($parser): void {
            $parser->shouldReceive('isCrlvDocument')->andReturn(true);
            $parser->shouldReceive('parseUpload')->andReturn($this->crlv(chassis: '9BWZZZ377VT009999'));
        });

        $this->actingAs($owner)
            ->post(route('user.vehicles.import-crlv'), ['crlv' => UploadedFile::fake()->create('CRLV-e.pdf', 100, 'application/pdf')])
            ->assertRedirect(route('user.vehicles.import.preview'));

        $this->assertFalse($vehicle->fresh()->hasCurrentOwner());
    }

    public function test_crlv_claim_fails_when_another_vehicle_already_has_the_renavam(): void
    {
        Vehicle::factory()->create(['renavam' => '12345678901']);
        $vehicle = $this->ownerlessVehicle();
        $owner = User::factory()->asUser()->create()->refresh();

        $this->claimWithCrlv($owner, $this->crlv())->assertSessionHasErrors('vehicle');

        $this->assertFalse($vehicle->fresh()->hasCurrentOwner());
        $this->assertNull($vehicle->fresh()->renavam);
    }

    public function test_screen_lists_pending_records_and_dashboard_shows_the_card(): void
    {
        $vehicle = $this->ownerlessVehicle();
        $this->withPendingAttachments($this->ownerlessRecord($this->workshopAccount(), $vehicle));
        $owner = $this->ownerOf($vehicle);

        $this->actingAs($owner)->get(route('user.dashboard'))
            ->assertOk()
            ->assertSee('data-workshop-records-card', false)
            ->assertSee('Ver registros de oficinas');

        $this->actingAs($owner)->get(route('user.workshop-records.index'))
            ->assertOk()
            ->assertSee('Registros de oficinas')
            ->assertSee('Troca de óleo')
            ->assertSee('Filtro de óleo')
            ->assertSee('Vincular este registro ao meu histórico')
            ->assertSee('Ocultar do histórico público')
            ->assertSee('Aceitar as notas fiscais e fotos da oficina')
            ->assertDontSee('CPF 123')
            ->assertDontSee('55,90');
    }

    public function test_dashboard_has_no_card_without_pending_records(): void
    {
        $owner = $this->ownerOf($this->ownerlessVehicle());

        $this->actingAs($owner)->get(route('user.dashboard'))
            ->assertOk()
            ->assertDontSee('data-workshop-records-card', false);
    }

    public function test_web_decision_links_attaches_when_verified_and_hides(): void
    {
        $vehicle = $this->ownerlessVehicle();
        $record = $this->withPendingAttachments($this->ownerlessRecord($this->workshopAccount(), $vehicle));
        $owner = $this->ownerOf($vehicle);

        $this->actingAs($owner)
            ->post(route('user.workshop-records.decide', $record), ['link' => '1', 'attach_files' => '1', 'hide_from_public' => '1'])
            ->assertRedirect()->assertSessionHas('success');

        $fresh = $record->fresh();
        $this->assertSame('linked', $fresh->owner_status);
        $this->assertSame('accepted', $fresh->attachments_status);
        $this->assertNotNull($fresh->hidden_from_public_at);
        $this->assertSame($owner->tenant_id, $fresh->tenant_id);
    }

    public function test_web_decision_by_a_stranger_is_forbidden(): void
    {
        $vehicle = $this->ownerlessVehicle();
        $record = $this->ownerlessRecord($this->workshopAccount(), $vehicle);
        $this->ownerOf($vehicle);
        $stranger = User::factory()->asUser()->create();

        $this->actingAs($stranger)
            ->post(route('user.workshop-records.decide', $record), ['link' => '1'])
            ->assertForbidden();
    }
}
