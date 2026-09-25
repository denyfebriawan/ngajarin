<?php

namespace Database\Factories;

use App\Models\Booking;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Callers must set tenant_id, subject_id, teacher_id and student_id: the composite foreign keys
 * need a subject of that workspace and two of its members, which a factory can't guess.
 *
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Next week, 10:00-11:00 UTC. Tests that care about the time set their own.
        $start = now()->addWeek()->setTime(10, 0);

        return [
            'starts_at' => $start,
            'ends_at' => $start->addHour(),
            'price' => 150_000,
        ];
    }
}
