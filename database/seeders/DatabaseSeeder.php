<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Hotel;
use App\Models\HotelImage;
use App\Models\Role;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Crea un propietario reutilizable para asociar los hoteles de demo
        $ownerRole = Role::query()->firstOrCreate(['name' => 'owner']);

        $owner = User::query()->firstOrCreate([
            'email' => 'owner@example.com',
        ], [
            'role_id' => $ownerRole->id,
            'name' => 'Demo Owner',
            'status' => 'active',
            'password' => 'password',
        ]);

        $hotels = Hotel::query()->where('status', 'published')->get();

        if ($hotels->isEmpty()) {
            // Rellena el catálogo solo si todavía no hay hoteles publicados
            $hotels = Hotel::factory()
                ->count(12)
                ->has(HotelImage::factory()->cover(), 'images')
                ->has(HotelImage::factory()->count(2), 'images')
                ->has(RoomType::factory()->count(3), 'roomTypes')
                ->create([
                    'owner_user_id' => $owner->id,
                ]);
        }

        // Añade reseñas de demo respetando la relación obligatoria con reservas
        $hotels->each(function (Hotel $hotel): void {
            if ($hotel->reviews()->where('status', 'published')->exists()) {
                return;
            }

            $roomType = $hotel->roomTypes()->orderBy('id')->first();

            if (! $roomType) {
                return;
            }

            User::factory()
                ->count(4)
                ->create()
                ->each(function (User $user) use ($hotel, $roomType): void {
                    $booking = Booking::factory()->create([
                        'user_id' => $user->id,
                        'hotel_id' => $hotel->id,
                        'room_type_id' => $roomType->id,
                        'hotel_name' => $hotel->name,
                        'room_type_name' => $roomType->name,
                        'customer_name' => $user->name,
                        'customer_email' => $user->email,
                    ]);

                    $booking->review()->create([
                        'hotel_id' => $hotel->id,
                        'user_id' => $user->id,
                        'rating' => fake()->numberBetween(3, 5),
                        'comment' => fake()->paragraph(),
                        'status' => 'published',
                    ]);
                });
        });
    }
}
