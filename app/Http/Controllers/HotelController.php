<?php

namespace App\Http\Controllers;

use App\Http\Resources\HotelResource;
use App\Models\Hotel;

class HotelController extends Controller
{
    public function index()
    {
        // Devuelve el catálogo público de hoteles con los datos necesarios para listados
        $hotels = Hotel::query()
            ->where('status', 'published')
            ->with('coverImage')
            ->withMin('roomTypes', 'base_price')
            ->withAvg([
                'reviews as average_rating' => fn ($query) => $query->where('status', 'published'),
            ], 'rating')
            ->withCount([
                'reviews as reviews_count' => fn ($query) => $query->where('status', 'published'),
            ])
            ->orderBy('id')
            ->get();

        return HotelResource::collection($hotels);
    }
}
