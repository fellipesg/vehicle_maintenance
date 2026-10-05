<?php

namespace Tests\Feature\Outreach;

use App\Mail\WorkshopProspectInviteMail;
use App\Models\WorkshopProspect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SendWorkshopProspectTestInviteCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'outreach.enabled' => false,
            'outreach.mailer' => 'outreach',
            'outreach.from.address' => 'contato@revisalog.com.br',
            'outreach.from.name' => 'Felipe, do RevisaLog',
        ]);
    }

    public function test_it_sends_the_first_touch_to_each_address_through_the_outreach_mailer_without_saving_anything(): void
    {
        Mail::fake();
        Queue::fake();

        $this->artisan('outreach:send-test', ['emails' => ['a@x.com.br', 'b@x.com.br']])
            ->expectsOutputToContain('Enviado para a@x.com.br')
            ->expectsOutputToContain('Enviado para b@x.com.br')
            ->assertSuccessful();

        Mail::mailer('outreach')->assertSent(WorkshopProspectInviteMail::class, 2);
        Mail::mailer('outreach')->assertSent(WorkshopProspectInviteMail::class, fn (WorkshopProspectInviteMail $mail): bool => $mail->hasTo('a@x.com.br')
            && $mail->variant === WorkshopProspectInviteMail::FIRST_TOUCH
            && $mail->envelope()->subject === 'Auto Center Exemplo no RevisaLog');
        Queue::assertNothingPushed();
        $this->assertSame(0, WorkshopProspect::count());
    }

    public function test_follow_up_and_nameless_variants_can_be_tested(): void
    {
        Mail::fake();

        $this->artisan('outreach:send-test', ['emails' => ['a@x.com.br'], '--follow-up' => true, '--name' => ''])->assertSuccessful();

        Mail::mailer('outreach')->assertSent(WorkshopProspectInviteMail::class, fn (WorkshopProspectInviteMail $mail): bool => $mail->variant === WorkshopProspectInviteMail::FOLLOW_UP
            && $mail->envelope()->subject === 'Sua oficina no RevisaLog'
            && str_contains($mail->render(), 'Olá, tudo bem?'));
    }

    public function test_an_invalid_address_or_missing_sender_fails_without_stopping_the_others(): void
    {
        Mail::fake();

        $this->artisan('outreach:send-test', ['emails' => ['nao-e-email', 'ok@x.com.br']])
            ->expectsOutputToContain('E-mail inválido: nao-e-email')
            ->expectsOutputToContain('Enviado para ok@x.com.br')
            ->assertFailed();
    }

    public function test_a_missing_sender_address_fails_on_a_real_transport(): void
    {
        config(['outreach.mailer' => 'array', 'outreach.from.address' => null]);

        $this->artisan('outreach:send-test', ['emails' => ['ok@x.com.br']])
            ->expectsOutputToContain('OUTREACH_FROM_ADDRESS')
            ->assertFailed();

        $this->assertCount(0, app('mail.manager')->mailer('array')->getSymfonyTransport()->messages());
    }

    public function test_it_delivers_through_a_real_transport_with_the_outreach_sender(): void
    {
        config(['outreach.mailer' => 'array']);

        $this->artisan('outreach:send-test', ['emails' => ['ok@x.com.br']])->assertSuccessful();

        $sent = app('mail.manager')->mailer('array')->getSymfonyTransport()->messages()->last()->getOriginalMessage();
        $this->assertSame('contato@revisalog.com.br', $sent->getFrom()[0]->getAddress());
        $this->assertSame('ok@x.com.br', $sent->getTo()[0]->getAddress());
    }
}
