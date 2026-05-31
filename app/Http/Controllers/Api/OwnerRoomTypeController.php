<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\RoomTypeAvailabilityResource;
use App\Http\Resources\RoomTypeResource;
use App\Models\Hotel;
use App\Models\RoomType;
use App\Services\ImageStorageService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OwnerRoomTypeController extends Controller
{
    // Inicializa el servicio de imágenes
    public function __construct(private readonly ImageStorageService $images) {}

    // Lista los tipos de habitación de un hotel del propietario autenticado
    public function index(Request $request, int $hotelId)
    {
        // Las habitaciones se listan solo si el hotel pertenece al owner autenticado
        $ownerUserId = $request->user()->id;

        $hotel = Hotel::query()
            ->where('owner_user_id', $ownerUserId)
            ->findOrFail($hotelId);

        $roomTypes = $hotel->roomTypes()
            ->with($this->roomTypeRelations())
            ->withCount($this->roomTypeCounts())
            ->orderBy('id')
            ->get();

        return RoomTypeResource::collection($roomTypes);
    }

    // Crea un tipo de habitación dentro de un hotel del propietario autenticado
    public function store(Request $request, int $hotelId)
    {
        $validated = $request->validate($this->roomTypeRules());
        // La nueva habitación se crea dentro de un hotel propio del owner
        $ownerUserId = $request->user()->id;

        $hotel = Hotel::query()
            ->where('owner_user_id', $ownerUserId)
            ->findOrFail($hotelId);

        $roomType = $hotel->roomTypes()->create($this->roomTypePayload($validated));
        $this->syncServices($roomType, $validated);
        $this->storeImages($roomType, $validated);
        $this->loadRoomTypeDetails($roomType);

        return RoomTypeResource::make($roomType)
            ->response()
            ->setStatusCode(201);
    }

    // Muestra el detalle de un tipo de habitación del propietario autenticado
    public function show(Request $request, int $roomTypeId): RoomTypeResource
    {
        // Evita consultar habitaciones de hoteles de otros propietarios
        $ownerUserId = $request->user()->id;

        $roomType = RoomType::query()
            ->where('id', $roomTypeId)
            ->whereHas('hotel', fn ($query) => $query->where('owner_user_id', $ownerUserId))
            ->with($this->roomTypeRelations())
            ->withCount($this->roomTypeCounts())
            ->firstOrFail();

        return RoomTypeResource::make($roomType);
    }

    // Devuelve la disponibilidad de un tipo de habitación del propietario
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

    // Actualiza en bloque la disponibilidad y precios de varias fechas
    public function availabilityBulk(Request $request, int $roomTypeId)
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.date' => ['required', 'date'],
            'items.*.available_units' => ['required', 'integer', 'min:0'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'items.*.currency' => ['nullable', 'string', 'size:3'],
            'items.*.status' => ['required', 'string', 'in:open,closed'],
            'items.*.min_stay_nights' => ['nullable', 'integer', 'min:1'],
        ]);
        // El bulk update queda limitado a habitaciones del owner autenticado
        $ownerUserId = $request->user()->id;

        $roomType = RoomType::query()
            ->where('id', $roomTypeId)
            ->whereHas('hotel', fn ($query) => $query->where('owner_user_id', $ownerUserId))
            ->firstOrFail();

        $this->validateAvailableUnits($validated['items'], $roomType->total_units);

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
            'currency' => strtoupper($item['currency'] ?? $roomType->currency ?? 'EUR'),
            'status' => $item['status'],
            'min_stay_nights' => $item['min_stay_nights'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ])->all();

        DB::table('room_type_availabilities')->upsert(
            $rows,
            ['room_type_id', 'date'],
            ['available_units', 'price', 'currency', 'status', 'min_stay_nights', 'updated_at'],
        );

        $availability = $roomType->availability()
            ->whereIn('date', $dates)
            ->orderBy('date')
            ->get();

        return RoomTypeAvailabilityResource::collection($availability);
    }

    // Evita guardar más disponibilidad diaria que habitaciones reales tiene el tipo
    private function validateAvailableUnits(array $items, int $totalUnits): void
    {
        foreach ($items as $index => $item) {
            if ($item['available_units'] <= $totalUnits) {
                continue;
            }

            throw ValidationException::withMessages([
                "items.{$index}.available_units" => ["Available units cannot be greater than total units ({$totalUnits})."],
            ]);
        }
    }

    // Evita bajar las unidades totales por debajo de la disponibilidad ya creada
    private function validateTotalUnits(RoomType $roomType, int $totalUnits): void
    {
        $maxAvailableUnits = (int) $roomType->availability()->max('available_units');

        if ($maxAvailableUnits > $totalUnits) {
            throw ValidationException::withMessages([
                'total_units' => ["Total units cannot be lower than existing availability ({$maxAvailableUnits})."],
            ]);
        }
    }

    // Actualiza los datos base de un tipo de habitación del propietario
    public function update(Request $request, int $roomTypeId): RoomTypeResource
    {
        $validated = $request->validate($this->roomTypeRules(required: false));
        // Solo se permite actualizar habitaciones de hoteles propios
        $ownerUserId = $request->user()->id;

        $roomType = RoomType::query()
            ->where('id', $roomTypeId)
            ->whereHas('hotel', fn ($query) => $query->where('owner_user_id', $ownerUserId))
            ->firstOrFail();

        if (isset($validated['total_units'])) {
            $this->validateTotalUnits($roomType, (int) $validated['total_units']);
        }

        $roomType->update($this->roomTypePayload($validated, $roomType));
        $this->syncServices($roomType, $validated);
        $this->storeImages($roomType, $validated);
        $this->loadRoomTypeDetails($roomType);

        return RoomTypeResource::make($roomType);
    }

    // Sube imágenes a un tipo de habitación del propietario autenticado
    public function images(Request $request, int $roomTypeId): RoomTypeResource
    {
        $validated = $request->validate($this->imageRules());
        $ownerUserId = $request->user()->id;

        $roomType = RoomType::query()
            ->where('id', $roomTypeId)
            ->whereHas('hotel', fn ($query) => $query->where('owner_user_id', $ownerUserId))
            ->firstOrFail();

        $this->storeImages($roomType, $validated);
        $this->loadRoomTypeDetails($roomType);

        return RoomTypeResource::make($roomType);
    }

    // Reglas de validación compartidas para crear y actualizar habitaciones
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
            'currency' => ['nullable', 'string', 'size:3'],
            'total_units' => [$presence, 'integer', 'min:1', 'max:65535'],
            'free_cancellation_hours' => ['nullable', 'integer', 'min:0', 'max:8760'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
            'service_ids' => ['sometimes', 'array'],
            'service_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('services', 'id')->where(fn ($query) => $query
                    ->where('is_active', true)
                    ->whereIn('scope', ['room_type', 'both'])),
            ],
            ...$this->imageRules(),
        ];
    }

    // Devuelve las reglas de validación de imágenes
    private function imageRules(): array
    {
        return $this->images->validationRules();
    }

    // Sincroniza los servicios asignados al tipo de habitación
    private function syncServices(RoomType $roomType, array $validated): void
    {
        if (! array_key_exists('service_ids', $validated)) {
            return;
        }

        $roomType->services()->sync($validated['service_ids']);
    }

    // Guarda las imágenes enviadas para el tipo de habitación
    private function storeImages(RoomType $roomType, array $validated): void
    {
        $this->images->store($roomType, $validated, 'room-types');
    }

    // Helpers de carga para respuestas de tipos de habitación

    // Devuelve las relaciones necesarias para responder con un tipo de habitación completo
    private function roomTypeRelations(): array
    {
        return [
            'coverImage',
            'images' => fn ($query) => $query->orderByDesc('is_cover')->orderBy('sort_order'),
            'services' => fn ($query) => $query->orderBy('name'),
        ];
    }

    // Devuelve los contadores que se cargan junto al tipo de habitación
    private function roomTypeCounts(): array
    {
        return ['availability', 'bookings'];
    }

    // Carga en memoria las relaciones y contadores del detalle de habitación
    private function loadRoomTypeDetails(RoomType $roomType): void
    {
        $roomType->load($this->roomTypeRelations());
        $roomType->loadCount($this->roomTypeCounts());
    }

    // Prepara solo los campos permitidos para guardar el tipo de habitación
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
            'currency' => isset($validated['currency'])
                ? strtoupper($validated['currency'])
                : ($roomType?->currency ?? 'EUR'),
            'total_units' => $validated['total_units'] ?? $roomType?->total_units,
            'free_cancellation_hours' => array_key_exists('free_cancellation_hours', $validated)
                ? $validated['free_cancellation_hours']
                : $roomType?->free_cancellation_hours,
            'status' => $validated['status'] ?? $roomType?->status ?? 'active',
        ])->filter(fn ($value, $key) => $value !== null
            || ($key === 'free_cancellation_hours' && array_key_exists($key, $validated)))->all();
    }
}
