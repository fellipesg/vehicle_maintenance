<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Os testes nunca mandam eventos ao Sentry de verdade, mesmo com um DSN real no .env local
 * (phpunit.xml e phpunit.pgsql.xml forçam SENTRY_LARAVEL_DSN e SENTRY_DSN vazios).
 */
class SentryDisabledInTestsTest extends TestCase
{
    public function test_sentry_has_no_dsn_while_testing(): void
    {
        $this->assertTrue(blank(config('sentry.dsn')));
    }
}
