<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\AvailabilityResource;
use App\Models\RoomType;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class RoomTypeController extends Controller
{
    public function availability(Request $request, int $roomTypeId)
    {
        $validated = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        $from = CarbonImmutable::parse($validated['from']);
        $to = CarbonImmutable::parse($validated['to']);

        // Devuelve la disponibilidad pública de un tipo de habitación activo
        $roomType = RoomType::query()
            ->where('status', 'active')
            ->whereHas('hotel', fn ($query) => $query->where('status', 'published'))
            ->findOrFail($roomTypeId);

        $availability = $roomType->availability()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('date')
            ->get();

        return AvailabilityResource::collection($availability);
    }
}
