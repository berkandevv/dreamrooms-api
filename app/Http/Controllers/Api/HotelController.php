<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\HotelResource;
use App\Http\Resources\ReviewResource;
use App\Models\Hotel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class HotelController extends Controller
{
    /**
     * List published hotels
     *
     * Returns the public hotel catalog with location, cover image, starting price,
     * average rating, and review count
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'country' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'stars' => ['nullable', 'integer', 'min:1', 'max:5'],
            'pets_allowed' => ['nullable', 'boolean'],
            'smoking_allowed' => ['nullable', 'boolean'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0', 'gte:min_price'],
        ]);

        // Devuelve el catálogo público de hoteles con los datos necesarios para listados
        $query = Hotel::query()
            ->where('status', 'published')
            ->when($validated['country'] ?? null, fn ($query, $country) => $query->where('country', 'like', "%{$country}%"))
            ->when($validated['city'] ?? null, fn ($query, $city) => $query->where('city', 'like', "%{$city}%"))
            ->when($validated['stars'] ?? null, fn ($query, $stars) => $query->where('stars', $stars))
            ->when(isset($validated['pets_allowed']), fn ($query) => $query->where('pets_allowed', $validated['pets_allowed']))
            ->when(isset($validated['smoking_allowed']), fn ($query) => $query->where('smoking_allowed', $validated['smoking_allowed']))
            ->when(
                isset($validated['min_price']) || isset($validated['max_price']),
                fn (Builder $query) => $this->applyRoomTypePriceFilter($query, $validated)
            )
            ->with($this->hotelListRelations());

        $this->applyHotelSummaryMetrics($query);

        $hotels = $query
            ->orderBy('id')
            ->get();

        return HotelResource::collection($hotels);
    }

    /**
     * Show a published hotel
     *
     * Returns the full public detail for a hotel, including images, services,
     * room types, room images, and room services
     */
    public function show(string $slug): HotelResource
    {
        // Devuelve el detalle público de un hotel publicado por su slug
        $query = Hotel::query()
            ->where('status', 'published')
            ->where('slug', $slug)
            ->with($this->hotelDetailRelations());

        $this->applyHotelSummaryMetrics($query);

        $hotel = $query->firstOrFail();

        return new HotelResource($hotel);
    }

    /**
     * List published hotel reviews
     *
     * Returns the public reviews for a published hotel ordered from newest to oldest
     */
    public function reviews(string $slug)
    {
        // Devuelve las reseñas publicadas de un hotel publicado por su slug
        $hotel = Hotel::query()
            ->where('status', 'published')
            ->where('slug', $slug)
            ->firstOrFail();

        $reviews = $hotel->reviews()
            ->where('status', 'published')
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->get();

        return ReviewResource::collection($reviews);
    }

    // Helpers de consulta para el catálogo público de hoteles

    // Devuelve las relaciones necesarias para el listado público de hoteles
    private function hotelListRelations(): array
    {
        return ['coverImage'];
    }

    // Devuelve las relaciones necesarias para el detalle público de un hotel
    private function hotelDetailRelations(): array
    {
        return [
            'coverImage',
            'images' => fn ($query) => $query->orderBy('sort_order'),
            'services' => fn ($query) => $query->where('is_active', true)->orderBy('name'),
            'roomTypes' => fn ($query) => $query->where('status', 'active')->orderBy('base_price'),
            'roomTypes.coverImage',
            'roomTypes.images' => fn ($query) => $query->orderByDesc('is_cover')->orderBy('sort_order'),
            'roomTypes.services' => fn ($query) => $query->where('is_active', true)->orderBy('name'),
        ];
    }

    // Añade al query las métricas resumen que usan los resources de hotel
    private function applyHotelSummaryMetrics(Builder $query): void
    {
        $query
            ->withMin([
                'roomTypes' => fn ($roomTypeQuery) => $roomTypeQuery->where('status', 'active'),
            ], 'base_price')
            ->withAvg([
                'reviews as average_rating' => fn ($reviewQuery) => $reviewQuery->where('status', 'published'),
            ], 'rating')
            ->withCount([
                'reviews as reviews_count' => fn ($reviewQuery) => $reviewQuery->where('status', 'published'),
            ]);
    }

    // Aplica el filtro de precio usando solo tipos de habitación activos
    private function applyRoomTypePriceFilter(Builder $query, array $validated): void
    {
        $query->whereHas('roomTypes', function (Builder $roomTypeQuery) use ($validated): void {
            $roomTypeQuery
                ->where('status', 'active')
                ->when(
                    isset($validated['min_price']),
                    fn (Builder $priceQuery) => $priceQuery->where('base_price', '>=', $validated['min_price'])
                )
                ->when(
                    isset($validated['max_price']),
                    fn (Builder $priceQuery) => $priceQuery->where('base_price', '<=', $validated['max_price'])
                );
        });
    }
}
