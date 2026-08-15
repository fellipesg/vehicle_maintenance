<?php

namespace App\Database;

use DateTimeInterface;
use Illuminate\Database\PostgresConnection as BasePostgresConnection;

/**
 * Laravel converts PHP bools to 0/1 before binding. PostgreSQL (and Neon’s
 * pooler) rejects integer literals for boolean columns, which aborts the
 * current transaction; the next query then surfaces only SQLSTATE 25P02.
 */
class PostgresConnection extends BasePostgresConnection
{
    public function prepareBindings(array $bindings)
    {
        $grammar = $this->getQueryGrammar();

        foreach ($bindings as $key => $value) {
            if ($value instanceof DateTimeInterface) {
                $bindings[$key] = $value->format($grammar->getDateFormat());
            } elseif (is_bool($value)) {
                $bindings[$key] = $value ? 'true' : 'false';
            }
        }

        return $bindings;
    }
}
