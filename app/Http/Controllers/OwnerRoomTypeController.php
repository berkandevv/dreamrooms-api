<?php

namespace App\Http\Controllers;

use App\Http\Resources\RoomTypeResource;
use App\Models\Hotel;
use App\Models\RoomType;
use Illuminate\Http\Request;

class OwnerRoomTypeController extends Controller
{
    public function index(Request $request, int $hotelId)
    {
        $validated = $request->validate([
            'owner_user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        // Lista las habitaciones de un hotel que pertenece el propietario indicado
        $hotel = Hotel::query()
            ->where('owner_user_id', $validated['owner_user_id'])
            ->findOrFail($hotelId);

        $roomTypes = $hotel->roomTypes()
            ->with([
                'images' => fn ($query) => $query->orderByDesc('is_cover')->orderBy('sort_order'),
                'services' => fn ($query) => $query->orderBy('name'),
            ])
            ->withCount('bookings')
            ->orderBy('id')
            ->get();

        return RoomTypeResource::collection($roomTypes);
    }

    public function show(Request $request, int $roomTypeId): RoomTypeResource
    {
        $validated = $request->validate([
            'owner_user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        // Devuelve el detalle de una habitación de un hotel del propietario indicado
        $roomType = RoomType::query()
            ->where('id', $roomTypeId)
            ->whereHas('hotel', fn ($query) => $query->where('owner_user_id', $validated['owner_user_id']))
            ->with([
                'images' => fn ($query) => $query->orderByDesc('is_cover')->orderBy('sort_order'),
                'services' => fn ($query) => $query->orderBy('name'),
            ])
            ->withCount([
                'availability',
                'bookings',
            ])
            ->firstOrFail();

        return new RoomTypeResource($roomType);
    }
}
