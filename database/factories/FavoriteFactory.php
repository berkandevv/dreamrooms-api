<?php

namespace Database\Factories;

use App\Models\Favorite;
use App\Models\Hotel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Favorite>
 */
class FavoriteFactory extends Factory
{
    public function definition(): array
    {
        // Asocia usuarios y hoteles para probar el listado de favoritos
        return [
            'user_id' => User::factory(),
            'hotel_id' => Hotel::factory(),
        ];
    }
}
