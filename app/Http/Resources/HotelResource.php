<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HotelResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'stars' => $this->stars,
            'status' => $this->status,
            'location' => [
                'country' => $this->country,
                'region' => $this->region,
                'city' => $this->city,
                'address' => $this->address,
                'postal_code' => $this->postal_code,
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
            ],
            'contact' => [
                'email' => $this->contact_email,
                'phone' => $this->contact_phone,
                'address' => $this->address,
            ],
            'check_in_time' => $this->check_in_time?->format('H:i:s'),
            'check_out_time' => $this->check_out_time?->format('H:i:s'),
            'cancellation_policy' => $this->cancellation_policy,
            'pets_allowed' => $this->pets_allowed,
            'smoking_allowed' => $this->smoking_allowed,
            'starting_price' => $this->room_types_min_base_price,
            'average_rating' => $this->average_rating !== null
                ? round((float) $this->average_rating, 1)
                : null,
            'reviews_count' => $this->reviews_count,
            'bookings_count' => $this->whenCounted('bookings'),
            'room_types_count' => $this->whenCounted('roomTypes'),
            'cover_image' => $this->coverImage ? [
                'id' => $this->coverImage->id,
                'url' => $this->coverImage->image_url,
                'alt_text' => $this->coverImage->alt_text,
            ] : null,
            'images' => $this->whenLoaded('images', fn () => $this->images->map(fn ($image) => [
                'id' => $image->id,
                'url' => $image->image_url,
                'alt_text' => $image->alt_text,
                'is_cover' => $image->is_cover,
                'sort_order' => $image->sort_order,
            ])),
            'services' => $this->whenLoaded('services', fn () => $this->services->map(fn ($service) => [
                'id' => $service->id,
                'name' => $service->name,
                'slug' => $service->slug,
                'icon' => $service->icon,
                'category' => $service->category,
                'scope' => $service->scope,
            ])),
            'room_types' => $this->whenLoaded('roomTypes', fn () => $this->roomTypes->map(fn ($roomType) => [
                'id' => $roomType->id,
                'name' => $roomType->name,
                'description' => $roomType->description,
                'capacity_adults' => $roomType->capacity_adults,
                'capacity_children' => $roomType->capacity_children,
                'size_m2' => $roomType->size_m2,
                'bed_type' => $roomType->bed_type,
                'base_price' => $roomType->base_price,
                'total_units' => $roomType->total_units,
                'status' => $roomType->status,
                'images' => $roomType->relationLoaded('images')
                    ? $roomType->images->map(fn ($image) => [
                        'id' => $image->id,
                        'url' => $image->image_url,
                        'alt_text' => $image->alt_text,
                        'is_cover' => $image->is_cover,
                        'sort_order' => $image->sort_order,
                    ])
                    : [],
                'services' => $roomType->relationLoaded('services')
                    ? $roomType->services->map(fn ($service) => [
                        'id' => $service->id,
                        'name' => $service->name,
                        'slug' => $service->slug,
                        'icon' => $service->icon,
                        'category' => $service->category,
                        'scope' => $service->scope,
                    ])
                    : [],
            ])),
        ];
    }
}
