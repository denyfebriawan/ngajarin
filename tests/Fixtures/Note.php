<?php

namespace Tests\Fixtures;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Model;

/**
 * A tenant-owned model that exists only in tests, for testing BelongsToTenant before real
 * tenant-owned models exist. Its `notes` table is created by the tests that use it.
 */
#[Unguarded]
class Note extends Model
{
    use BelongsToTenant;
}
