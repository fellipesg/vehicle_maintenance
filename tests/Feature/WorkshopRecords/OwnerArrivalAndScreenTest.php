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

    private function checkboxTag(string $html, string $id): string
    {
        preg_match('/<input[^>]*id="'.preg_quote($id, '/').'"[^>]*>/', $html, $matches);

        return $matches[0] ?? '';
    }

    public function test_consent_checkbox_is_unchecked_for_pending_and_checked_only_when_linked(): void
    {
        $vehicle = $this->ownerlessVehicle();
        $pending = $this->ownerlessRecord($this->workshopAccount(), $vehicle);
        $owner = $this->ownerOf($vehicle);
        $linked = $this->ownerlessRecord($this->workshopAccount(), $vehicle, ['kilometers' => 51_000]);
        $linked->forceFill(['owner_status' => Maintenance::OWNER_LINKED, 'tenant_id' => $owner->tenant_id])->save();

        $html = $this->actingAs($owner)->get(route('user.workshop-records.index', ['status' => 'all']))
            ->assertOk()
            ->getContent();

        $this->assertDoesNotMatchRegularExpression('/\schecked(=|\s|\/|>)/', $this->checkboxTag($html, 'link-'.$pending->id));
        $this->assertMatchesRegularExpression('/\schecked(=|\s|\/|>)/', $this->checkboxTag($html, 'link-'.$linked->id));
    }

    public function test_attachment_warning_shows_only_for_records_with_pending_files(): void
    {
        $vehicle = $this->ownerlessVehicle();
        $owner = $this->ownerOf($vehicle);
        $withFiles = $this->withPendingAttachments($this->ownerlessRecord($this->workshopAccount(), $vehicle));

        $this->actingAs($owner)->get(route('user.workshop-records.index'))
            ->assertOk()
            ->assertSee('Se você não aceitar, as notas fiscais e fotos da oficina são apagadas ao salvar.');

        $withFiles->forceFill(['attachments_status' => Maintenance::ATTACHMENTS_NONE])->save();
        $withFiles->invoices()->delete();
        $withFiles->photos()->delete();

        $this->actingAs($owner)->get(route('user.workshop-records.index'))
            ->assertOk()
            ->assertDontSee('são apagadas ao salvar.');
    }

    public function test_claim_wizard_says_records_await_the_owner_decision(): void
    {
        $vehicle = $this->ownerlessVehicle();
        $this->ownerlessRecord($this->workshopAccount(), $vehicle);
        $this->ownerlessRecord($this->workshopAccount(), $vehicle, ['kilometers' => 51_000]);
        $owner = User::factory()->asUser()->create(['document' => '52998224725'])->refresh();

        $this->claimPreview($owner, $this->crlv())
            ->assertOk()
            ->assertSee('2 registros de oficina aguardam a sua decisão em')
            ->assertDontSee('no histórico, que passa a aparecer para você');
    }

    public function test_claim_wizard_keeps_the_old_text_for_ordinary_maintenances(): void
    {
        $vehicle = $this->ownerlessVehicle();
        Maintenance::factory()->sealedByWorkshop()->create(['vehicle_id' => $vehicle->id]);
        $owner = User::factory()->asUser()->create(['document' => '52998224725'])->refresh();

        $this->claimPreview($owner, $this->crlv())
            ->assertOk()
            ->assertSee('no histórico, que passa a aparecer para você')
            ->assertDontSee('aguarda a sua decisão');
    }

    public function test_arrival_notification_counts_and_words_only_the_pending_records(): void
    {
        Notification::fake();
        $this->mock(FcmService::class)->shouldReceive('sendToUser')->once();
        $vehicle = $this->ownerlessVehicle();
        $this->ownerlessRecord($this->workshopAccount(), $vehicle);
        $linked = $this->ownerlessRecord($this->workshopAccount(), $vehicle, ['kilometers' => 51_000]);
        $owner = $this->ownerOf($vehicle);
        $linked->forceFill(['owner_status' => Maintenance::OWNER_LINKED, 'tenant_id' => $owner->tenant_id])->save();

        app(\App\Services\Maintenance\WorkshopRecordsArrivalNotifier::class)->notify($owner, $vehicle);

        Notification::assertSentTo($owner, WorkshopRecordsPendingNotification::class, function (WorkshopRecordsPendingNotification $notification): bool {
            return $notification->count === 1
                && $notification->body() === '1 registro de oficina aguarda a sua decisão no seu Fiat Argo.'
                && $notification->title() === 'Registros de oficina aguardam a sua decisão';
        });
    }

    private function claimPreview(User $user, CrlvParseResult $crlv): \Illuminate\Testing\TestResponse
    {
        $this->mock(CrlvPdfParser::class, function ($parser) use ($crlv): void {
            $parser->shouldReceive('isCrlvDocument')->andReturn(true);
            $parser->shouldReceive('parseUpload')->andReturn($crlv);
        });

        $this->actingAs($user)
            ->post(route('user.vehicles.import-crlv'), ['crlv' => UploadedFile::fake()->create('CRLV-e.pdf', 100, 'application/pdf')])
            ->assertRedirect(route('user.vehicles.claim.preview'));

        return $this->actingAs($user)->get(route('user.vehicles.claim.preview'));
    }
}
