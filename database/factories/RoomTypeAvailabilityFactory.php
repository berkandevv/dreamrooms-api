<?php

namespace Database\Factories;

use App\Models\RoomType;
use App\Models\RoomTypeAvailability;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoomTypeAvailability>
 */
class RoomTypeAvailabilityFactory extends Factory
{
    public function definition(): array
    {
        // Genera días abiertos para llenar el calendario de disponibilidad
        return [
            'room_type_id' => RoomType::factory(),
            'date' => fake()->dateTimeBetween('now', '+3 months')->format('Y-m-d'),
            'available_units' => fake()->numberBetween(0, 10),
            'price' => fake()->randomFloat(2, 70, 420),
            'status' => 'open',
            'min_stay_nights' => fake()->randomElement([1, 1, 1, 2, 3]),
        ];
    }
}
