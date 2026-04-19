<?php

namespace App\Http\Controllers;

use App\Http\Resources\HotelResource;
use App\Models\Hotel;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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

    public function show(Request $request, int $hotelId): HotelResource
    {
        $validated = $request->validate([
            'owner_user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        // Devuelve el detalle completo de un hotel del propietario indicado
        $hotel = Hotel::query()
            ->where('owner_user_id', $validated['owner_user_id'])
            ->where('id', $hotelId)
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

    public function store(Request $request)
    {
        $validated = $request->validate($this->hotelRules());

        // Crea un hotel del propietario indicado como borrador por defecto
        $hotel = Hotel::query()->create($this->hotelPayload($validated));

        $hotel->load('coverImage');

        return (new HotelResource($hotel))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, int $hotelId): HotelResource
    {
        $validated = $request->validate($this->hotelRules(required: false));

        // Actualiza solo hoteles que pertenecen al propietario indicado
        $hotel = Hotel::query()
            ->where('owner_user_id', $validated['owner_user_id'])
            ->findOrFail($hotelId);

        $payload = $this->hotelPayload($validated, $hotel);

        if (isset($validated['name']) && $validated['name'] !== $hotel->name) {
            $payload['slug'] = $this->generateUniqueSlug($validated['name'], $hotel->id);
        }

        $hotel->update($payload);
        $hotel->load('coverImage');

        return new HotelResource($hotel);
    }

    private function hotelRules(bool $required = true): array
    {
        $presence = $required ? 'required' : 'sometimes';

        return [
            'owner_user_id' => ['required', 'integer', 'exists:users,id'],
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
            'pets_allowed' => ['nullable', 'boolean'],
            'smoking_allowed' => ['nullable', 'boolean'],
            'status' => ['nullable', 'string', 'in:draft,published,inactive'],
        ];
    }

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
            'pets_allowed' => $validated['pets_allowed'] ?? $hotel?->pets_allowed ?? false,
            'smoking_allowed' => $validated['smoking_allowed'] ?? $hotel?->smoking_allowed ?? false,
            'status' => $validated['status'] ?? $hotel?->status ?? 'draft',
        ])->filter(fn ($value) => $value !== null)->all();

        if (! $hotel) {
            $payload['slug'] = $this->generateUniqueSlug($validated['name']);
        }

        return $payload;
    }

    private function generateUniqueSlug(string $name, ?int $ignoreHotelId = null): string
    {
        // Evita colisiones cuando un propietario repite nombres de hotel
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 2;

        while (Hotel::query()
            ->where('slug', $slug)
            ->when($ignoreHotelId, fn ($query) => $query->whereKeyNot($ignoreHotelId))
            ->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
