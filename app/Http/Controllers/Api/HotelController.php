<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\HotelResource;
use App\Http\Resources\ReviewResource;
use App\Models\Hotel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class HotelController extends Controller
{
    // Lista los hoteles publicados
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
            ->published()
            ->when($validated['country'] ?? null, fn ($query, $country) => $query->where('country', 'like', "%{$country}%"))
            ->when($validated['city'] ?? null, fn ($query, $city) => $query->where('city', 'like', "%{$city}%"))
            ->when($validated['stars'] ?? null, fn ($query, $stars) => $query->where('stars', $stars))
            ->when(isset($validated['pets_allowed']), fn ($query) => $query->where('pets_allowed', $validated['pets_allowed']))
            ->when(isset($validated['smoking_allowed']), fn ($query) => $query->where('smoking_allowed', $validated['smoking_allowed']))
            ->when(
                isset($validated['min_price']) || isset($validated['max_price']),
                fn (Builder $query) => $this->applyRoomTypePriceFilter($query, $validated)
            )
            ->with($this->hotelListRelations())
            ->withPublicSummaryMetrics();

        $hotels = $query
            ->orderBy('id')
            ->get();

        return HotelResource::collection($hotels);
    }

    // Muestra el detalle público de un hotel
    public function show(string $slug): HotelResource
    {
        // Devuelve el detalle público de un hotel publicado por su slug
        $query = Hotel::query()
            ->published()
            ->where('slug', $slug)
            ->with($this->hotelDetailRelations())
            ->withPublicSummaryMetrics();

        $hotel = $query->firstOrFail();

        return HotelResource::make($hotel);
    }

    // Lista las reseñas publicadas de un hotel
    public function reviews(string $slug)
    {
        // Devuelve las reseñas publicadas de un hotel publicado por su slug
        $hotel = Hotel::query()
            ->published()
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
        return [
            'coverImage',
            'services' => fn ($query) => $query->where('is_active', true)->orderBy('name'),
        ];
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
