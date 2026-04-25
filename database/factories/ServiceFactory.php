<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->words(3, true));

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'icon' => fake()->optional()->randomElement(['wifi', 'waves', 'car', 'snowflake', 'coffee', 'dumbbell']),
            'category' => fake()->randomElement(['General', 'Wellness', 'Transport', 'Room']),
            'scope' => fake()->randomElement(['hotel', 'room_type', 'both']),
            'is_active' => true,
        ];
    }

    public function hotel(): static
    {
        return $this->state(fn (array $attributes) => [
            'scope' => 'hotel',
        ]);
    }

    public function roomType(): static
    {
        return $this->state(fn (array $attributes) => [
            'scope' => 'room_type',
        ]);
    }

    public function both(): static
    {
        return $this->state(fn (array $attributes) => [
            'scope' => 'both',
        ]);
    }
}
