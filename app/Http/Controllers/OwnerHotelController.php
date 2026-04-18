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

    public function store(Request $request)
    {
        $validated = $request->validate([
            'owner_user_id' => ['required', 'integer', 'exists:users,id'],
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string'],
            'stars' => ['required', 'integer', 'min:1', 'max:5'],
            'country' => ['required', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'address' => ['required', 'string', 'max:255'],
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
        ]);

        // Crea un hotel del propietario indicado como borrador por defecto
        $hotel = Hotel::query()->create([
            'owner_user_id' => $validated['owner_user_id'],
            'name' => $validated['name'],
            'slug' => $this->generateUniqueSlug($validated['name']),
            'description' => $validated['description'] ?? null,
            'stars' => $validated['stars'],
            'country' => $validated['country'],
            'region' => $validated['region'] ?? null,
            'city' => $validated['city'],
            'address' => $validated['address'],
            'postal_code' => $validated['postal_code'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'contact_email' => $validated['contact_email'] ?? null,
            'contact_phone' => $validated['contact_phone'] ?? null,
            'check_in_time' => $validated['check_in_time'] ?? null,
            'check_out_time' => $validated['check_out_time'] ?? null,
            'cancellation_policy' => $validated['cancellation_policy'] ?? null,
            'pets_allowed' => $validated['pets_allowed'] ?? false,
            'smoking_allowed' => $validated['smoking_allowed'] ?? false,
            'status' => $validated['status'] ?? 'draft',
        ]);

        $hotel->load('coverImage');

        return (new HotelResource($hotel))
            ->response()
            ->setStatusCode(201);
    }

    private function generateUniqueSlug(string $name): string
    {
        // Evita colisiones cuando un propietario repite nombres de hotel
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 2;

        while (Hotel::query()->where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
