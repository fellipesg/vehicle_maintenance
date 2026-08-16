<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserFcmToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserFcmTokenControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_register_fcm_token(): void
    {
        $user = $this->actingAsApiUser();

        $this->postJson('/api/v1/fcm-tokens', [
            'token' => 'device-token-123',
            'device_type' => 'android',
        ])->assertCreated()
            ->assertJsonPath('data.token', 'device-token-123');

        $this->assertDatabaseHas('user_fcm_tokens', [
            'user_id' => $user->id,
            'token' => 'device-token-123',
        ]);
    }

    public function test_can_list_own_fcm_tokens(): void
    {
        $user = $this->actingAsApiUser();
        UserFcmToken::create([
            'user_id' => $user->id,
            'token' => 'token-a',
            'device_type' => 'android',
        ]);

        $this->getJson('/api/v1/fcm-tokens')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_can_delete_own_fcm_token(): void
    {
        $user = $this->actingAsApiUser();
        UserFcmToken::create([
            'user_id' => $user->id,
            'token' => 'token-to-delete',
            'device_type' => 'android',
        ]);

        $this->deleteJson('/api/v1/fcm-tokens/token-to-delete')
            ->assertOk();

        $this->assertDatabaseMissing('user_fcm_tokens', [
            'token' => 'token-to-delete',
        ]);
    }

    public function test_cannot_delete_fcm_token_of_another_user(): void
    {
        $this->actingAsApiUser();
        $otherUser = User::factory()->asUser()->create();
        UserFcmToken::create([
            'user_id' => $otherUser->id,
            'token' => 'other-user-token',
            'device_type' => 'ios',
        ]);

        $this->deleteJson('/api/v1/fcm-tokens/other-user-token')
            ->assertForbidden();

        $this->assertDatabaseHas('user_fcm_tokens', [
            'token' => 'other-user-token',
        ]);
    }

    public function test_cannot_hijack_fcm_token_registered_to_another_user(): void
    {
        $this->actingAsApiUser();
        $otherUser = User::factory()->asUser()->create();
        UserFcmToken::create([
            'user_id' => $otherUser->id,
            'token' => 'shared-device-token',
            'device_type' => 'android',
        ]);

        $this->postJson('/api/v1/fcm-tokens', [
            'token' => 'shared-device-token',
            'device_type' => 'android',
        ])->assertForbidden();

        $this->assertDatabaseHas('user_fcm_tokens', [
            'token' => 'shared-device-token',
            'user_id' => $otherUser->id,
        ]);
    }
}
