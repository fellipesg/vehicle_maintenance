<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class CorsConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_testing_allows_wildcard_origin_when_env_not_set(): void
    {
        $this->assertSame(['*'], config('cors.allowed_origins'));
        $this->assertFalse(config('cors.supports_credentials'));

        $response = $this->getJson('/api/v1/workshops', [
            'Origin' => 'http://localhost:3000',
        ]);

        $response->assertOk();
        $this->assertSame('*', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_explicit_allowed_origins_disable_wildcard_credentials(): void
    {
        Config::set('cors.allowed_origins', ['https://app.example.com']);
        Config::set('cors.supports_credentials', true);

        $this->assertSame(['https://app.example.com'], config('cors.allowed_origins'));
        $this->assertTrue(config('cors.supports_credentials'));
    }

    public function test_production_defaults_to_app_url_only_without_wildcard_patterns(): void
    {
        $this->withEnv([
            'APP_ENV' => 'production',
            'APP_URL' => 'https://revisalog.com.br/',
            'CORS_ALLOWED_ORIGINS' => null,
            'CORS_ALLOWED_ORIGIN_PATTERNS' => null,
        ], function (): void {
            $this->assertSame(['https://revisalog.com.br'], cors_allowed_origins());
            $this->assertSame([], cors_allowed_origin_patterns());
        });
    }

    public function test_production_uses_explicit_origins_and_patterns_from_env(): void
    {
        $this->withEnv([
            'APP_ENV' => 'production',
            'APP_URL' => 'https://revisalog.com.br',
            'CORS_ALLOWED_ORIGINS' => 'https://revisalog.com.br, https://app.example.com',
            'CORS_ALLOWED_ORIGIN_PATTERNS' => '#^https://preview-[a-z0-9]+\.example\.com$#',
        ], function (): void {
            $this->assertSame(['https://revisalog.com.br', 'https://app.example.com'], cors_allowed_origins());
            $this->assertSame(['#^https://preview-[a-z0-9]+\.example\.com$#'], cors_allowed_origin_patterns());
        });
    }

    public function test_foreign_laravel_cloud_origin_is_not_granted_credentialed_access(): void
    {
        Config::set('cors.allowed_origins', ['https://revisalog.com.br']);
        Config::set('cors.allowed_origins_patterns', []);
        Config::set('cors.supports_credentials', true);

        $response = $this->getJson('/api/v1/workshops', [
            'Origin' => 'https://attacker-app.laravel.cloud',
        ]);

        $response->assertOk();
        $this->assertNotSame(
            'https://attacker-app.laravel.cloud',
            $response->headers->get('Access-Control-Allow-Origin'),
        );

        $allowed = $this->getJson('/api/v1/workshops', [
            'Origin' => 'https://revisalog.com.br',
        ]);

        $this->assertSame('https://revisalog.com.br', $allowed->headers->get('Access-Control-Allow-Origin'));
        $this->assertSame('true', $allowed->headers->get('Access-Control-Allow-Credentials'));
    }

    /**
     * @param  array<string, string|null>  $values
     */
    private function withEnv(array $values, callable $callback): void
    {
        $original = [];

        foreach ($values as $key => $value) {
            $original[$key] = [$_ENV[$key] ?? null, $_SERVER[$key] ?? null, getenv($key)];
            $this->setEnvValue($key, $value);
        }

        try {
            $callback();
        } finally {
            foreach ($original as $key => [$env, $server, $putenv]) {
                $this->setEnvValue($key, null);

                if ($env !== null) {
                    $_ENV[$key] = $env;
                }

                if ($server !== null) {
                    $_SERVER[$key] = $server;
                }

                if ($putenv !== false) {
                    putenv("{$key}={$putenv}");
                }
            }
        }
    }

    private function setEnvValue(string $key, ?string $value): void
    {
        if ($value === null) {
            unset($_ENV[$key], $_SERVER[$key]);
            putenv($key);

            return;
        }

        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
        putenv("{$key}={$value}");
    }
}
