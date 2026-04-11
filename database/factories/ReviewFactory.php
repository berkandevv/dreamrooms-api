<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Hotel;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    public function definition(): array
    {
        // Genera reseñas públicas para rellenar el endpoint de reseñas
        return [
            'hotel_id' => Hotel::factory(),
            'user_id' => User::factory(),
            'booking_id' => Booking::factory(),
            'rating' => fake()->numberBetween(3, 5),
            'comment' => fake()->paragraph(),
            'status' => 'published',
        ];
    }
}
