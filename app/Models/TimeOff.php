<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Carbon\CarbonImmutable;
use Database\Factories\TimeOffFactory;
use Illuminate\Database\Eloquent\Attributes\DateFormat;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A teacher's leave: whole days, from starts_at up to (not including) ends_at.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $user_id
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at Exclusive: the first moment the teacher is back.
 * @property string|null $reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('time_off')]
#[Fillable(['user_id', 'starts_at', 'ends_at', 'reason'])]
// Send dates to Postgres with their offset ("...00:00:00+07:00"). The default "Y-m-d H:i:s" drops
// it, so 00:00 in Jakarta would be stored as 00:00 UTC, seven hours off.
#[DateFormat('Y-m-d H:i:sP')]
class TimeOff extends Model
{
    /** @use HasFactory<TimeOffFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
        ];
    }

    /**
     * The teacher who is away.
     *
     * @return BelongsTo<User, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
