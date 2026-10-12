<?php

namespace Tests\Feature\Api;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MobileOAuthRedirectTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function providers(): array
    {
        return ['google' => ['google'], 'facebook' => ['facebook'], 'twitter' => ['twitter']];
    }

    #[DataProvider('providers')]
    public function test_browser_oauth_is_off_by_default_and_answers_in_the_format_the_app_understands(string $provider): void
    {
        config([
            'services.google.client_id' => 'id',
            'services.google.client_secret' => 'secret',
            'services.google.redirect' => 'http://localhost:8080/api/v1/auth/google/callback',
        ]);

        $this->getJson("/api/v1/auth/{$provider}/redirect")
            ->assertOk()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'OAUTH_NOT_CONFIGURED')
            ->assertJsonMissingPath('data.redirect_url');
    }

    public function test_when_enabled_it_still_refuses_a_provider_without_credentials(): void
    {
        config(['services.mobile_oauth_enabled' => true, 'services.facebook.client_id' => null]);

        $this->getJson('/api/v1/auth/facebook/redirect')
            ->assertStatus(500)
            ->assertJsonPath('success', false);
    }

    public function test_an_unknown_provider_is_rejected(): void
    {
        $this->getJson('/api/v1/auth/github/redirect')->assertStatus(400);
    }
}
