<?php

namespace Tests\Feature;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\UserFcmToken;
use App\Models\Vehicle;
use App\Models\VehiclePdfExport;
use App\Notifications\ConsignmentOwnerAccountDeletedNotification;
use App\Support\AppStorage;
use App\Support\SanctumMobileToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * DELETE /api/v1/me. A exclusão é irreversível, então o token sozinho não basta: vale a mesma
 * reautenticação da tela `/conta` (senha atual num Form Request).
 */
class ProfileAccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var string A senha que a UserFactory grava.
     */
    private const PASSWORD = 'password';

    public function test_guest_cannot_delete_account(): void
    {
        $this->deleteJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_user_can_delete_account_and_personal_data(): void
    {
        Storage::fake(AppStorage::diskName());

        $user = $this->actingAsApiUser();
        $originalEmail = $user->email;
        $originalPassword = $user->password;

        $file = UploadedFile::fake()->image('avatar.jpg');
        $avatarPath = $file->storeAs('avatars', $user->id.'_avatar.jpg', AppStorage::diskName());
        $user->update([
            'phone' => '43999998888',
            'document' => '12345678901',
            'city' => 'Londrina',
            'avatar' => $avatarPath,
        ]);

        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($user, $vehicle);

        $maintenance = Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
        ]);

        UserFcmToken::query()->create([
            'user_id' => $user->id,
            'token' => 'fcm-token-'.$user->id,
            'device_type' => 'ios',
        ]);

        $user->createToken(SanctumMobileToken::TOKEN_NAME, SanctumMobileToken::ABILITIES);

        $response = $this->deleteJson('/api/v1/me', ['current_password' => self::PASSWORD]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Account deleted successfully');

        $user->refresh();

        $this->assertSame('Conta excluída', $user->name);
        $this->assertNotSame($originalEmail, $user->email);
        $this->assertStringEndsWith('@deleted.revisalog.invalid', $user->email);
        $this->assertNotSame($originalPassword, $user->password);
        $this->assertNull($user->phone);
        $this->assertNull($user->document);
        $this->assertNull($user->city);
        $this->assertNull($user->avatar);
        $this->assertFalse($user->is_admin);
        $this->assertFalse($user->subscription_active);
        $this->assertFalse($user->vehicles()->exists());
        $this->assertSame(0, $user->tokens()->count());
        $this->assertDatabaseMissing('user_fcm_tokens', ['user_id' => $user->id]);
        Storage::disk(AppStorage::diskName())->assertMissing($avatarPath);

        $this->assertModelExists($vehicle);
        $this->assertModelExists($maintenance->fresh());
        $this->assertDatabaseMissing('users', ['email' => $originalEmail]);
        $this->assertFalse(Hash::check('password', $user->password));
    }

    public function test_deleting_account_keeps_history_registered_by_other_users(): void
    {
        $owner = $this->actingAsApiUser();
        $other = User::factory()->asUser()->create();
        $otherEmail = $other->email;
        $vehicle = Vehicle::factory()->create();

        $this->attachVehicleToUser($owner, $vehicle);
        $this->attachVehicleToUser($other, $vehicle);

        $ownMaintenance = Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $owner->id,
            'tenant_id' => $owner->tenant_id,
        ]);
        $otherMaintenance = Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $other->id,
            'tenant_id' => $other->tenant_id,
        ]);

        $this->deleteJson('/api/v1/me', ['current_password' => self::PASSWORD])->assertOk();

        $this->assertModelExists($vehicle);
        $this->assertModelExists($ownMaintenance->fresh());
        $this->assertModelExists($otherMaintenance->fresh());
        $this->assertSame($other->id, $otherMaintenance->fresh()->user_id);

        $owner->refresh();
        $other->refresh();

        $this->assertTrue($other->vehicles()->whereKey($vehicle->id)->exists());
        $this->assertFalse($owner->vehicles()->exists());
        $this->assertSame('Conta excluída', $owner->name);
        $this->assertNotSame('Conta excluída', $other->name);
        $this->assertSame($otherEmail, $other->email);
    }

    public function test_deleted_account_cannot_authenticate_with_previous_password(): void
    {
        $user = $this->actingAsApiUser();
        $email = $user->email;

        $this->deleteJson('/api/v1/me', ['current_password' => self::PASSWORD])->assertOk();

        $user->refresh();

        $this->assertDatabaseMissing('users', ['email' => $email]);
        $this->assertFalse(Hash::check('password', $user->password));
    }

    public function test_account_is_not_deleted_without_the_current_password(): void
    {
        $user = $this->actingAsApiUser();
        $name = $user->name;
        $token = $user->createToken(SanctumMobileToken::TOKEN_NAME, SanctumMobileToken::ABILITIES);

        $this->deleteJson('/api/v1/me')
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.current_password.0', 'Informe sua senha para excluir a conta.');

        $this->assertSame($name, $user->fresh()->name);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $token->accessToken->getKey()]);
    }

    public function test_account_is_not_deleted_with_the_wrong_password(): void
    {
        $user = $this->actingAsApiUser();
        $email = $user->email;

        $this->deleteJson('/api/v1/me', ['current_password' => 'senha-errada'])
            ->assertStatus(422)
            ->assertJsonPath('errors.current_password.0', 'A senha atual não confere.');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => $email]);
        $this->assertSame($email, $user->fresh()->email);
    }

    /**
     * Conta criada pelo provedor social nunca escolheu senha: a mensagem precisa apontar o
     * caminho que funciona para ela, o link assinado de /excluir-conta.
     */
    public function test_social_account_is_pointed_to_the_signed_link_flow(): void
    {
        $user = User::factory()->asUser()->create([
            'provider' => 'google',
            'provider_id' => 'google-123',
            'password' => Hash::make(Str::random(32)),
        ]);
        $this->actingAsApiUser($user);

        $response = $this->deleteJson('/api/v1/me', ['current_password' => self::PASSWORD])
            ->assertStatus(422);

        $message = $response->json('errors.current_password.0');

        $this->assertStringContainsString('Google', $message);
        $this->assertStringContainsString(route('account-deletion.show'), $message);
        $this->assertSame($user->email, $user->fresh()->email);
    }

    /**
     * Notificações do canal `database` citam nome, veículo e placa; o relatório em PDF traz o
     * histórico inteiro e só venceria no `vehicle-pdf-exports:cleanup`, até 24 h depois.
     */
    public function test_deleting_the_account_erases_notifications_and_generated_pdf_exports(): void
    {
        Storage::fake(AppStorage::diskName());

        $user = $this->actingAsApiUser();
        $other = User::factory()->asUser()->create();
        $vehicle = Vehicle::factory()->create();
        $this->attachVehicleToUser($user, $vehicle);

        $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => ConsignmentOwnerAccountDeletedNotification::class,
            'data' => ['vehicle' => 'Gol ABC1D23'],
        ]);
        $other->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => ConsignmentOwnerAccountDeletedNotification::class,
            'data' => ['vehicle' => 'Onix XYZ4E56'],
        ]);

        $exportPath = 'exports/vehicle-pdfs/'.$user->id.'-historico.pdf';
        Storage::disk(AppStorage::diskName())->put($exportPath, '%PDF-1.4');

        $export = VehiclePdfExport::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'status' => VehiclePdfExport::STATUS_COMPLETED,
            'file_path' => $exportPath,
            'filename' => 'historico.pdf',
            'expires_at' => now()->addDay(),
        ]);

        $otherExportPath = 'exports/vehicle-pdfs/'.$other->id.'-historico.pdf';
        Storage::disk(AppStorage::diskName())->put($otherExportPath, '%PDF-1.4');

        $otherExport = VehiclePdfExport::factory()->create([
            'vehicle_id' => $vehicle->id,
            'user_id' => $other->id,
            'status' => VehiclePdfExport::STATUS_COMPLETED,
            'file_path' => $otherExportPath,
            'expires_at' => now()->addDay(),
        ]);

        $this->deleteJson('/api/v1/me', ['current_password' => self::PASSWORD])->assertOk();

        $this->assertSame(0, $user->fresh()->notifications()->count());
        $this->assertDatabaseMissing('vehicle_pdf_exports', ['id' => $export->id]);
        Storage::disk(AppStorage::diskName())->assertMissing($exportPath);

        // Só os dados de quem excluiu: a conta ao lado continua inteira.
        $this->assertSame(1, $other->fresh()->notifications()->count());
        $this->assertDatabaseHas('vehicle_pdf_exports', ['id' => $otherExport->id]);
        Storage::disk(AppStorage::diskName())->assertExists($otherExportPath);
    }

    /**
     * O driver de sessão é `database`: a exclusão apaga as linhas, e o navegador que já estava
     * logado perde a autenticação.
     */
    public function test_deleting_the_account_drops_the_web_sessions_of_every_device(): void
    {
        config(['session.driver' => 'database']);

        $user = $this->actingAsApiUser();
        $other = User::factory()->asUser()->create();

        foreach ([$user, $other] as $index => $owner) {
            DB::table('sessions')->insert([
                'id' => 'session-'.$index,
                'user_id' => $owner->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'phpunit',
                'payload' => base64_encode(serialize([])),
                'last_activity' => now()->getTimestamp(),
            ]);
        }

        $this->deleteJson('/api/v1/me', ['current_password' => self::PASSWORD])->assertOk();

        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
        $this->assertDatabaseHas('sessions', ['user_id' => $other->id]);
    }
}
