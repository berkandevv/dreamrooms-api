<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
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
            'booking_reference' => $this->booking_reference,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'hotel' => [
                'id' => $this->hotel_id,
                'name' => $this->hotel_name,
                'slug' => $this->whenLoaded('hotel', fn () => $this->hotel?->slug),
            ],
            'room_type' => [
                'id' => $this->room_type_id,
                'name' => $this->room_type_name,
            ],
            'customer' => [
                'name' => $this->customer_name,
                'email' => $this->customer_email,
                'phone' => $this->customer_phone,
            ],
            'stay' => [
                'check_in' => $this->check_in?->toDateString(),
                'check_out' => $this->check_out?->toDateString(),
                'nights' => $this->nights,
                'adults_count' => $this->adults_count,
                'children_count' => $this->children_count,
                'units_booked' => $this->units_booked,
            ],
            'amounts' => [
                'subtotal' => $this->subtotal_amount,
                'taxes' => $this->taxes_amount,
                'discount' => $this->discount_amount,
                'total' => $this->total_amount,
                'currency' => $this->currency,
            ],
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ]),
            'guests' => $this->whenLoaded('guests', fn () => $this->guests->map(fn ($guest) => [
                'id' => $guest->id,
                'full_name' => $guest->full_name,
                'document_type' => $guest->document_type,
                'document_number' => $guest->document_number,
                'birth_date' => $guest->birth_date?->toDateString(),
                'is_primary' => $guest->is_primary,
            ])),
            'payments' => $this->whenLoaded('payments', fn () => $this->payments->map(fn ($payment) => [
                'id' => $payment->id,
                'provider' => $payment->provider,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'status' => $payment->status,
                'transaction_reference' => $payment->transaction_reference,
                'metadata' => $this->when($request->user()?->hasRole(['owner', 'admin']), $payment->metadata),
                'paid_at' => $payment->paid_at?->toISOString(),
            ])),
            'notes' => $this->notes,
            'booked_at' => $this->booked_at?->toISOString(),
            'expires_at' => $this->expires_at?->toISOString(),
            'confirmed_at' => $this->confirmed_at?->toISOString(),
            'cancelled_at' => $this->cancelled_at?->toISOString(),
        ];
    }
}
