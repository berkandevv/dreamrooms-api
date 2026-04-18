<?php

namespace App\Http\Controllers;

use App\Http\Resources\HotelResource;
use App\Models\Hotel;
use Illuminate\Http\Request;

class OwnerHotelController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'owner_user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        // Devuelve los hoteles del propietario indicado hasta que activemos auth
        $hotels = Hotel::query()
            ->where('owner_user_id', $validated['owner_user_id'])
            ->with('coverImage')
            ->withMin('roomTypes', 'base_price')
            ->withAvg([
                'reviews as average_rating' => fn ($query) => $query->where('status', 'published'),
            ], 'rating')
            ->withCount([
                'bookings',
                'roomTypes',
                'reviews as reviews_count' => fn ($query) => $query->where('status', 'published'),
            ])
            ->orderBy('id')
            ->get();

        return HotelResource::collection($hotels);
    }

    public function show(Request $request, int $id): HotelResource
    {
        $validated = $request->validate([
            'owner_user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        // Devuelve el detalle completo de un hotel del propietario indicado
        $hotel = Hotel::query()
            ->where('owner_user_id', $validated['owner_user_id'])
            ->where('id', $id)
            ->with([
                'coverImage',
                'images' => fn ($query) => $query->orderBy('sort_order'),
                'services' => fn ($query) => $query->orderBy('name'),
                'roomTypes' => fn ($query) => $query->orderBy('id'),
                'roomTypes.images' => fn ($query) => $query->orderByDesc('is_cover')->orderBy('sort_order'),
                'roomTypes.services' => fn ($query) => $query->orderBy('name'),
            ])
            ->withMin('roomTypes', 'base_price')
            ->withAvg([
                'reviews as average_rating' => fn ($query) => $query->where('status', 'published'),
            ], 'rating')
            ->withCount([
                'bookings',
                'roomTypes',
                'reviews as reviews_count' => fn ($query) => $query->where('status', 'published'),
            ])
            ->firstOrFail();

        return new HotelResource($hotel);
    }
}
