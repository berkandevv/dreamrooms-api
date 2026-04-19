<?php

namespace App\Http\Controllers;

use App\Http\Resources\RoomTypeAvailabilityResource;
use App\Http\Resources\RoomTypeResource;
use App\Models\Hotel;
use App\Models\RoomType;
use Carbon\CarbonImmutable;
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

    public function store(Request $request, int $hotelId)
    {
        $validated = $request->validate($this->roomTypeRules());

        // Crea una habitación dentro de un hotel del propietario indicado
        $hotel = Hotel::query()
            ->where('owner_user_id', $validated['owner_user_id'])
            ->findOrFail($hotelId);

        $roomType = $hotel->roomTypes()->create($this->roomTypePayload($validated));
        $roomType->load(['images', 'services']);
        $roomType->loadCount(['availability', 'bookings']);

        return (new RoomTypeResource($roomType))
            ->response()
            ->setStatusCode(201);
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

    public function availability(Request $request, int $roomTypeId)
    {
        $validated = $request->validate([
            'owner_user_id' => ['required', 'integer', 'exists:users,id'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        // Devuelve la disponibilidad de una habitación del propietario indicado
        $roomType = RoomType::query()
            ->where('id', $roomTypeId)
            ->whereHas('hotel', fn ($query) => $query->where('owner_user_id', $validated['owner_user_id']))
            ->firstOrFail();

        $from = CarbonImmutable::parse($validated['from']);
        $to = CarbonImmutable::parse($validated['to']);

        $availability = $roomType->availability()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('date')
            ->get();

        return RoomTypeAvailabilityResource::collection($availability);
    }

    public function update(Request $request, int $roomTypeId): RoomTypeResource
    {
        $validated = $request->validate($this->roomTypeRules(required: false));

        // Actualiza solo habitaciones que pertenecen a hoteles del propietario indicado
        $roomType = RoomType::query()
            ->where('id', $roomTypeId)
            ->whereHas('hotel', fn ($query) => $query->where('owner_user_id', $validated['owner_user_id']))
            ->firstOrFail();

        $roomType->update($this->roomTypePayload($validated, $roomType));
        $roomType->load(['images', 'services']);
        $roomType->loadCount(['availability', 'bookings']);

        return new RoomTypeResource($roomType);
    }

    private function roomTypeRules(bool $required = true): array
    {
        $presence = $required ? 'required' : 'sometimes';

        return [
            'owner_user_id' => ['required', 'integer', 'exists:users,id'],
            'name' => [$presence, 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'capacity_adults' => [$presence, 'integer', 'min:1', 'max:255'],
            'capacity_children' => [$presence, 'integer', 'min:0', 'max:255'],
            'size_m2' => ['nullable', 'numeric', 'min:0'],
            'bed_type' => ['nullable', 'string', 'max:100'],
            'base_price' => [$presence, 'numeric', 'min:0'],
            'total_units' => [$presence, 'integer', 'min:1', 'max:65535'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
        ];
    }

    private function roomTypePayload(array $validated, ?RoomType $roomType = null): array
    {
        return collect([
            'name' => $validated['name'] ?? $roomType?->name,
            'description' => $validated['description'] ?? $roomType?->description,
            'capacity_adults' => $validated['capacity_adults'] ?? $roomType?->capacity_adults,
            'capacity_children' => $validated['capacity_children'] ?? $roomType?->capacity_children,
            'size_m2' => $validated['size_m2'] ?? $roomType?->size_m2,
            'bed_type' => $validated['bed_type'] ?? $roomType?->bed_type,
            'base_price' => $validated['base_price'] ?? $roomType?->base_price,
            'total_units' => $validated['total_units'] ?? $roomType?->total_units,
            'status' => $validated['status'] ?? $roomType?->status ?? 'active',
        ])->filter(fn ($value) => $value !== null)->all();
    }
}
