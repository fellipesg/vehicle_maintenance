<?php

namespace Tests\Feature\WorkshopRecords;

use App\Mail\CustomerInviteMail;
use App\Models\EmailSuppression;
use App\Models\MaintenanceInvite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\Feature\Concerns\BuildsOwnerlessRecords;
use Tests\TestCase;

/**
 * "Avisar o cliente": e-mail uma vez por OS, supressão, limite diário, WhatsApp sem placa e a
 * página pública /convite/{token}.
 */
class CustomerInviteTest extends TestCase
{
    use BuildsOwnerlessRecords;
    use RefreshDatabase;

    public function test_email_invite_is_sent_once_and_stores_only_the_hash(): void
    {
        Mail::fake();
        $workshop = $this->workshopAccount();
        $record = $this->ownerlessRecord($workshop, $this->ownerlessVehicle());
        $this->actingAsApiUser($workshop);

        $this->postJson("/api/v1/maintenances/{$record->id}/invites/email", ['email' => 'Cliente@Exemplo.com'])
            ->assertStatus(202)
            ->assertJsonStructure(['data' => ['email_invited_at']]);

        Mail::assertQueued(CustomerInviteMail::class, 1);
        $invite = MaintenanceInvite::query()->firstOrFail();
        $this->assertSame(hash('sha256', 'cliente@exemplo.com'), $invite->email_hash);
        $this->assertStringNotContainsString('exemplo', json_encode($invite->getAttributes()));

        $this->postJson("/api/v1/maintenances/{$record->id}/invites/email", ['email' => 'outro@exemplo.com'])->assertStatus(409);
        Mail::assertQueued(CustomerInviteMail::class, 1);
    }

    public function test_email_content_has_no_plate_chassis_and_carries_opt_out(): void
    {
        $workshop = $this->workshopAccount();
        $vehicle = $this->ownerlessVehicle(['license_plate' => 'ABC1D23']);
        $record = $this->ownerlessRecord($workshop, $vehicle);
        $this->actingAsApiUser($workshop);
        Mail::fake();
        $this->postJson("/api/v1/maintenances/{$record->id}/invites/email", ['email' => 'c@exemplo.com'])->assertStatus(202);

        Mail::assertQueued(CustomerInviteMail::class, function (CustomerInviteMail $mail): bool {
            $html = $mail->render();

            return str_contains($html, 'Fiat Argo 2021')
                && str_contains($html, 'Você recebeu esta mensagem porque a oficina')
                && str_contains($html, 'registrou um serviço no seu carro')
                && str_contains($html, '/convite/descadastrar')
                && ! str_contains($html, 'ABC1D23')
                && ! str_contains($html, self::OWNERLESS_CHASSIS)
                && $mail->hasTo('c@exemplo.com');
        });
    }

