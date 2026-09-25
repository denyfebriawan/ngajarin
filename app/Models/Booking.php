<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Tenancy\BelongsToTenant;
use Carbon\CarbonImmutable;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A lesson: a student booked a teacher for a subject, from starts_at up to (not including) ends_at.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $subject_id
 * @property int $teacher_id
 * @property int $student_id
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at Exclusive.
 * @property BookingStatus $status
 * @property int $price Whole rupiah, copied from the subject when booked.
 * @property CarbonImmutable|null $cancelled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['subject_id', 'teacher_id', 'student_id', 'starts_at', 'ends_at', 'price'])]
// `period` is computed by Postgres; the app reads starts_at/ends_at instead.
#[Hidden(['period'])]
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * New bookings are confirmed (the same default as the column), known without re-reading it.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'confirmed',
    ];

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
            'cancelled_at' => 'immutable_datetime',
            'status' => BookingStatus::class,
            'price' => 'integer',
        ];
    }

    /**
     * Cancel the lesson. Its time stops counting for the no-double-booking constraints, so the
     * slot can be booked again.
     */
    public function cancel(): void
    {
        $this->status = BookingStatus::Cancelled;
        $this->cancelled_at = CarbonImmutable::now();
        $this->save();
    }

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
