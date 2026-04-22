<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Favorite;
use App\Models\Hotel;
use App\Models\HotelImage;
use App\Models\Role;
use App\Models\RoomType;
use App\Models\RoomTypeAvailability;
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

        // Crea un cliente fijo para probar el flujo público y las reservas
        $customerRole = Role::query()->firstOrCreate(['name' => 'customer']);

        User::query()->firstOrCreate([
            'email' => 'customer@example.com',
        ], [
            'role_id' => $customerRole->id,
            'name' => 'Demo Customer',
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

        // Crea disponibilidad futura para que el calendario tenga datos de demo
        RoomType::query()
            ->where('status', 'active')
            ->whereDoesntHave('availability')
            ->get()
            ->each(function (RoomType $roomType): void {
                collect(range(0, 90))->each(function (int $daysFromToday) use ($roomType): void {
                    $date = today()->addDays($daysFromToday);
                    $isWeekend = $date->isWeekend();
                    $priceMultiplier = $isWeekend ? 1.2 : 1;

                    RoomTypeAvailability::factory()->create([
                        'room_type_id' => $roomType->id,
                        'date' => $date->toDateString(),
                        'available_units' => fake()->numberBetween(1, $roomType->total_units),
                        'price' => round((float) $roomType->base_price * $priceMultiplier, 2),
                        'status' => 'open',
                        'min_stay_nights' => $isWeekend ? 2 : 1,
                    ]);
                });
            });

        // Crea algunos favoritos de demo para usuarios existentes
        User::query()
            ->where('status', 'active')
            ->whereDoesntHave('favorites')
            ->limit(5)
            ->get()
            ->each(function (User $user) use ($hotels): void {
                $hotels->take(3)->each(function (Hotel $hotel) use ($user): void {
                    Favorite::query()->firstOrCreate([
                        'user_id' => $user->id,
                        'hotel_id' => $hotel->id,
                    ]);
                });
            });
    }
}
