<?php

namespace Database\Factories;

use App\Models\TimeOff;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Callers must set tenant_id and user_id (a member of that tenant), like AvailabilityRuleFactory.
 *
 * @extends Factory<TimeOff>
 */
class TimeOffFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Two days next week, midnight to midnight UTC. Tests that care about dates set their own.
        $start = now()->addWeek()->startOfDay();

        return [
            'starts_at' => $start,
            'ends_at' => $start->addDays(2),
            'reason' => fake()->optional()->randomElement(['Holiday', 'Family event', 'Conference']),
        ];
    }
}
