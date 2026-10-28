<?php

namespace Tests\Feature\Web;

use App\Mail\AccountDeletionRequestMail;
use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Página pública de exclusão de conta (/excluir-conta), o link que o Google Play exibe na ficha
 * do app. Quem prova a posse é a caixa de e-mail: o formulário só dispara um link assinado, e a
 * exclusão acontece no POST da confirmação — nunca ao abrir o link, que varredores de e-mail
 * visitam sozinhos.
 */
class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    private function confirmationUrlFor(User $user): string
    {
        return URL::temporarySignedRoute(
            'account-deletion.confirm',
            now()->addHour(),
            ['user' => $user->id, 'hash' => sha1($user->email)],
        );
    }

    public function test_page_is_public_and_names_the_app(): void
    {
        $this->get(route('account-deletion.show'))
            ->assertOk()
            ->assertSee('Excluir conta e dados')
            ->assertSee('RevisaLog');
    }

    public function test_page_shows_the_steps_and_what_is_deleted_or_kept(): void
    {
        $response = $this->get(route('account-deletion.show'))->assertOk();

        // Caminho pelo app e caminho sem o app.
        $response->assertSee('Configurações');
        $response->assertSee('link de confirmação');

        // Exigência do Google: dizer o que sai e o que fica.
        $response->assertSee('O que é apagado');
        $response->assertSee('O que permanece');
        $response->assertSee('Conta excluída');
    }

    public function test_request_sends_a_confirmation_link_to_the_account_email(): void
    {
        Mail::fake();

        $user = User::factory()->create(['email' => 'dono@example.com']);

        $this->post(route('account-deletion.request'), ['email' => 'dono@example.com'])
            ->assertRedirect(route('account-deletion.show'))
            ->assertSessionHas('status');

        Mail::assertQueued(AccountDeletionRequestMail::class, function (AccountDeletionRequestMail $mail) use ($user): bool {
            return $mail->hasTo($user->email)
                && str_contains($mail->confirmationUrl, 'excluir-conta/confirmar/'.$user->id)
                && str_contains($mail->confirmationUrl, 'signature=');
        });
    }

    public function test_unknown_email_gets_the_same_answer_and_no_mail(): void
    {
        Mail::fake();

        $knownResponse = $this->post(route('account-deletion.request'), ['email' => 'ninguem@example.com'])
            ->assertRedirect(route('account-deletion.show'));

        $this->assertSame(
            'Se houver uma conta com esse e-mail, enviamos o link de confirmação. Ele vale por 1 hora.',
            $knownResponse->getSession()->get('status'),
        );

        Mail::assertNothingQueued();
    }

    public function test_honeypot_stops_the_robot(): void
    {
        Mail::fake();

        User::factory()->create(['email' => 'dono@example.com']);

        $this->post(route('account-deletion.request'), [
            'email' => 'dono@example.com',
            'website' => 'http://spam.example',
        ])->assertRedirect(route('account-deletion.show'));

        Mail::assertNothingQueued();
    }

    public function test_email_is_required_and_must_be_valid(): void
    {
        Mail::fake();

        $this->post(route('account-deletion.request'), ['email' => ''])
            ->assertSessionHasErrors('email');

        $this->post(route('account-deletion.request'), ['email' => 'nao-e-email'])
            ->assertSessionHasErrors('email');

        Mail::assertNothingQueued();
    }

    public function test_confirmation_needs_a_valid_signature(): void
    {
        $user = User::factory()->create();

        $this->get(route('account-deletion.confirm', ['user' => $user->id, 'hash' => sha1($user->email)]))
            ->assertForbidden();
    }

    public function test_confirmation_link_dies_when_the_email_no_longer_matches(): void
    {
        $user = User::factory()->create();
        $url = $this->confirmationUrlFor($user);

        $user->forceFill(['email' => 'outro@example.com'])->save();

        $this->get($url)->assertForbidden();
    }

    public function test_opening_the_link_does_not_delete_anything(): void
    {
        $user = User::factory()->create(['name' => 'Maria']);

        $this->get($this->confirmationUrlFor($user))
            ->assertOk()
            ->assertSee('Confirmar exclusão da conta');

        $this->assertSame('Maria', $user->fresh()->name);
    }

    public function test_confirming_erases_personal_data_but_keeps_the_vehicle_history(): void
    {
        $user = User::factory()->create([
            'name' => 'Maria',
            'phone' => '43999998888',
            'city' => 'Londrina',
        ]);
        $originalEmail = $user->email;

        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($user, $vehicle);

        $maintenance = Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
        ]);

        $this->delete($this->confirmationUrlFor($user))
            ->assertRedirect(route('account-deletion.show'))
            ->assertSessionHas('status');

        $user->refresh();

        $this->assertSame('Conta excluída', $user->name);
        $this->assertNull($user->phone);
        $this->assertNull($user->city);
        $this->assertStringEndsWith('@deleted.revisalog.invalid', $user->email);
        $this->assertDatabaseMissing('users', ['email' => $originalEmail]);
        $this->assertFalse($user->vehicles()->exists());

        // O pedido do dono do produto: some o dono, fica o histórico do carro.
        $this->assertModelExists($vehicle->fresh());
        $this->assertModelExists($maintenance->fresh());
        $this->assertSame($vehicle->id, $maintenance->fresh()->vehicle_id);
    }

    public function test_the_confirmation_mail_renders_with_the_link(): void
    {
        $mail = new AccountDeletionRequestMail(confirmationUrl: 'https://revisalog.com.br/excluir-conta/confirmar/1?signature=abc', expiresInMinutes: 60);

        $rendered = $mail->render();

        $this->assertStringContainsString('Confirme a exclusão da sua conta', $rendered);
        $this->assertStringContainsString('excluir-conta/confirmar/1', $rendered);
        $this->assertStringContainsString('60 minutos', $rendered);
        $this->assertSame('Confirme a exclusão da sua conta RevisaLog', $mail->envelope()->subject);
    }

    public function test_the_same_link_cannot_be_used_twice(): void
    {
        $user = User::factory()->create();
        $url = $this->confirmationUrlFor($user);

        $this->delete($url)->assertRedirect(route('account-deletion.show'));

        // A exclusão troca o e-mail, então o hash do link deixa de bater.
        $this->delete($url)->assertForbidden();
    }

    /**
     * Teto por aparelho (IP): 5 pedidos por minuto, mesmo que cada um aponte para um e-mail
     * diferente — aqui o balde por destinatário nunca é o que barra.
     */
    public function test_sixth_request_from_the_same_ip_in_a_minute_returns_429(): void
    {
        Mail::fake();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->requestDeletion('203.0.113.9', "pessoa{$attempt}@example.com")
                ->assertRedirect(route('account-deletion.show'));
        }

        $this->requestDeletion('203.0.113.9', 'pessoa6@example.com')->assertStatus(429);

        Mail::assertNothingQueued();
    }

    /**
     * O defeito que isto cobre: com teto só por IP, trocar de IP mandava e-mails sem limite para a
     * caixa de outra pessoa. O balde por destinatário soma todos os IPs, então um IP novo a cada
     * pedido não aumenta quantos e-mails um endereço recebe por dia.
     */
    public function test_destination_email_is_capped_per_day_across_every_ip(): void
    {
        Mail::fake();

        User::factory()->create(['email' => 'alvo@example.com']);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->requestDeletion("198.51.100.{$attempt}", 'alvo@example.com')
                ->assertRedirect(route('account-deletion.show'));
        }

        $this->requestDeletion('198.51.100.250', 'alvo@example.com')->assertStatus(429);

        Mail::assertQueued(AccountDeletionRequestMail::class, 5);
    }

    /** Trocar maiúsculas por minúsculas não abre um balde novo para a mesma caixa. */
    public function test_the_daily_cap_ignores_the_case_of_the_email(): void
    {
        Mail::fake();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->requestDeletion("198.51.100.{$attempt}", 'ALVO@EXAMPLE.COM')
                ->assertRedirect(route('account-deletion.show'));
        }

        $this->requestDeletion('198.51.100.250', 'alvo@example.com')->assertStatus(429);
    }

    /**
     * Sem oráculo: ter ou não conta não muda nada que o visitante possa ver. Nem a resposta do
     * pedido, nem o momento em que o teto por destinatário passa a recusar — porque a chave do
     * balde é o e-mail digitado, não a conta encontrada. As duas sequências usam os mesmos IPs na
     * mesma ordem, então a única diferença entre elas é existir o cadastro.
     */
    public function test_existing_and_unknown_emails_are_indistinguishable(): void
    {
        Mail::fake();

        User::factory()->create(['email' => 'cadastrado@example.com']);

        $registered = $this->attemptsUntilTheCap('cadastrado@example.com');
        $this->flushSession();
        $unknown = $this->attemptsUntilTheCap('ninguem@example.com');

        $this->assertSame($registered, $unknown);
        $this->assertSame([302, 302, 302, 302, 302, 429], array_column($registered, 'status'));

        // E só o endereço cadastrado recebeu e-mail, sem que isso apareça na resposta.
        Mail::assertQueued(AccountDeletionRequestMail::class, 5);
    }

    /**
     * /contato e /excluir-conta dividiam o balde 'contact': mensagens ao suporte gastavam a cota
     * de quem precisava do link de exclusão, e vice-versa. Agora cada rota tem o seu.
     */
    public function test_contact_messages_do_not_spend_the_account_deletion_quota(): void
    {
        Mail::fake();

        $contactPayload = [
            'name' => 'Ana Silva',
            'email' => 'ana@example.com',
            'subject' => 'support',
            'message' => 'Preciso de ajuda com o relatório do veículo.',
        ];

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post(route('contact.store'), $contactPayload)->assertRedirect(route('contact.show'));
        }

        $this->post(route('contact.store'), $contactPayload)->assertStatus(429);

        $this->post(route('account-deletion.request'), ['email' => 'dono@example.com'])
            ->assertRedirect(route('account-deletion.show'));
    }

    /**
     * Tudo o que o visitante consegue observar em cada tentativa até o teto por destinatário
     * recusar — status, destino do redirecionamento e corpo da resposta —, sempre com um IP
     * próprio para que nenhum balde por IP interfira.
     *
     * @return list<array{status: int, location: string|null, body: string}>
     */
    private function attemptsUntilTheCap(string $email): array
    {
        $attempts = [];

        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $response = $this->requestDeletion("192.0.2.{$attempt}", $email);

            $attempts[] = [
                'status' => $response->getStatusCode(),
                'location' => $response->headers->get('Location'),
                'body' => $response->getContent(),
            ];
        }

        return $attempts;
    }

    private function requestDeletion(string $ip, string $email): TestResponse
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->post(route('account-deletion.request'), ['email' => $email]);
    }
}
