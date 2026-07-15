<?php

namespace Database\Factories;

use App\Helpers\DeliveryStatus;
use App\Models\Delivery;
use App\Models\Rider;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Delivery>
 */
class DeliveryFactory extends Factory
{
    protected $model = Delivery::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'rider_id' => null,
            'tracking_number' => sprintf('NASO-%s-%05d', now()->format('Ymd'), fake()->numberBetween(1, 99999)),
            'customer_name' => fake()->name(),
            'customer_phone' => fake()->phoneNumber(),
            'pickup_address' => fake()->streetAddress(),
            'delivery_address' => fake()->streetAddress(),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'status' => DeliveryStatus::PENDING,
            'priority' => fake()->randomElement(['low', 'normal', 'high', 'urgent']),
            'offer_expires_at' => now()->addMinutes(15),
            'notes' => fake()->optional()->sentence(),
            'delivery_fee' => fake()->randomFloat(2, 50, 500),
            'payment_method' => fake()->randomElement(['cash', 'card', 'wallet']),
            'payment_status' => 'pending',
        ];
    }

    public function assigned(): static
    {
        return $this->state(fn () => [
            'rider_id' => Rider::factory()->online(),
            'status' => DeliveryStatus::ASSIGNED,
            'assigned_at' => now(),
        ]);
    }
}
