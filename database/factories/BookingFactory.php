<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Hotel;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    public function definition(): array
    {
        // Crea reservas completadas para poder asociarlas a reseñas publicadas.
        $checkIn = fake()->dateTimeBetween('-10 months', '-1 month');
        $nights = fake()->numberBetween(1, 7);
        $subtotal = fake()->randomFloat(2, 120, 1200);
        $taxes = round($subtotal * 0.1, 2);

        return [
            'booking_reference' => strtoupper(Str::random(10)),
            'user_id' => User::factory(),
            'hotel_id' => Hotel::factory(),
            'room_type_id' => RoomType::factory(),
            'hotel_name' => fake()->company().' Hotel',
            'room_type_name' => fake()->randomElement(['Standard Room', 'Superior Room', 'Deluxe Room', 'Junior Suite']),
            'customer_name' => fake()->name(),
            'customer_email' => fake()->safeEmail(),
            'customer_phone' => fake()->phoneNumber(),
            'check_in' => $checkIn,
            'check_out' => (clone $checkIn)->modify("+{$nights} days"),
            'nights' => $nights,
            'adults_count' => fake()->numberBetween(1, 4),
            'children_count' => fake()->numberBetween(0, 2),
            'units_booked' => 1,
            'status' => 'completed',
            'payment_method' => 'card',
            'payment_status' => 'paid',
            'subtotal_amount' => $subtotal,
            'taxes_amount' => $taxes,
            'discount_amount' => 0,
            'total_amount' => $subtotal + $taxes,
            'currency' => 'EUR',
            'booked_at' => (clone $checkIn)->modify('-30 days'),
            'confirmed_at' => (clone $checkIn)->modify('-29 days'),
            'notes' => null,
        ];
    }
}
