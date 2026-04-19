<?php

namespace App\Http\Controllers;

use App\Http\Resources\RoomTypeAvailabilityResource;
use App\Http\Resources\RoomTypeResource;
use App\Models\Hotel;
use App\Models\RoomType;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OwnerRoomTypeController extends Controller
{
    public function index(Request $request, int $hotelId)
    {
        // Las habitaciones se listan solo si el hotel pertenece al owner autenticado
        $ownerUserId = $request->user()->id;

        $hotel = Hotel::query()
            ->where('owner_user_id', $ownerUserId)
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
        // La nueva habitación se crea dentro de un hotel propio del owner
        $ownerUserId = $request->user()->id;

        $hotel = Hotel::query()
            ->where('owner_user_id', $ownerUserId)
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
        // Evita consultar habitaciones de hoteles de otros propietarios
        $ownerUserId = $request->user()->id;

        $roomType = RoomType::query()
            ->where('id', $roomTypeId)
            ->whereHas('hotel', fn ($query) => $query->where('owner_user_id', $ownerUserId))
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
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);
        // La disponibilidad privada solo se expone para habitaciones propias
        $ownerUserId = $request->user()->id;

        $roomType = RoomType::query()
            ->where('id', $roomTypeId)
            ->whereHas('hotel', fn ($query) => $query->where('owner_user_id', $ownerUserId))
            ->firstOrFail();

        $from = CarbonImmutable::parse($validated['from']);
        $to = CarbonImmutable::parse($validated['to']);

        $availability = $roomType->availability()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('date')
            ->get();

        return RoomTypeAvailabilityResource::collection($availability);
    }

    public function availabilityBulk(Request $request, int $roomTypeId)
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.date' => ['required', 'date'],
            'items.*.available_units' => ['required', 'integer', 'min:0'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'items.*.status' => ['required', 'string', 'in:open,closed'],
            'items.*.min_stay_nights' => ['nullable', 'integer', 'min:1'],
        ]);
        // El bulk update queda limitado a habitaciones del owner autenticado
        $ownerUserId = $request->user()->id;

        $roomType = RoomType::query()
            ->where('id', $roomTypeId)
            ->whereHas('hotel', fn ($query) => $query->where('owner_user_id', $ownerUserId))
            ->firstOrFail();

        $dates = collect($validated['items'])
            ->pluck('date')
            ->map(fn ($date) => CarbonImmutable::parse($date)->toDateString())
            ->unique()
            ->values();

        $rows = collect($validated['items'])->map(fn ($item) => [
            'room_type_id' => $roomType->id,
            'date' => CarbonImmutable::parse($item['date'])->toDateString(),
            'available_units' => $item['available_units'],
            'price' => round((float) $item['price'], 2),
            'status' => $item['status'],
            'min_stay_nights' => $item['min_stay_nights'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ])->all();

        DB::table('room_type_availabilities')->upsert(
            $rows,
            ['room_type_id', 'date'],
            ['available_units', 'price', 'status', 'min_stay_nights', 'updated_at'],
        );

        $availability = $roomType->availability()
            ->whereIn('date', $dates)
            ->orderBy('date')
            ->get();

        return RoomTypeAvailabilityResource::collection($availability);
    }

    public function update(Request $request, int $roomTypeId): RoomTypeResource
    {
        $validated = $request->validate($this->roomTypeRules(required: false));
        // Solo se permite actualizar habitaciones de hoteles propios
        $ownerUserId = $request->user()->id;

        $roomType = RoomType::query()
            ->where('id', $roomTypeId)
            ->whereHas('hotel', fn ($query) => $query->where('owner_user_id', $ownerUserId))
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
