<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoomTypeResource extends JsonResource
{
    private const DEFAULT_PRICE_CURRENCY = 'EUR';

    private const PRICE_CURRENCY_SYMBOL = '€';

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
            'currency' => $this->currency ?? self::DEFAULT_PRICE_CURRENCY,
            'currency_symbol' => $this->currencySymbol($this->currency ?? self::DEFAULT_PRICE_CURRENCY),
            'total_units' => $this->total_units,
            'free_cancellation_hours' => $this->free_cancellation_hours,
            'status' => $this->status,
            'availability_count' => $this->whenCounted('availability'),
            'bookings_count' => $this->whenCounted('bookings'),
            'cover_image' => $this->whenLoaded('coverImage', fn () => $this->coverImage ? [
                'id' => $this->coverImage->id,
                'url' => $this->coverImage->image_url,
                'alt_text' => $this->coverImage->alt_text,
            ] : null),
            'images' => $this->whenLoaded('images', fn () => $this->mapImages($this->images)),
            'services' => $this->whenLoaded('services', fn () => $this->mapServices($this->services)),
        ];
    }

    private function currencySymbol(?string $currency): ?string
    {
        return $currency === self::DEFAULT_PRICE_CURRENCY ? self::PRICE_CURRENCY_SYMBOL : null;
    }

    // Transforma la lista de imágenes al formato de respuesta
    private function mapImages($images)
    {
        return $images->map(fn ($image) => [
            'id' => $image->id,
            'url' => $image->image_url,
            'alt_text' => $image->alt_text,
            'is_cover' => $image->is_cover,
            'sort_order' => $image->sort_order,
        ]);
    }

    // Transforma la lista de servicios al formato de respuesta
    private function mapServices($services)
    {
        return $services->map(fn ($service) => [
            'id' => $service->id,
            'name' => $service->name,
            'slug' => $service->slug,
            'icon' => $service->icon,
            'category' => $service->category,
            'scope' => $service->scope,
        ]);
    }
}
