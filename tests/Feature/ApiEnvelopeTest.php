<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiEnvelopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_api_request_returns_unified_json_shape(): void
    {
        $this->getJson('/api/v1/me')
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthenticated.')
            ->assertJsonMissing(['exception', 'trace']);
    }

    public function test_ability_middleware_denies_token_without_required_ability(): void
    {
        $user = User::factory()->asUser()->create();
        Sanctum::actingAs($user, ['profile:read']);

        $this->putJson('/api/v1/me', ['name' => 'Updated Name'])
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_paginated_list_includes_meta_and_links(): void
    {
        $user = $this->actingAsApiUser();

        $this->getJson('/api/v1/my-vehicles')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data',
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
                'links' => ['first', 'last', 'prev', 'next'],
            ]);
    }
}
