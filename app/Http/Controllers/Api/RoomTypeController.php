<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\AvailabilityResource;
use App\Models\RoomType;
use App\Services\RoomTypeAvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class RoomTypeController extends Controller
{
    private readonly RoomTypeAvailabilityService $availabilityService;

    public function __construct(RoomTypeAvailabilityService $availabilityService)
    {
        $this->availabilityService = $availabilityService;
    }

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

    public function quote(Request $request, int $roomTypeId)
    {
        $validated = $request->validate([
            'check_in' => ['required', 'date', 'after_or_equal:today'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'units_booked' => ['nullable', 'integer', 'min:1'],
        ]);

        $roomType = RoomType::query()
            ->with('hotel')
            ->where('status', 'active')
            ->whereHas('hotel', fn ($query) => $query->where('status', 'published'))
            ->findOrFail($roomTypeId);

        $stayData = $this->availabilityService->buildStayData($validated);

        return response()->json([
            'data' => $this->availabilityService->quoteStay($roomType, $stayData),
        ]);
    }
}
