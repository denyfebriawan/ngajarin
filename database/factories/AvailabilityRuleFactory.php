<?php

namespace Database\Factories;

use App\Models\AvailabilityRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Callers must set tenant_id and user_id (a member of that tenant), e.g.
 * AvailabilityRule::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $teacher->id]).
 *
 * @extends Factory<AvailabilityRule>
 */
class AvailabilityRuleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'weekday' => 1,
            'starts_at' => '09:00',
            'ends_at' => '12:00',
        ];
    }
}
