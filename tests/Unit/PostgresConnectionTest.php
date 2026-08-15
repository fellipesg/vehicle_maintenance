<?php

namespace Tests\Unit;

use App\Database\PostgresConnection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PostgresConnectionTest extends TestCase
{
    #[Test]
    public function prepare_bindings_sends_postgres_boolean_literals(): void
    {
        $connection = new PostgresConnection(
            fn () => null,
            'neondb',
            '',
            ['driver' => 'pgsql', 'name' => 'pgsql', 'prefix' => ''],
        );

        $this->assertSame(
            ['false', 'true', 5, 'keep'],
            array_values($connection->prepareBindings([
                false,
                true,
                5,
                'keep',
            ])),
        );
    }
}
