<?php

namespace Database\Factories;

use App\Models\Rider;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Rider>
 */
class RiderFactory extends Factory
{
    protected $model = Rider::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'vehicle_type' => fake()->randomElement(['motorcycle', 'bicycle', 'car', 'van']),
            'vehicle_number' => strtoupper(fake()->bothify('??-####')),
            'license_number' => strtoupper(fake()->bothify('LIC-######')),
            'is_online' => fake()->boolean(30),
            'is_available' => true,
            'current_latitude' => fake()->latitude(),
            'current_longitude' => fake()->longitude(),
            'rating' => fake()->randomFloat(2, 3, 5),
            'total_deliveries' => fake()->numberBetween(0, 200),
        ];
    }

    public function online(): static
    {
        return $this->state(fn () => [
            'is_online' => true,
            'is_available' => true,
        ]);
    }
}
