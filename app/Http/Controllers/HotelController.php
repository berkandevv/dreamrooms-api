<?php

namespace App\Http\Controllers;

use App\Http\Resources\HotelResource;
use App\Http\Resources\ReviewResource;
use App\Models\Hotel;

class HotelController extends Controller
{
    /**
     * List published hotels
     *
     * Returns the public hotel catalog with location, cover image, starting price,
     * average rating, and review count
     */
    public function index()
    {
        // Devuelve el catálogo público de hoteles con los datos necesarios para listados
        $hotels = Hotel::query()
            ->where('status', 'published')
            ->with('coverImage')
            ->withMin('roomTypes', 'base_price')
            ->withAvg([
                'reviews as average_rating' => fn ($query) => $query->where('status', 'published'),
            ], 'rating')
            ->withCount([
                'reviews as reviews_count' => fn ($query) => $query->where('status', 'published'),
            ])
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
        $hotel = Hotel::query()
            ->where('status', 'published')
            ->where('slug', $slug)
            ->with([
                'coverImage',
                'images' => fn ($query) => $query->orderBy('sort_order'),
                'services' => fn ($query) => $query->where('is_active', true)->orderBy('name'),
                'roomTypes' => fn ($query) => $query->where('status', 'active')->orderBy('base_price'),
                'roomTypes.images' => fn ($query) => $query->orderByDesc('is_cover')->orderBy('sort_order'),
                'roomTypes.services' => fn ($query) => $query->where('is_active', true)->orderBy('name'),
            ])
            ->withMin('roomTypes', 'base_price')
            ->withAvg([
                'reviews as average_rating' => fn ($query) => $query->where('status', 'published'),
            ], 'rating')
            ->withCount([
                'reviews as reviews_count' => fn ($query) => $query->where('status', 'published'),
            ])
            ->firstOrFail();

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
}
