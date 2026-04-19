<?php

namespace App\Http\Controllers;

use App\Http\Resources\HotelResource;
use App\Models\Favorite;
use App\Models\Hotel;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function index(Request $request)
    {
        // Los favoritos se resuelven siempre desde el token para evitar suplantar usuarios
        $user = $request->user();

        $hotels = Favorite::query()
            ->where('user_id', $user->id)
            ->whereHas('hotel', fn ($query) => $query->where('status', 'published'))
            ->with([
                'hotel.coverImage',
                'hotel' => fn ($query) => $query
                    ->withMin([
                        'roomTypes' => fn ($roomTypeQuery) => $roomTypeQuery->where('status', 'active'),
                    ], 'base_price')
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

    public function store(Request $request, int $hotelId)
    {
        // Solo se pueden marcar como favoritos hoteles publicados
        $user = $request->user();

        $hotel = Hotel::query()
            ->where('status', 'published')
            ->findOrFail($hotelId);

        Favorite::query()->firstOrCreate([
            'user_id' => $user->id,
            'hotel_id' => $hotel->id,
        ]);

        return response()->json([
            'hotel_id' => $hotel->id,
            'user_id' => $user->id,
            'is_favorite' => true,
        ], 201);
    }

    public function destroy(Request $request, int $hotelId)
    {
        // El borrado queda acotado al favorito del usuario autenticado
        $user = $request->user();

        $hotel = Hotel::query()
            ->where('status', 'published')
            ->findOrFail($hotelId);

        Favorite::query()
            ->where('user_id', $user->id)
            ->where('hotel_id', $hotel->id)
            ->delete();

        return response()->json([
            'hotel_id' => $hotel->id,
            'user_id' => $user->id,
            'is_favorite' => false,
        ]);
    }
}
