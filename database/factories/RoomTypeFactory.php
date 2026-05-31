<?php

namespace Database\Factories;

use App\Models\Hotel;
use App\Models\RoomType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoomType>
 */
class RoomTypeFactory extends Factory
{
    public function definition(): array
    {
        // Crea tipos de habitación activos para calcular el precio inicial
        return [
            'hotel_id' => Hotel::factory(),
            'name' => fake()->randomElement(['Standard Room', 'Superior Room', 'Deluxe Room', 'Junior Suite', 'Family Room']),
            'description' => fake()->sentence(),
            'capacity_adults' => fake()->numberBetween(1, 4),
            'capacity_children' => fake()->numberBetween(0, 3),
            'size_m2' => fake()->randomFloat(2, 18, 80),
            'bed_type' => fake()->randomElement(['Single bed', 'Double bed', 'Queen bed', 'King bed', 'Twin beds']),
            'base_price' => fake()->randomFloat(2, 70, 420),
            'currency' => 'EUR',
            'total_units' => fake()->numberBetween(2, 20),
            'free_cancellation_hours' => fake()->randomElement([null, 24, 48, 72]),
            'status' => 'active',
        ];
    }
}
