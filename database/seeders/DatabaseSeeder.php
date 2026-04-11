<?php

namespace Database\Seeders;

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

        if (Hotel::query()->where('status', 'published')->exists()) {
            return;
        }

        // Rellena el catálogo solo si todavía no hay hoteles publicados
        Hotel::factory()
            ->count(12)
            ->has(HotelImage::factory()->cover(), 'images')
            ->has(HotelImage::factory()->count(2), 'images')
            ->has(RoomType::factory()->count(3), 'roomTypes')
            ->create([
                'owner_user_id' => $owner->id,
            ]);
    }
}
