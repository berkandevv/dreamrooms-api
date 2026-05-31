<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\HotelResource;
use App\Models\Hotel;
use App\Services\HotelSlugService;
use App\Services\ImageStorageService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OwnerHotelController extends Controller
{
    // Inicializa los servicios de hoteles
    public function __construct(
        private readonly HotelSlugService $hotelSlugs,
        private readonly ImageStorageService $images,
    ) {}

    // Lista los hoteles del propietario autenticado
    public function index(Request $request)
    {
        // El owner solo ve los hoteles asociados a su propio usuario
        $ownerUserId = $request->user()->id;

        $hotels = Hotel::query()
            ->where('owner_user_id', $ownerUserId)
            ->with('coverImage')
            ->withPublicSummaryMetrics()
            ->withCount([
                'bookings',
                'roomTypes',
            ])
            ->orderBy('id')
            ->get();

        return HotelResource::collection($hotels);
    }

    // Muestra un hotel del propietario autenticado
    public function show(Request $request, int $hotelId): HotelResource
    {
        // Evita que un propietario consulte hoteles de otro owner cambiando el ID
        $ownerUserId = $request->user()->id;

        $hotel = Hotel::query()
            ->where('owner_user_id', $ownerUserId)
            ->where('id', $hotelId)
            ->with([
                'coverImage',
                'images' => fn ($query) => $query->orderBy('sort_order'),
                'services' => fn ($query) => $query->orderBy('name'),
                'roomTypes' => fn ($query) => $query->orderBy('id'),
                'roomTypes.coverImage',
                'roomTypes.images' => fn ($query) => $query->orderByDesc('is_cover')->orderBy('sort_order'),
                'roomTypes.services' => fn ($query) => $query->orderBy('name'),
            ])
            ->withPublicSummaryMetrics()
            ->withCount([
                'bookings',
                'roomTypes',
            ])
            ->firstOrFail();

        return HotelResource::make($hotel);
    }

    // Crea un hotel para el propietario autenticado
    public function store(Request $request)
    {
        $validated = $request->validate($this->hotelRules());
        // El propietario se toma del token; no se acepta owner_user_id en el body
        $validated['owner_user_id'] = $request->user()->id;

        $hotel = Hotel::query()->create($this->hotelPayload($validated));

        $this->syncServices($hotel, $validated);
        $this->storeImages($hotel, $validated);
        $hotel->load([
            'coverImage',
            'images' => fn ($query) => $query->orderBy('sort_order'),
            'services' => fn ($query) => $query->orderBy('name'),
        ]);

        return HotelResource::make($hotel)
            ->response()
            ->setStatusCode(201);
    }

    // Actualiza un hotel del propietario autenticado
    public function update(Request $request, int $hotelId): HotelResource
    {
        $validated = $request->validate($this->hotelRules(required: false));
        // La actualización queda limitada a hoteles del owner autenticado
        $ownerUserId = $request->user()->id;

        $hotel = Hotel::query()
            ->where('owner_user_id', $ownerUserId)
            ->findOrFail($hotelId);

        $payload = $this->hotelPayload($validated, $hotel);

        if (isset($validated['name']) && $validated['name'] !== $hotel->name) {
            $payload['slug'] = $this->generateUniqueSlug($validated['name'], $hotel->id);
        }

        $hotel->update($payload);
        $this->syncServices($hotel, $validated);
        $this->storeImages($hotel, $validated);
        $hotel->load([
            'coverImage',
            'images' => fn ($query) => $query->orderBy('sort_order'),
            'services' => fn ($query) => $query->orderBy('name'),
        ]);

        return HotelResource::make($hotel);
    }

    // Añade una imagen a un hotel del propietario
    public function images(Request $request, int $hotelId): HotelResource
    {
        $validated = $request->validate($this->imageRules());
        $ownerUserId = $request->user()->id;

        $hotel = Hotel::query()
            ->where('owner_user_id', $ownerUserId)
            ->findOrFail($hotelId);

        $this->storeImages($hotel, $validated);
        $hotel->load([
            'coverImage',
            'images' => fn ($query) => $query->orderBy('sort_order'),
            'services' => fn ($query) => $query->orderBy('name'),
        ]);

        return HotelResource::make($hotel);
    }

    // Devuelve las reglas de validación de hoteles
    private function hotelRules(bool $required = true): array
    {
        $presence = $required ? 'required' : 'sometimes';

        return [
            'name' => [$presence, 'string', 'max:180'],
            'description' => ['nullable', 'string'],
            'stars' => [$presence, 'integer', 'min:1', 'max:5'],
            'country' => [$presence, 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:100'],
            'city' => [$presence, 'string', 'max:100'],
            'address' => [$presence, 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'contact_email' => ['nullable', 'email', 'max:150'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'check_in_time' => ['nullable', 'date_format:H:i'],
            'check_out_time' => ['nullable', 'date_format:H:i'],
            'cancellation_policy' => ['nullable', 'string'],
            'tax_rate_percent' => ['nullable', 'numeric', 'between:0,100'],
            'discount_rate_percent' => ['nullable', 'numeric', 'between:0,100'],
            'pets_allowed' => ['nullable', 'boolean'],
            'smoking_allowed' => ['nullable', 'boolean'],
            'status' => ['nullable', 'string', 'in:draft,published,inactive'],
            'service_ids' => ['sometimes', 'array'],
            'service_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('services', 'id')->where(fn ($query) => $query
                    ->where('is_active', true)
                    ->whereIn('scope', ['hotel', 'both'])),
            ],
            ...$this->imageRules(),
        ];
    }

    // Devuelve las reglas de validación de imágenes
    private function imageRules(): array
    {
        return $this->images->validationRules();
    }

    // Sincroniza los servicios asignados al hotel
    private function syncServices(Hotel $hotel, array $validated): void
    {
        if (! array_key_exists('service_ids', $validated)) {
            return;
        }

        $hotel->services()->sync($validated['service_ids']);
    }

    // Guarda las imágenes enviadas para el hotel
    private function storeImages(Hotel $hotel, array $validated): void
    {
        $this->images->store($hotel, $validated, 'hotels');
    }

    // Prepara los datos permitidos para guardar el hotel
    private function hotelPayload(array $validated, ?Hotel $hotel = null): array
    {
        $payload = collect([
            'owner_user_id' => $validated['owner_user_id'] ?? $hotel?->owner_user_id,
            'name' => $validated['name'] ?? $hotel?->name,
            'description' => $validated['description'] ?? $hotel?->description,
            'stars' => $validated['stars'] ?? $hotel?->stars,
            'country' => $validated['country'] ?? $hotel?->country,
            'region' => $validated['region'] ?? $hotel?->region,
            'city' => $validated['city'] ?? $hotel?->city,
            'address' => $validated['address'] ?? $hotel?->address,
            'postal_code' => $validated['postal_code'] ?? $hotel?->postal_code,
            'latitude' => $validated['latitude'] ?? $hotel?->latitude,
            'longitude' => $validated['longitude'] ?? $hotel?->longitude,
            'contact_email' => $validated['contact_email'] ?? $hotel?->contact_email,
            'contact_phone' => $validated['contact_phone'] ?? $hotel?->contact_phone,
            'check_in_time' => $validated['check_in_time'] ?? $hotel?->check_in_time,
            'check_out_time' => $validated['check_out_time'] ?? $hotel?->check_out_time,
            'cancellation_policy' => $validated['cancellation_policy'] ?? $hotel?->cancellation_policy,
            'tax_rate_percent' => $validated['tax_rate_percent'] ?? $hotel?->tax_rate_percent ?? 10,
            'discount_rate_percent' => $validated['discount_rate_percent'] ?? $hotel?->discount_rate_percent ?? 0,
            'pets_allowed' => $validated['pets_allowed'] ?? $hotel?->pets_allowed ?? false,
            'smoking_allowed' => $validated['smoking_allowed'] ?? $hotel?->smoking_allowed ?? false,
            'status' => $validated['status'] ?? $hotel?->status ?? 'draft',
        ])->filter(fn ($value) => $value !== null)->all();

        if (! $hotel) {
            $payload['slug'] = $this->generateUniqueSlug($validated['name']);
        }

        return $payload;
    }

    // Genera un slug único para el hotel
    private function generateUniqueSlug(string $name, ?int $ignoreHotelId = null): string
    {
        return $this->hotelSlugs->generate($name, $ignoreHotelId);
    }
}
