<?php

namespace App\Http\Controllers;

use App\Http\Resources\RoomTypeAvailabilityResource;
use App\Models\RoomType;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class RoomTypeController extends Controller
{
    /**
     * List room type availability.
     *
     * Returns the public availability calendar for an active room type in a
     * published hotel. When no dates are provided, the next 90 days are returned.
     */
    public function availability(Request $request, int $id)
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $from = CarbonImmutable::parse($validated['from'] ?? today()->toDateString());
        $to = CarbonImmutable::parse($validated['to'] ?? $from->addDays(90)->toDateString());

        // Devuelve la disponibilidad pública de un tipo de habitación activo
        $roomType = RoomType::query()
            ->where('status', 'active')
            ->whereHas('hotel', fn ($query) => $query->where('status', 'published'))
            ->findOrFail($id);

        $availability = $roomType->availability()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('date')
            ->get();

        return RoomTypeAvailabilityResource::collection($availability);
    }
}
