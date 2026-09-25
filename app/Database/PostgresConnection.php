<?php

namespace App\Database;

use Illuminate\Database\PostgresConnection as BasePostgresConnection;

/**
 * Laravel's Postgres connection, using the offset-aware PostgresGrammar. Registered for the
 * `pgsql` driver in AppServiceProvider.
 */
class PostgresConnection extends BasePostgresConnection
{
    protected function getDefaultQueryGrammar(): PostgresGrammar
    {
        return new PostgresGrammar($this);
    }
}
