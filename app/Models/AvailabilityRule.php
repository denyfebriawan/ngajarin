<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Database\Factories\AvailabilityRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One block of a teacher's weekly hours, e.g. Mondays 09:00-12:00, in the tenant's timezone.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $user_id
 * @property int $weekday ISO weekday: 1 = Monday ... 7 = Sunday.
 * @property string $starts_at "HH:MM:SS", local time.
 * @property string $ends_at "HH:MM:SS", local time.
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'weekday', 'starts_at', 'ends_at'])]
class AvailabilityRule extends Model
{
    /** @use HasFactory<AvailabilityRuleFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
        ];
    }

    /**
     * The teacher these hours belong to.
     *
     * @return BelongsTo<User, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
