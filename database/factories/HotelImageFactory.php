<?php

namespace Database\Factories;

use App\Models\Hotel;
use App\Models\HotelImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HotelImage>
 */
class HotelImageFactory extends Factory
{
    public function definition(): array
    {
        // Usa una URL estable por seed para simular imágenes reales de hotel
        $seed = fake()->uuid();

        return [
            'hotel_id' => Hotel::factory(),
            'image_url' => "https://picsum.photos/seed/hotel-{$seed}/1200/800",
            'alt_text' => fake()->sentence(3),
            'is_cover' => false,
            'sort_order' => fake()->numberBetween(1, 5),
        ];
    }

    public function cover(): static
    {
        // Marca una imagen como portada para el listado de hoteles
        return $this->state(fn () => [
            'is_cover' => true,
            'sort_order' => 0,
        ]);
    }
}
