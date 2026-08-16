<?php

namespace Tests\Feature;

use App\Support\AppStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_can_update_profile_fields(): void
    {
        $user = $this->actingAsApiUser();

        $response = $this->putJson('/api/v1/me', [
            'name' => 'Updated Name',
            'phone' => '43999998888',
            'postal_code' => '86000000',
            'street' => 'Rua Teste',
            'number' => '123',
            'complement' => 'Apto 1',
            'city' => 'Londrina',
            'state' => 'PR',
            'country' => 'Brasil',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.phone', '43999998888')
            ->assertJsonPath('data.city', 'Londrina');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'phone' => '43999998888',
        ]);
    }

    public function test_cannot_update_email_via_profile_endpoint(): void
    {
        $user = $this->actingAsApiUser();

        $response = $this->putJson('/api/v1/me', [
            'email' => 'hacked@example.com',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'The email field cannot be updated here.');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => $user->email,
        ]);
    }

    public function test_cannot_update_forbidden_admin_fields(): void
    {
        $user = $this->actingAsApiUser();

        $response = $this->putJson('/api/v1/me', [
            'user_type' => 'garage',
            'is_admin' => true,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['user_type', 'is_admin']);

        $user->refresh();
        $this->assertTrue($user->isUser());
        $this->assertFalse($user->is_admin);
    }

    public function test_can_upload_avatar(): void
    {
        $user = $this->actingAsApiUser();

        $file = UploadedFile::fake()->image('avatar.jpg');

        $response = $this->post('/api/v1/me/avatar', [
            'avatar' => $file,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $user->refresh();
        $this->assertNotNull($user->avatar);
        $this->assertStringStartsWith('avatars/', $user->avatar);
        Storage::disk('public')->assertExists($user->avatar);

        $response->assertJsonPath('data.avatar_url', AppStorage::url($user->avatar));
    }

    public function test_me_includes_avatar_url_for_remote_avatar(): void
    {
        $user = $this->actingAsApiUser();
        $user->update(['avatar' => 'https://example.com/oauth-avatar.jpg']);

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.avatar_url', 'https://example.com/oauth-avatar.jpg');
    }
}
