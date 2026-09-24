<?php

namespace App\Support;

use Illuminate\Database\QueryException;
use Throwable;

/**
 * Recognises Postgres errors that the app turns into friendly validation messages.
 * (Unique violations already have Laravel's UniqueConstraintViolationException.)
 */
final class PostgresError
{
    // SQLSTATE for a violated EXCLUDE constraint, e.g. overlapping time ranges.
    public const EXCLUSION_VIOLATION = '23P01';

    public static function isExclusionViolation(Throwable $exception): bool
    {
        return $exception instanceof QueryException
            && $exception->getCode() === self::EXCLUSION_VIOLATION;
    }
}
