<?php

namespace Tests\Feature\Outreach;

use App\Enums\WorkshopProspectStatus;
use App\Jobs\SendWorkshopProspectInvite;
use App\Mail\WorkshopProspectInviteMail;
use App\Models\EmailSuppression;
use App\Models\WorkshopProspect;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class SendWorkshopProspectInviteJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'outreach.mailer' => 'outreach',
            'outreach.from.address' => 'felipe@mail.revisalog.com.br',
            'outreach.from.name' => 'Felipe, do RevisaLog',
            'outreach.reply_to' => 'felipe@revisalog.com.br',
            'outreach.sender_signature_name' => 'Felipe Gonçalves',
        ]);
        $this->travelTo(Carbon::parse('2026-10-07 14:00:00', 'UTC'));
    }

    private function run_(WorkshopProspect $prospect): void
    {
        (new SendWorkshopProspectInvite($prospect->id))->handle();
    }

    public function test_it_sends_the_first_touch_through_the_outreach_mailer_only(): void
    {
        Mail::fake();
        $prospect = WorkshopProspect::factory()->create(['trade_name' => 'Auto Zé', 'email' => 'ze@x.com.br']);

        $this->run_($prospect);

        Mail::mailer('outreach')->assertSent(WorkshopProspectInviteMail::class, function (WorkshopProspectInviteMail $mail) use ($prospect): bool {
            $this->assertSame('outreach', $mail->mailer);
            $this->assertTrue($mail->hasTo('ze@x.com.br'));
            $this->assertTrue($mail->hasFrom('felipe@mail.revisalog.com.br', 'Felipe, do RevisaLog'));
            $this->assertTrue($mail->hasReplyTo('felipe@revisalog.com.br'));
            $this->assertSame('Auto Zé no RevisaLog', $mail->envelope()->subject);

            $headers = $mail->headers()->text;
            $this->assertSame('List-Unsubscribe=One-Click', $headers['List-Unsubscribe-Post']);
            $this->assertStringStartsWith('<'.url('/descadastrar/'.$prospect->token), $headers['List-Unsubscribe']);
            $this->assertStringContainsString('signature=', $headers['List-Unsubscribe']);
            $this->assertStringEndsWith('<mailto:felipe@revisalog.com.br?subject=descadastrar>', $headers['List-Unsubscribe']);

            $mail->assertSeeInText('Olá, equipe da Auto Zé.');
            $mail->assertSeeInText('Sou o Felipe Gonçalves, do RevisaLog');
            $mail->assertSeeInText(route('outreach.click', $prospect->token));
            $mail->assertSeeInHtml(route('outreach.click', $prospect->token));
            $mail->assertSeeInText('Encontramos este e-mail no cadastro público de CNPJ');

            return true;
        });

        $prospect->refresh();
        $this->assertSame(WorkshopProspectStatus::Sent, $prospect->status);
        $this->assertNotNull($prospect->first_sent_at);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{32}@mail\.revisalog\.com\.br$/', $prospect->first_message_id);
    }

    public function test_first_touch_sets_an_explicit_message_id_header_matching_the_stored_one(): void
    {
        Mail::fake();
        $prospect = WorkshopProspect::factory()->create();

        $this->run_($prospect);

        Mail::mailer('outreach')->assertSent(WorkshopProspectInviteMail::class, function (WorkshopProspectInviteMail $mail) use ($prospect): bool {
            $this->assertSame($prospect->fresh()->first_message_id, $mail->headers()->messageId);
            $this->assertSame([], $mail->headers()->references);
            $this->assertArrayNotHasKey('In-Reply-To', $mail->headers()->text);

            return true;
        });
    }

    public function test_the_html_has_no_tracking_pixel_or_images(): void
    {
        $prospect = WorkshopProspect::factory()->create();
        $html = (new WorkshopProspectInviteMail($prospect))->render();

        $this->assertStringNotContainsString('<img', $html);
    }

    public function test_it_sends_the_follow_up_variant_for_a_sent_prospect(): void
    {
        Mail::fake();
        $prospect = WorkshopProspect::factory()->sent(now()->subDays(10))->create(['trade_name' => 'Auto Zé', 'first_message_id' => 'abc123@mail.revisalog.com.br']);

        $this->run_($prospect);

        Mail::mailer('outreach')->assertSent(WorkshopProspectInviteMail::class, function (WorkshopProspectInviteMail $mail): bool {
            $this->assertSame('Re: Auto Zé no RevisaLog', $mail->envelope()->subject);
            $this->assertSame(['abc123@mail.revisalog.com.br'], $mail->headers()->references);
            $this->assertSame('<abc123@mail.revisalog.com.br>', $mail->headers()->text['In-Reply-To']);
            $this->assertNull($mail->headers()->messageId);
            $mail->assertSeeInText('Passando só para saber se a mensagem anterior chegou');
            $mail->assertDontSeeInText('Sou o Felipe');

            return true;
        });
        $prospect->refresh();
        $this->assertSame(WorkshopProspectStatus::FollowedUp, $prospect->status);
        $this->assertNotNull($prospect->follow_up_sent_at);
    }

    public function test_it_never_sends_to_a_suppressed_email(): void
    {
        Mail::fake();
        $prospect = WorkshopProspect::factory()->create(['email' => 'fora@x.com.br']);
        EmailSuppression::suppress('FORA@x.com.br', 'unsubscribed');

        $this->run_($prospect);

        Mail::mailer('outreach')->assertNothingSent();
        $this->assertSame(WorkshopProspectStatus::Skipped, $prospect->fresh()->status);
    }

    public function test_running_twice_sends_only_one_email(): void
    {
        Mail::fake();
        $prospect = WorkshopProspect::factory()->create();

        $this->run_($prospect);
        $this->run_($prospect);

        Mail::mailer('outreach')->assertSentCount(1);
    }

    /**
     * @return array<string, array{WorkshopProspectStatus}>
     */
    public static function nonContactableStatuses(): array
    {
        return collect([
            WorkshopProspectStatus::Clicked, WorkshopProspectStatus::Replied, WorkshopProspectStatus::Converted,
            WorkshopProspectStatus::Unsubscribed, WorkshopProspectStatus::Bounced, WorkshopProspectStatus::Failed,
            WorkshopProspectStatus::FollowedUp, WorkshopProspectStatus::Sending, WorkshopProspectStatus::Skipped,
        ])->mapWithKeys(fn ($status) => [$status->value => [$status]])->all();
    }

    #[DataProvider('nonContactableStatuses')]
    public function test_it_sends_nothing_for_other_statuses(WorkshopProspectStatus $status): void
    {
        Mail::fake();
        $prospect = WorkshopProspect::factory()->create(['status' => $status, 'first_sent_at' => now()->subDays(30)]);

        $this->run_($prospect);

        Mail::mailer('outreach')->assertNothingSent();
        $this->assertSame($status, $prospect->fresh()->status);
    }

    public function test_follow_up_waits_for_the_configured_business_days(): void
    {
        Mail::fake();
        // Sexta-feira 02/10 + 5 dias úteis = sexta 09/10. Hoje é quarta 07/10.
        $prospect = WorkshopProspect::factory()->sent(Carbon::parse('2026-10-02 14:00:00', 'UTC'))->create();

        $this->run_($prospect);
        Mail::mailer('outreach')->assertNothingSent();

        $this->travelTo(Carbon::parse('2026-10-09 14:00:00', 'UTC'));
        $this->run_($prospect);
        Mail::mailer('outreach')->assertSentCount(1);
    }

    public function test_the_two_message_cap_holds_after_the_follow_up(): void
    {
        Mail::fake();
        $prospect = WorkshopProspect::factory()->create();

        $this->run_($prospect);
        $this->travelTo(now()->addDays(14));
        $this->run_($prospect);
        $this->travelTo(now()->addDays(30));
        $this->run_($prospect);

        Mail::mailer('outreach')->assertSentCount(2);
        $this->assertSame(WorkshopProspectStatus::FollowedUp, $prospect->fresh()->status);
    }

    public function test_a_send_exception_marks_failed_without_rethrowing_or_retrying(): void
    {
        Mail::shouldReceive('mailer')->with('outreach')->andThrow(new RuntimeException('SMTP caiu'));
        $prospect = WorkshopProspect::factory()->create();

        $job = new SendWorkshopProspectInvite($prospect->id);
        $job->handle();

        $prospect->refresh();
        $this->assertSame(WorkshopProspectStatus::Failed, $prospect->status);
        $this->assertSame('SMTP caiu', $prospect->last_error);
        $this->assertSame(1, $job->tries);
        $this->assertInstanceOf(ShouldBeUnique::class, $job);
        $this->assertSame((string) $prospect->id, $job->uniqueId());
        $this->assertSame('database', $job->connection);
    }

    public function test_a_missing_from_address_refuses_to_build_the_message(): void
    {
        config(['outreach.from.address' => null]);
        $prospect = WorkshopProspect::factory()->create();

        $this->expectException(RuntimeException::class);

        (new WorkshopProspectInviteMail($prospect))->envelope();
    }

    public function test_reply_to_falls_back_to_the_from_address(): void
    {
        config(['outreach.reply_to' => null]);
        $mail = new WorkshopProspectInviteMail(WorkshopProspect::factory()->create());

        $this->assertTrue($mail->hasReplyTo('felipe@mail.revisalog.com.br'));
    }

    public function test_display_name_falls_back_to_a_title_cased_legal_name_and_then_a_generic_name(): void
    {
        $this->assertSame('Auto Pecas Zé Ltda', WorkshopProspect::factory()->make(['trade_name' => null, 'legal_name' => 'AUTO PECAS ZÉ LTDA'])->displayName());
        $this->assertSame('sua oficina', WorkshopProspect::factory()->make(['trade_name' => null, 'legal_name' => null])->displayName());
    }

    public function test_follow_up_without_a_stored_message_id_has_no_re_and_no_threading_headers(): void
    {
        Mail::fake();
        $prospect = WorkshopProspect::factory()->sent(now()->subDays(10))->create(['trade_name' => 'Auto Zé', 'first_message_id' => null]);

        $this->run_($prospect);

        Mail::mailer('outreach')->assertSent(WorkshopProspectInviteMail::class, function (WorkshopProspectInviteMail $mail): bool {
            $this->assertSame('Auto Zé no RevisaLog', $mail->envelope()->subject);
            $this->assertSame([], $mail->headers()->references);
            $this->assertArrayNotHasKey('In-Reply-To', $mail->headers()->text);

            return true;
        });
    }
}
