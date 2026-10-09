<?php

namespace Tests\Feature\Outreach;

use App\Enums\WorkshopProspectStatus;
use App\Mail\ContactMessageMail;
use App\Models\EmailSuppression;
use App\Models\WorkshopProspect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class OutreachPublicRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_click_records_the_first_click_and_redirects_to_the_workshop_landing_with_the_ref(): void
    {
        $prospect = WorkshopProspect::factory()->sent()->create();

        $this->get(route('outreach.click', $prospect->token))
            ->assertRedirect(route('workshops.landing', ['ref' => $prospect->token]));

        $prospect->refresh();
        $this->assertSame(WorkshopProspectStatus::Clicked, $prospect->status);
        $firstClick = $prospect->clicked_at;
        $this->assertNotNull($firstClick);

        $this->travel(1)->hour();
        $this->get(route('outreach.click', $prospect->token));
        $this->assertEquals($firstClick, $prospect->fresh()->clicked_at);
    }

    public function test_click_does_not_downgrade_other_statuses(): void
    {
        $prospect = WorkshopProspect::factory()->status(WorkshopProspectStatus::Converted)->create();

        $this->get(route('outreach.click', $prospect->token));

        $this->assertSame(WorkshopProspectStatus::Converted, $prospect->fresh()->status);
        $this->assertNotNull($prospect->fresh()->clicked_at);
    }

    public function test_unknown_token_redirects_to_contact_without_ref(): void
    {
        $this->get(route('outreach.click', 'nao-existe'))
            ->assertRedirect(route('contact.show', ['assunto' => 'partnership']));
    }

    public function test_unsubscribe_page_requires_a_valid_signature(): void
    {
        $prospect = WorkshopProspect::factory()->create();

        $this->get('/descadastrar/'.$prospect->token)->assertForbidden();
        $this->post('/descadastrar/'.$prospect->token)->assertForbidden();
        $this->assertDatabaseCount('email_suppressions', 0);
    }

    public function test_unsubscribe_get_shows_the_button_without_unsubscribing(): void
    {
        $prospect = WorkshopProspect::factory()->sent()->create();

        $this->get(URL::signedRoute('outreach.unsubscribe.show', $prospect->token))
            ->assertOk()
            ->assertSee('Não quero mais receber');

        $this->assertDatabaseCount('email_suppressions', 0);
        $this->assertSame(WorkshopProspectStatus::Sent, $prospect->fresh()->status);
    }

    public function test_unsubscribe_post_works_as_one_click_without_csrf_and_is_idempotent(): void
    {
        $prospect = WorkshopProspect::factory()->sent()->create(['email' => 'ze@x.com.br']);
        $url = URL::signedRoute('outreach.unsubscribe.show', $prospect->token);

        $this->post($url, ['List-Unsubscribe' => 'One-Click'])
            ->assertOk()
            ->assertSee('Pronto. Você não vai receber mais mensagens do RevisaLog.');
        $this->post($url)->assertOk();

        $prospect->refresh();
        $this->assertSame(WorkshopProspectStatus::Unsubscribed, $prospect->status);
        $this->assertNotNull($prospect->unsubscribed_at);
        $this->assertDatabaseCount('email_suppressions', 1);
        $this->assertDatabaseHas('email_suppressions', ['email' => 'ze@x.com.br', 'reason' => 'unsubscribed']);
        $this->assertFalse($prospect->isContactable());
    }

    public function test_unsubscribe_with_an_unknown_token_still_confirms_without_creating_anything(): void
    {
        $this->post(URL::signedRoute('outreach.unsubscribe.show', 'desconhecido'))
            ->assertOk()
            ->assertSee('Pronto.');

        $this->assertDatabaseCount('email_suppressions', 0);
    }

    public function test_contact_page_prefills_the_ref_as_a_hidden_input(): void
    {
        $this->get(route('contact.show', ['assunto' => 'partnership', 'ref' => 'abc123']))
            ->assertOk()
            ->assertSee('name="ref" value="abc123"', false);

        $this->get(route('contact.show'))->assertDontSee('name="ref"', false);
    }

    public function test_contact_with_a_ref_marks_the_prospect_as_replied_and_adds_the_origin_line(): void
    {
        Mail::fake();
        $prospect = WorkshopProspect::factory()->sent()->create(['trade_name' => 'Auto Zé', 'cnpj' => '12345678000195']);

        $this->post(route('contact.store'), [
            'name' => 'Zé', 'email' => 'ze@x.com.br', 'subject' => 'partnership', 'message' => 'Quero participar.',
            'ref' => $prospect->token,
        ])->assertRedirect(route('contact.show'));

        $prospect->refresh();
        $this->assertSame(WorkshopProspectStatus::Replied, $prospect->status);
        $this->assertNotNull($prospect->replied_at);

        Mail::assertQueued(ContactMessageMail::class, function (ContactMessageMail $mail): bool {
            $this->assertSame('convite por e-mail · Auto Zé · CNPJ 12.345.678/0001-95', $mail->origin);
            $mail->assertSeeInHtml('convite por e-mail · Auto Zé · CNPJ 12.345.678/0001-95');
            $mail->assertSeeInHtml('Origem:');

            return true;
        });
    }

    public function test_contact_with_a_ref_keeps_a_converted_prospect_converted(): void
    {
        Mail::fake();
        $prospect = WorkshopProspect::factory()->status(WorkshopProspectStatus::Converted)->create();

        $this->post(route('contact.store'), [
            'name' => 'Zé', 'email' => 'ze@x.com.br', 'subject' => 'partnership', 'message' => 'Oi.', 'ref' => $prospect->token,
        ]);

        $this->assertSame(WorkshopProspectStatus::Converted, $prospect->fresh()->status);
        $this->assertNotNull($prospect->fresh()->replied_at);
    }

    public function test_contact_without_or_with_an_unknown_ref_has_no_origin_line(): void
    {
        Mail::fake();

        $this->post(route('contact.store'), ['name' => 'A', 'email' => 'a@x.com.br', 'subject' => 'question', 'message' => 'Oi.']);
        $this->post(route('contact.store'), ['name' => 'A', 'email' => 'a@x.com.br', 'subject' => 'question', 'message' => 'Oi.', 'ref' => 'zzz']);

        Mail::assertQueued(ContactMessageMail::class, 2);
        Mail::assertQueued(ContactMessageMail::class, fn (ContactMessageMail $mail): bool => $mail->origin === null);
    }

    public function test_privacy_policy_discloses_the_prospecting(): void
    {
        $this->get(route('legal.privacy'))
            ->assertOk()
            ->assertSee('cadastro de CNPJ da Receita Federal')
            ->assertSee('no máximo duas mensagens', false)
            ->assertSee('2026-10-10');

        $this->assertSame('2026-10-10', config('legal.privacy_version'));
    }

    public function test_suppression_helper_is_case_insensitive_and_idempotent(): void
    {
        EmailSuppression::suppress('Ze@X.com.br', 'unsubscribed');
        EmailSuppression::suppress('ze@x.com.br', 'manual');

        $this->assertTrue(EmailSuppression::isSuppressed(' ZE@x.com.br '));
        $this->assertDatabaseCount('email_suppressions', 1);
        $this->assertDatabaseHas('email_suppressions', ['reason' => 'unsubscribed']);
    }
}
