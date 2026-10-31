<?php

namespace Tests\Feature;

use App\Models\EmailSuppression;
use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Workshop;
use App\Models\WorkshopMessageOptOut;
use App\Models\WorkshopMessageTemplate;
use App\Notifications\WorkshopFollowUpNotification;
use App\Services\FcmService;
use App\Services\Workshop\WorkshopMessageTemplateDispatcher;
use App\Support\Workshop\WorkshopMessageConsent;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class WorkshopMessageOptOutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-01');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_follow_up_email_carries_one_click_unsubscribe_that_stops_that_workshop(): void
    {
        $this->mock(FcmService::class)->shouldReceive('sendToUser')->once()->andReturnTrue();

        ['owner' => $owner, 'workshop' => $workshop] = $this->followUpScenario();

        $this->assertSame(1, app(WorkshopMessageTemplateDispatcher::class)->dispatchFollowUps());

        $headers = $this->lastSentMail()->getHeaders();
        $this->assertSame('List-Unsubscribe=One-Click', $headers->get('List-Unsubscribe-Post')->getBodyAsString());

        $unsubscribeUrl = trim($headers->get('List-Unsubscribe')->getBodyAsString(), '<>');
        $this->assertStringContainsString(url('/oficinas/descadastrar'), $unsubscribeUrl);
        $this->assertStringContainsString('signature=', $unsubscribeUrl);

        $this->post($unsubscribeUrl)->assertOk()->assertSee('Pronto');

        $this->assertDatabaseHas('workshop_message_opt_outs', [
            'user_id' => $owner->id,
            'workshop_id' => $workshop->id,
        ]);
    }

    public function test_one_click_unsubscribe_is_idempotent(): void
    {
        $owner = User::factory()->asUser()->create();
        $workshop = Workshop::factory()->create();
        $url = app(WorkshopMessageConsent::class)->unsubscribeUrl($owner->id, $workshop->id);

        $this->post($url)->assertOk();
        $this->post($url)->assertOk();

        $this->assertDatabaseCount('workshop_message_opt_outs', 1);
    }

    public function test_page_offers_silencing_every_workshop(): void
    {
        $owner = User::factory()->asUser()->create();
        $workshop = Workshop::factory()->create(['name' => 'Oficina Central']);
        $url = app(WorkshopMessageConsent::class)->unsubscribeUrl($owner->id, $workshop->id);

        $this->get($url)->assertOk()->assertSee('Oficina Central');

        $this->post($url, ['scope' => WorkshopMessageConsent::SCOPE_ALL])->assertOk();

        $this->assertDatabaseHas('workshop_message_opt_outs', [
            'user_id' => $owner->id,
            'workshop_id' => null,
        ]);
    }

    public function test_unsigned_and_tampered_links_are_refused(): void
    {
        $owner = User::factory()->asUser()->create();
        $workshop = Workshop::factory()->create();

        $this->get(route('workshops.messages.unsubscribe.show', ['payload' => 'x']))->assertForbidden();

        $this->get(URL::signedRoute('workshops.messages.unsubscribe.show', ['payload' => 'nao-e-cifrado']))
            ->assertNotFound();

        $this->post(app(WorkshopMessageConsent::class)->unsubscribeUrl($owner->id, $workshop->id).'&scope=all')
            ->assertForbidden();

        $this->assertDatabaseCount('workshop_message_opt_outs', 0);
    }

    public function test_opted_out_owner_receives_neither_email_nor_app_notice(): void
    {
        $this->mock(FcmService::class)->shouldNotReceive('sendToUser');

        ['owner' => $owner, 'workshop' => $workshop] = $this->followUpScenario();
        WorkshopMessageOptOut::factory()->create([
            'user_id' => $owner->id,
            'workshop_id' => $workshop->id,
        ]);

        $this->assertSame(0, app(WorkshopMessageTemplateDispatcher::class)->dispatchFollowUps());

        $this->assertDatabaseMissing('notifications', ['type' => WorkshopFollowUpNotification::class]);
        $this->assertCount(0, $this->sentMail());
    }

    public function test_suppressed_address_keeps_the_app_notice_but_gets_no_email(): void
    {
        $this->mock(FcmService::class)->shouldReceive('sendToUser')->once()->andReturnTrue();

        ['owner' => $owner] = $this->followUpScenario();
        EmailSuppression::suppress($owner->email, EmailSuppression::REASON_BOUNCED);

        $this->assertSame(1, app(WorkshopMessageTemplateDispatcher::class)->dispatchFollowUps());

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $owner->id,
            'type' => WorkshopFollowUpNotification::class,
        ]);
        $this->assertCount(0, $this->sentMail());
    }

    /**
     * O POST de um clique chega sem sessão: o caminho tem de estar na lista do CSRF, e o padrão
     * tem de casar com ele (o 'convite/descadastrar/*' antigo não casava com o caminho exato).
     * Em teste o CSRF é sempre pulado (runningUnitTests), então a conferência é na configuração.
     */
    public function test_one_click_unsubscribe_posts_are_outside_csrf(): void
    {
        $excluded = app(ValidateCsrfToken::class)->getExcludedPaths();

        foreach (['oficinas/descadastrar', 'convite/descadastrar'] as $path) {
            $request = Request::create('/'.$path.'?payload=x&signature=y', 'POST');

            $this->assertTrue(
                collect($excluded)->contains(fn (string $pattern): bool => $request->is($pattern)),
                "O POST de um clique em /{$path} cairia no CSRF.",
            );
        }
    }

    /**
     * @return \Illuminate\Support\Collection<int, \Illuminate\Mail\SentMessage>
     */
    private function sentMail(): \Illuminate\Support\Collection
    {
        return app('mail.manager')->mailer('array')->getSymfonyTransport()->messages();
    }

    private function lastSentMail(): \Symfony\Component\Mime\Email
    {
        $message = $this->sentMail()->last();

        $this->assertNotNull($message);

        return $message->getOriginalMessage();
    }

    /**
     * @return array{owner: User, workshop: Workshop, vehicle: Vehicle}
     */
    private function followUpScenario(): array
    {
        $owner = User::factory()->asUser()->create();
        $workshop = Workshop::factory()->create(['name' => 'Oficina Central']);
        $vehicle = Vehicle::factory()->create([
            'current_kilometers' => 70_000,
            'odometer_at_registration' => 70_000,
        ]);
        $this->attachVehicleToUser($owner, $vehicle);

        Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $owner->id,
            'tenant_id' => $owner->tenant_id,
            'workshop_id' => $workshop->id,
            'kilometers' => 80_000,
            'maintenance_date' => '2025-01-01',
            'service_category' => 'mechanical',
        ]);

        WorkshopMessageTemplate::factory()->forWorkshop($workshop)->scheduledRevision()->create([
            'lead_kilometers' => 2_000,
        ]);

        return ['owner' => $owner, 'workshop' => $workshop, 'vehicle' => $vehicle];
    }
}
