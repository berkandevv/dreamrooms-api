<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoomTypeResource extends JsonResource
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
            'hotel_id' => $this->hotel_id,
            'name' => $this->name,
            'description' => $this->description,
            'capacity_adults' => $this->capacity_adults,
            'capacity_children' => $this->capacity_children,
            'size_m2' => $this->size_m2,
            'bed_type' => $this->bed_type,
            'base_price' => $this->base_price,
            'total_units' => $this->total_units,
            'status' => $this->status,
            'availability_count' => $this->whenCounted('availability'),
            'bookings_count' => $this->whenCounted('bookings'),
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
        ];
    }
}
