<?php

namespace App\Database;

use Illuminate\Database\Query\Grammars\PostgresGrammar as BasePostgresGrammar;

/**
 * Laravel's Postgres grammar, but dates are sent with their UTC offset.
 *
 * Laravel formats every date it sends to the database, in queries and when models save, as
 * "Y-m-d H:i:s", without the offset, and Postgres then reads it in the session's timezone (UTC).
 * A Carbon in Asia/Jakarta (say, "today" from 00:00 local time) would be taken as that wall time
 * in UTC, seven hours off. With the offset, Postgres knows the exact moment.
 */
class PostgresGrammar extends BasePostgresGrammar
{
    public function getDateFormat(): string
    {
        return 'Y-m-d H:i:sP';
    }
}
