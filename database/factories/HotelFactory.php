<?php

namespace Database\Factories;

use App\Models\Hotel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Hotel>
 */
class HotelFactory extends Factory
{
    public function definition(): array
    {
        // Genera hoteles publicados para que aparezcan en el catálogo público
        $name = fake()->unique()->company().' Hotel';

        return [
            'owner_user_id' => User::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->paragraph(),
            'stars' => fake()->numberBetween(3, 5),
            'country' => 'Spain',
            'region' => fake()->randomElement(['Catalonia', 'Madrid', 'Andalusia', 'Valencia', 'Balearic Islands']),
            'city' => fake()->city(),
            'address' => fake()->streetAddress(),
            'postal_code' => fake()->postcode(),
            'latitude' => fake()->latitude(36, 43),
            'longitude' => fake()->longitude(-9, 4),
            'contact_email' => fake()->companyEmail(),
            'contact_phone' => fake()->phoneNumber(),
            'check_in_time' => '15:00:00',
            'check_out_time' => '11:00:00',
            'cancellation_policy' => fake()->sentence(),
            'tax_rate_percent' => 0,
            'discount_rate_percent' => 0,
            'pets_allowed' => fake()->boolean(35),
            'smoking_allowed' => false,
            'status' => 'published',
        ];
    }
}
