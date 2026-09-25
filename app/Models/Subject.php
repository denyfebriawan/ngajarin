<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Database\Factories\SubjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Something a workspace teaches, e.g. "Math Grade 10": how long a lesson lasts and what it costs.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property string|null $description
 * @property int $duration_minutes
 * @property int $price Whole rupiah.
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'description', 'duration_minutes', 'price'])]
class Subject extends Model
{
    /** @use HasFactory<SubjectFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'price' => 'integer',
        ];
    }

    /**
     * The workspace members (owners or tutors) who teach this subject.
     *
     * @return BelongsToMany<User, $this>
     */
    public function teachers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'subject_teacher');
    }

    /**
     * Every lesson booked for this subject, including cancelled ones.
     *
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Replace this subject's teachers with the given users.
     *
     * @param  list<int>  $userIds
     */
    public function syncTeachers(array $userIds): void
    {
        // The pivot's tenant_id is part of both composite foreign keys, so it must be filled in.
        $this->teachers()->syncWithPivotValues($userIds, ['tenant_id' => $this->tenant_id]);
    }
}
