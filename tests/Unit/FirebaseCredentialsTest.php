<?php

namespace Tests\Unit;

use App\Support\FirebaseCredentials;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FirebaseCredentialsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'firebase.credentials_base64' => null,
            'firebase.credentials_json' => null,
            'firebase.credentials_path' => 'missing-firebase-credentials.json',
        ]);
    }

    #[Test]
    public function test_resolves_credentials_from_base64_env(): void
    {
        $payload = ['type' => 'service_account', 'project_id' => 'demo'];
        config(['firebase.credentials_base64' => base64_encode(json_encode($payload))]);

        $this->assertSame($payload, FirebaseCredentials::resolve());
    }

    #[Test]
    public function test_resolves_credentials_from_json_env(): void
    {
        $payload = ['type' => 'service_account', 'project_id' => 'demo'];
        config(['firebase.credentials_json' => json_encode($payload)]);

        $this->assertSame($payload, FirebaseCredentials::resolve());
    }

    #[Test]
    public function test_throws_when_no_credentials_configured(): void
    {
        config(['firebase.credentials_path' => 'missing-firebase-credentials.json']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Firebase credentials not found');

        FirebaseCredentials::resolve();
    }
}
