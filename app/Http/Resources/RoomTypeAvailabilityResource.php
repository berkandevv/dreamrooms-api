<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoomTypeAvailabilityResource extends JsonResource
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
            'room_type_id' => $this->room_type_id,
            'date' => $this->date?->toDateString(),
            'available_units' => $this->available_units,
            'price' => $this->price,
            'status' => $this->status,
            'min_stay_nights' => $this->min_stay_nights,
        ];
    }
}