    public function test_suppressed_address_is_refused_with_a_generic_message(): void
    {
        Mail::fake();
        EmailSuppression::suppress('quieto@exemplo.com', EmailSuppression::REASON_UNSUBSCRIBED);
        $workshop = $this->workshopAccount();
        $record = $this->ownerlessRecord($workshop, $this->ownerlessVehicle());
        $this->actingAsApiUser($workshop);

        $this->postJson("/api/v1/maintenances/{$record->id}/invites/email", ['email' => 'QUIETO@exemplo.com'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Não foi possível enviar para este e-mail.');
        Mail::assertNothingQueued();
    }

    public function test_daily_limit_per_workshop_returns_429(): void
    {
        Mail::fake();
        config(['maintenance.invite_email_daily_limit' => 2]);
        $workshop = $this->workshopAccount();
        $vehicle = $this->ownerlessVehicle();
        $this->actingAsApiUser($workshop);

        foreach (['a', 'b'] as $name) {
            $record = $this->ownerlessRecord($workshop, $vehicle);
            $this->postJson("/api/v1/maintenances/{$record->id}/invites/email", ['email' => "{$name}@exemplo.com"])->assertStatus(202);
        }

        $third = $this->ownerlessRecord($workshop, $vehicle);
        $this->postJson("/api/v1/maintenances/{$third->id}/invites/email", ['email' => 'c@exemplo.com'])->assertStatus(429);
        Mail::assertQueued(CustomerInviteMail::class, 2);
    }

    public function test_whatsapp_url_has_the_text_without_plate_and_stores_no_phone(): void
    {
        $workshop = $this->workshopAccount();
        $vehicle = $this->ownerlessVehicle(['license_plate' => 'ABC1D23']);
        $record = $this->ownerlessRecord($workshop, $vehicle);
        $this->actingAsApiUser($workshop);

        $response = $this->postJson("/api/v1/maintenances/{$record->id}/invites/whatsapp", ['phone' => '(11) 91234-5678'])
            ->assertOk()
            ->assertJsonStructure(['data' => ['url', 'whatsapp_invited_at']]);

        $url = $response->json('data.url');
        $this->assertStringStartsWith('https://wa.me/5511912345678?text=', $url);
        $text = urldecode(substr($url, strlen('https://wa.me/5511912345678?text=')));
        $this->assertStringContainsString('Fiat Argo 2021', $text);
        $this->assertStringContainsString($workshop->workshop->name, $text);
        $this->assertStringContainsString(route('invites.show', MaintenanceInvite::query()->firstOrFail()->token), $text);
        $this->assertStringNotContainsString('ABC1D23', $text);
        $this->assertStringNotContainsString(self::OWNERLESS_CHASSIS, $text);
        $this->assertNotNull(MaintenanceInvite::query()->firstOrFail()->whatsapp_invited_at);
        $this->assertStringNotContainsString('91234', json_encode(MaintenanceInvite::query()->firstOrFail()->getAttributes()));

        $this->postJson("/api/v1/maintenances/{$record->id}/invites/whatsapp", ['phone' => '123'])->assertStatus(422);
    }

    public function test_only_the_creating_workshop_can_invite(): void
    {
        Mail::fake();
        $record = $this->ownerlessRecord($this->workshopAccount(), $this->ownerlessVehicle());

        $this->actingAsApiUser($this->workshopAccount());
        $this->postJson("/api/v1/maintenances/{$record->id}/invites/email", ['email' => 'c@exemplo.com'])->assertForbidden();
        $this->postJson("/api/v1/maintenances/{$record->id}/invites/whatsapp", ['phone' => '11912345678'])->assertForbidden();

        $this->actingAsApiUser();
        $this->postJson("/api/v1/maintenances/{$record->id}/invites/email", ['email' => 'c@exemplo.com'])->assertForbidden();
        Mail::assertNothingQueued();
    }

    public function test_no_invite_once_the_vehicle_has_an_owner(): void
    {
        $workshop = $this->workshopAccount();
        $vehicle = $this->ownerlessVehicle();
        $record = $this->ownerlessRecord($workshop, $vehicle);
        $this->ownerOf($vehicle);
        $this->actingAsApiUser($workshop);

        $this->postJson("/api/v1/maintenances/{$record->id}/invites/whatsapp", ['phone' => '11912345678'])->assertForbidden();
    }

    public function test_web_invite_forms_work_for_the_workshop(): void
    {
        Mail::fake();
        $workshop = $this->workshopAccount();
        $record = $this->ownerlessRecord($workshop, $this->ownerlessVehicle());

        $this->actingAs($workshop)
            ->post(route('workshop.maintenances.invite.email', $record), ['email' => 'web@exemplo.com'])
            ->assertSessionHas('success');
        Mail::assertQueued(CustomerInviteMail::class, 1);

        $this->actingAs($workshop)
            ->post(route('workshop.maintenances.invite.whatsapp', $record), ['phone' => '11987654321'])
            ->assertRedirectContains('https://wa.me/5511987654321');
    }

    public function test_invite_page_is_public_minimal_and_noindex(): void
    {
        $workshop = $this->workshopAccount();
        $vehicle = $this->ownerlessVehicle(['license_plate' => 'ABC1D23']);
        $record = $this->ownerlessRecord($workshop, $vehicle);
        $invite = MaintenanceInvite::factory()->create(['maintenance_id' => $record->id, 'workshop_id' => $workshop->workshop->id]);

        $this->get(route('invites.show', $invite->token))
            ->assertOk()
            ->assertSee('noindex', false)
            ->assertSee($workshop->workshop->name)
            ->assertSee('Fiat Argo 2021')
            ->assertSee($record->maintenance_date->format('d/m/Y'))
            ->assertSee('Criar conta grátis')
            ->assertDontSee('ABC1D23')
            ->assertDontSee(self::OWNERLESS_CHASSIS)
            ->assertDontSee('CPF');

        $this->get(route('invites.show', 'token-que-nao-existe'))->assertNotFound();
    }

    public function test_opt_out_link_writes_to_the_suppression_list(): void
    {
        $url = URL::signedRoute('invites.unsubscribe.show', ['payload' => \Illuminate\Support\Facades\Crypt::encryptString('sair@exemplo.com')]);

        $this->get($url)->assertOk()->assertSee('Não quero mais receber');
        $this->assertFalse(EmailSuppression::isSuppressed('sair@exemplo.com'));

        $this->post($url)->assertOk()->assertSee('Pronto');
        $this->assertTrue(EmailSuppression::isSuppressed('sair@exemplo.com'));

        $this->get(route('invites.unsubscribe.show', ['payload' => 'x']))->assertForbidden();
    }
}
