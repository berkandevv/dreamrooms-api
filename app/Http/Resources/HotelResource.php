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
            'location' => [
                'country' => $this->country,
                'region' => $this->region,
                'city' => $this->city,
                'address' => $this->address,
                'postal_code' => $this->postal_code,
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
            ],
            'check_in_time' => $this->check_in_time?->format('H:i:s'),
            'check_out_time' => $this->check_out_time?->format('H:i:s'),
            'pets_allowed' => $this->pets_allowed,
            'smoking_allowed' => $this->smoking_allowed,
            'starting_price' => $this->room_types_min_base_price,
            'average_rating' => $this->average_rating !== null
                ? round((float) $this->average_rating, 1)
                : null,
            'reviews_count' => $this->reviews_count,
            'cover_image' => $this->coverImage ? [
                'id' => $this->coverImage->id,
                'url' => $this->coverImage->image_url,
                'alt_text' => $this->coverImage->alt_text,
            ] : null,
        ];
    }
}
