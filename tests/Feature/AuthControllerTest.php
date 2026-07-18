<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_register_user_with_tenant(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'name' => 'João Silva',
            'email' => 'joao@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'user_type' => 'user',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.email', 'joao@example.com');

        $user = User::where('email', 'joao@example.com')->first();
        $this->assertNotNull($user->tenant_id);
        $this->assertDatabaseHas('tenants', [
            'id' => $user->tenant_id,
            'type' => 'individual',
        ]);
    }

    public function test_can_register_garage_with_tenant(): void
    {
        $this->postJson('/api/v1/register', [
            'name' => 'Garagem Central',
            'email' => 'garagem@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'user_type' => 'garage',
        ])->assertCreated();

        $user = User::where('email', 'garagem@example.com')->first();
        $this->assertDatabaseHas('tenants', ['id' => $user->tenant_id, 'type' => 'garage']);
        $this->assertDatabaseHas('garages', ['tenant_id' => $user->tenant_id, 'user_id' => $user->id]);
    }

    public function test_can_login(): void
    {
        $user = User::factory()->asUser()->create([
            'email' => 'login@example.com',
            'password' => bcrypt('password123'),
        ]);

        $this->postJson('/api/v1/login', [
            'email' => 'login@example.com',
            'password' => 'password123',
        ])->assertOk()
            ->assertJsonStructure(['data' => ['token', 'user']]);
    }

    public function test_can_get_authenticated_user(): void
    {
        $user = $this->actingAsApiUser();

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.tenant_id', $user->tenant_id);
    }

    public function test_can_logout(): void
    {
        $user = $this->actingAsApiUser();

        $this->postJson('/api/v1/logout')
            ->assertOk();
    }
}
