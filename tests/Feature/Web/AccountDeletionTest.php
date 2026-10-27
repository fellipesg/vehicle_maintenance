<?php

namespace Tests\Feature\Web;

use App\Mail\AccountDeletionRequestMail;
use App\Models\Maintenance;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehiclePdfExport;
use App\Notifications\ConsignmentOwnerAccountDeletedNotification;
use App\Support\AppStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
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
     * O link pode chegar no mesmo navegador em que a pessoa está logada. Sem o logout explícito,
     * a sessão sobrevive à exclusão: o framework regrava a linha de `sessions` ao fim da resposta
     * e o AuthenticateWebSession guarda o hash da senha nova logo em seguida.
     */
    public function test_the_signed_link_signs_out_the_browser_that_used_it(): void
    {
        User::factory()->asUser()->create(['email' => 'dono@example.com']);

        $this->post(route('login.submit', 'usuario'), ['email' => 'dono@example.com', 'password' => 'password'])
            ->assertRedirect(route('user.dashboard'));

        $user = User::query()->where('email', 'dono@example.com')->firstOrFail();

        $this->delete($this->confirmationUrlFor($user))
            ->assertRedirect(route('account-deletion.show'));

        $this->assertGuest();

        $sessionAfterDeletion = session()->all();
        $this->leaveDevice();

        $this->withSession($sessionAfterDeletion)
            ->get(route('user.dashboard'))
            ->assertRedirect(route('login.usuario'));

        $this->assertGuest();
    }

    /**
     * Outro aparelho da mesma conta também perde a autenticação: a sessão está presa ao hash da
     * senha, que a exclusão troca.
     */
    public function test_the_other_devices_stop_being_authenticated(): void
    {
        User::factory()->asUser()->create(['email' => 'dono@example.com']);

        $this->post(route('login.submit', 'usuario'), ['email' => 'dono@example.com', 'password' => 'password'])
            ->assertRedirect(route('user.dashboard'));

        $otherDevice = session()->all();
        $this->leaveDevice();

        $user = User::query()->where('email', 'dono@example.com')->firstOrFail();

        $this->delete($this->confirmationUrlFor($user))
            ->assertRedirect(route('account-deletion.show'));
        $this->leaveDevice();

        $this->withSession($otherDevice)
            ->get(route('user.dashboard'))
            ->assertRedirect(route('login.usuario'));

        $this->assertGuest();
    }

    public function test_confirming_erases_notifications_and_generated_pdf_exports(): void
    {
        Storage::fake(AppStorage::diskName());

        $user = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($user, $vehicle);
        $user->refresh();

        $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => ConsignmentOwnerAccountDeletedNotification::class,
            'data' => ['vehicle' => 'Gol ABC1D23'],
        ]);

        $exportPath = 'exports/vehicle-pdfs/'.$user->id.'-historico.pdf';
        Storage::disk(AppStorage::diskName())->put($exportPath, '%PDF-1.4');

        $export = VehiclePdfExport::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'status' => VehiclePdfExport::STATUS_COMPLETED,
            'file_path' => $exportPath,
            'expires_at' => now()->addDay(),
        ]);

        $this->delete($this->confirmationUrlFor($user))
            ->assertRedirect(route('account-deletion.show'));

        $this->assertSame(0, $user->fresh()->notifications()->count());
        $this->assertDatabaseMissing('vehicle_pdf_exports', ['id' => $export->id]);
        Storage::disk(AppStorage::diskName())->assertMissing($exportPath);
    }

    /**
     * Notas fiscais e fotos continuarem é decisão, não esquecimento: o histórico é do chassi. A
     * página pública e a política de privacidade têm de dizer isso, e é por elas que o Google Play
     * e o titular do dado conferem o que a exclusão faz.
     */
    public function test_the_page_and_the_privacy_policy_say_what_deletion_keeps_and_erases(): void
    {
        $this->get(route('account-deletion.show'))
            ->assertOk()
            ->assertSee('As notificações que você recebeu na plataforma.')
            ->assertSee('Os relatórios em PDF que você gerou, registro e arquivo.')
            ->assertSee('Notas fiscais e fotos das manutenções também ficam');

        $this->get(route('legal.privacy'))
            ->assertOk()
            ->assertSee('as suas sessões abertas em qualquer aparelho')
            ->assertSee('os relatórios em PDF que você gerou (registro e arquivo)')
            ->assertSee('inclusive as notas fiscais e as fotos do serviço')
            ->assertSee('pede a sua senha atual');

        $this->assertSame('2026-10-27', config('legal.privacy_version'));
    }

    /**
     * Limpa guarda e sessão, como se a próxima requisição viesse de outro navegador.
     */
    private function leaveDevice(): void
    {
        $this->app['auth']->forgetGuards();
        $this->flushSession();
    }
}
