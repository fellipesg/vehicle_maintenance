<?php

namespace Tests\Feature;

use App\Models\Maintenance;
use App\Models\User;
use App\Models\UserFcmToken;
use App\Models\Vehicle;
use App\Support\AppStorage;
use App\Support\SanctumMobileToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileAccountDeletionTest extends TestCase
{
    use RefreshDatabase;

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

        $response = $this->deleteJson('/api/v1/me');

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

        $this->deleteJson('/api/v1/me')->assertOk();

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

        $this->deleteJson('/api/v1/me')->assertOk();

        $user->refresh();

        $this->assertDatabaseMissing('users', ['email' => $email]);
        $this->assertFalse(Hash::check('password', $user->password));
    }
}
