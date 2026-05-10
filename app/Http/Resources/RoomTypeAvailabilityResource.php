<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoomTypeAvailabilityResource extends JsonResource
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
            'room_type_id' => $this->room_type_id,
            'date' => $this->date?->toDateString(),
            'available_units' => $this->available_units,
            'price' => $this->price,
            'currency' => $this->currency ?? self::DEFAULT_PRICE_CURRENCY,
            'currency_symbol' => $this->currencySymbol($this->currency ?? self::DEFAULT_PRICE_CURRENCY),
            'status' => $this->status,
            'min_stay_nights' => $this->min_stay_nights,
        ];
    }

    private function currencySymbol(?string $currency): ?string
    {
        return $currency === self::DEFAULT_PRICE_CURRENCY ? self::PRICE_CURRENCY_SYMBOL : null;
    }
}
