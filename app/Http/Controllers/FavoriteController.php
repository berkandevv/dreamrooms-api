<?php

namespace App\Http\Controllers;

use App\Http\Resources\HotelResource;
use App\Models\Favorite;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        // Devuelve los hoteles favoritos del usuario indicado hasta que activemos auth
        $hotels = Favorite::query()
            ->where('user_id', $validated['user_id'])
            ->whereHas('hotel', fn ($query) => $query->where('status', 'published'))
            ->with([
                'hotel.coverImage',
                'hotel' => fn ($query) => $query
                    ->withMin('roomTypes', 'base_price')
                    ->withAvg([
                        'reviews as average_rating' => fn ($reviewQuery) => $reviewQuery->where('status', 'published'),
                    ], 'rating')
                    ->withCount([
                        'reviews as reviews_count' => fn ($reviewQuery) => $reviewQuery->where('status', 'published'),
                    ]),
            ])
            ->orderByDesc('created_at')
            ->get()
            ->pluck('hotel');

        return HotelResource::collection($hotels);
    }
}
