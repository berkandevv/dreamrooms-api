<?php

namespace App\Services;

use App\Models\Hotel;
use Illuminate\Support\Str;

class HotelSlugService
{
    // Genera un slug único para un hotel
    public function generate(string $hotelName, ?int $ignoreHotelId = null): string
    {
        $baseSlug = Str::slug($hotelName);
        $slug = $baseSlug;
        $counter = 2;

        while (Hotel::query()
            ->where('slug', $slug)
            ->when($ignoreHotelId, fn ($query) => $query->whereKeyNot($ignoreHotelId))
            ->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
