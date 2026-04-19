<?php

namespace App\Http\Controllers;

use App\Http\Resources\BookingResource;
use App\Models\Booking;
use Illuminate\Http\Request;

class OwnerBookingController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'owner_user_id' => ['required', 'integer', 'exists:users,id'],
            'hotel_id' => ['nullable', 'integer', 'exists:hotels,id'],
            'status' => ['nullable', 'string', 'in:pending,confirmed,cancelled,completed'],
            'payment_status' => ['nullable', 'string', 'in:pending,partial,paid,failed,refunded'],
        ]);

        // Lista solo las reservas de hoteles que pertenecen al propietario indicado
        $bookings = Booking::query()
            ->whereHas('hotel', fn ($query) => $query->where('owner_user_id', $validated['owner_user_id']))
            ->when($validated['hotel_id'] ?? null, fn ($query, $hotelId) => $query->where('hotel_id', $hotelId))
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($validated['payment_status'] ?? null, fn ($query, $paymentStatus) => $query->where('payment_status', $paymentStatus))
            ->with([
                'user:id,name,email',
                'hotel:id,name,slug',
                'roomType:id,name',
                'guests',
                'payments',
            ])
            ->orderByDesc('booked_at')
            ->orderByDesc('id')
            ->get();

        return BookingResource::collection($bookings);
    }
}
