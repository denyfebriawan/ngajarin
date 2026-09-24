<?php

namespace Database\Factories;

use App\Models\Subject;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subject>
 */
class SubjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            // Unique number, so a test can create many subjects without hitting the unique name index.
            'name' => fake()->randomElement(['Math', 'Physics', 'Chemistry', 'Biology', 'English', 'Mandarin', 'Piano', 'Coding']).' Level '.fake()->unique()->numberBetween(1, 9999),
            'description' => fake()->optional()->sentence(),
            'duration_minutes' => fake()->randomElement([30, 45, 60, 90]),
            'price' => fake()->randomElement([50_000, 75_000, 100_000, 150_000]),
        ];
    }
}
