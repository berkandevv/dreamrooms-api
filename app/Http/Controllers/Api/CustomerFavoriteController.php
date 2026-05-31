<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\HotelResource;
use App\Models\Favorite;
use App\Models\Hotel;
use Illuminate\Http\Request;

class CustomerFavoriteController extends Controller
{
    // Lista los hoteles favoritos del cliente
    public function index(Request $request)
    {
        // Los favoritos se resuelven siempre desde el token para evitar suplantar usuarios
        $user = $request->user();

        $hotels = Favorite::query()
            ->where('user_id', $user->id)
            ->whereHas('hotel', fn ($query) => $query->published())
            ->with([
                'hotel.coverImage',
                'hotel' => fn ($query) => $query
                    ->withPublicSummaryMetrics(),
            ])
            ->orderByDesc('created_at')
            ->get()
            ->pluck('hotel');

        return HotelResource::collection($hotels);
    }

    // Añade un hotel a los favoritos del cliente
    public function store(Request $request, int $hotelId)
    {
        // Solo se pueden marcar como favoritos hoteles publicados
        $user = $request->user();

        $hotel = Hotel::query()
            ->published()
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

    // Elimina un hotel de los favoritos del cliente
    public function destroy(Request $request, int $hotelId)
    {
        // El borrado queda acotado al favorito del usuario autenticado
        $user = $request->user();

        $hotel = Hotel::query()
            ->published()
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
