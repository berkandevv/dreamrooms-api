<?php

namespace App\Http\Controllers;

use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\RoomTypeAvailability;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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

    public function show(Request $request, int $bookingId): BookingResource
    {
        $validated = $request->validate([
            'owner_user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        // Devuelve una reserva concreta solo si pertenece a un hotel del propietario indicado
        $booking = Booking::query()
            ->whereKey($bookingId)
            ->whereHas('hotel', fn ($query) => $query->where('owner_user_id', $validated['owner_user_id']))
            ->with([
                'user:id,name,email',
                'hotel:id,name,slug',
                'roomType:id,name',
                'guests',
                'payments',
            ])
            ->firstOrFail();

        return new BookingResource($booking);
    }

    public function updateStatus(Request $request, int $bookingId): BookingResource
    {
        $validated = $request->validate([
            'owner_user_id' => ['required', 'integer', 'exists:users,id'],
            'status' => ['required', 'string', 'in:pending,confirmed,cancelled,completed'],
        ]);

        $booking = DB::transaction(fn (): Booking => $this->changeBookingStatus($bookingId, $validated));

        $booking->load([
            'user:id,name,email',
            'hotel:id,name,slug',
            'roomType:id,name',
            'guests',
            'payments',
        ]);

        return new BookingResource($booking);
    }

    private function changeBookingStatus(int $bookingId, array $validated): Booking
    {
        // Cambia el estado respetando el propietario del hotel y las transiciones normales de reserva
        $booking = Booking::query()
            ->whereKey($bookingId)
            ->whereHas('hotel', fn ($query) => $query->where('owner_user_id', $validated['owner_user_id']))
            ->lockForUpdate()
            ->firstOrFail();

        $newStatus = $validated['status'];

        if ($booking->status === $newStatus) {
            return $booking;
        }

        $this->validateStatusTransition($booking->status, $newStatus);

        if ($newStatus === 'cancelled') {
            $this->restoreAvailability($booking);
        }

        $booking->forceFill([
            'status' => $newStatus,
            'confirmed_at' => in_array($newStatus, ['confirmed', 'completed'], true)
                ? ($booking->confirmed_at ?? now())
                : $booking->confirmed_at,
            'cancelled_at' => $newStatus === 'cancelled' ? now() : $booking->cancelled_at,
        ])->save();

        return $booking;
    }

    private function validateStatusTransition(string $currentStatus, string $newStatus): void
    {
        $allowedTransitions = [
            'pending' => ['confirmed', 'cancelled'],
            'confirmed' => ['completed', 'cancelled'],
            'cancelled' => [],
            'completed' => [],
        ];

        if (in_array($newStatus, $allowedTransitions[$currentStatus] ?? [], true)) {
            return;
        }

        throw ValidationException::withMessages([
            'status' => ["Cannot change booking status from {$currentStatus} to {$newStatus}."],
        ]);
    }

    private function restoreAvailability(Booking $booking): void
    {
        $stayDates = $this->buildStayDates(
            CarbonImmutable::parse($booking->check_in),
            CarbonImmutable::parse($booking->check_out),
        );

        RoomTypeAvailability::query()
            ->where('room_type_id', $booking->room_type_id)
            ->whereIn('date', $stayDates)
            ->lockForUpdate()
            ->increment('available_units', $booking->units_booked);
    }

    private function buildStayDates(CarbonImmutable $checkIn, CarbonImmutable $checkOut): array
    {
        $stayDates = [];

        for ($date = $checkIn; $date->lt($checkOut); $date = $date->addDay()) {
            $stayDates[] = $date->toDateString();
        }

        return $stayDates;
    }
}
