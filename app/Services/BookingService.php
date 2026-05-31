<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\RoomTypeAvailability;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingService
{
    // Inicializa el servicio de disponibilidad
    public function __construct(private readonly RoomTypeAvailabilityService $availabilityService) {}

    // Cancela una reserva del cliente autenticado
    public function cancelForCustomer(int $bookingId, int $customerUserId): Booking
    {
        return DB::transaction(function () use ($bookingId, $customerUserId): Booking {
            $booking = Booking::query()
                ->where('user_id', $customerUserId)
                ->lockForUpdate()
                ->findOrFail($bookingId);

            return $this->cancelLockedBooking($booking);
        });
    }

    // Cancela las reservas pendientes que han caducado
    public function expirePendingBookings(): void
    {
        Booking::query()
            ->where('status', 'pending')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->pluck('id')
            ->each(fn (int $bookingId) => DB::transaction(function () use ($bookingId): void {
                $booking = Booking::query()
                    ->lockForUpdate()
                    ->find($bookingId);

                if (! $booking || $booking->status !== 'pending') {
                    return;
                }

                $this->cancelLockedBooking($booking);
            }));
    }

    // Cambia el estado de una reserva
    public function changeStatus(int $bookingId, string $newStatus, ?int $ownerUserId = null): Booking
    {
        return DB::transaction(function () use ($bookingId, $newStatus, $ownerUserId): Booking {
            $booking = $this->lockBooking($bookingId, $ownerUserId, ['roomType']);

            if ($booking->status === $newStatus) {
                return $booking;
            }

            if ($newStatus === 'confirmed' && $booking->status === 'pending' && $booking->expires_at !== null && $booking->expires_at->isPast()) {
                throw ValidationException::withMessages([
                    'status' => ['This booking has expired and cannot be confirmed.'],
                ]);
            }

            if ($newStatus === 'completed' && $booking->payment_status !== 'paid') {
                throw ValidationException::withMessages([
                    'status' => ['Only paid bookings can be completed.'],
                ]);
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
        });
    }

    // Registra un pago manual de una reserva
    public function registerPayment(int $bookingId, array $validated, ?int $ownerUserId = null): Booking
    {
        return DB::transaction(function () use ($bookingId, $validated, $ownerUserId): Booking {
            $booking = $this->lockBooking($bookingId, $ownerUserId);
            $paymentStatus = $validated['status'] ?? 'paid';

            if ($booking->payment_method !== 'hotel') {
                throw ValidationException::withMessages([
                    'payment' => ['Only pay-at-hotel bookings can receive manual owner payments.'],
                ]);
            }

            if ($booking->status === 'cancelled' && $paymentStatus !== 'refunded') {
                throw ValidationException::withMessages([
                    'amount' => ['Cancelled bookings can only receive refunded payments.'],
                ]);
            }

            if ($booking->status === 'pending' && $booking->expires_at !== null && $booking->expires_at->isPast()) {
                throw ValidationException::withMessages([
                    'amount' => ['This booking has expired and cannot receive payments.'],
                ]);
            }

            $amount = round((float) $validated['amount'], 2);

            if ($paymentStatus === 'paid' && $amount !== round((float) $booking->total_amount, 2)) {
                throw ValidationException::withMessages([
                    'amount' => ['The payment amount must match the full booking total. Partial payments are not allowed.'],
                ]);
            }

            if ($paymentStatus === 'paid' && $booking->payment_status === 'paid') {
                throw ValidationException::withMessages([
                    'payment' => ['This booking is already paid.'],
                ]);
            }

            Payment::query()->create([
                'booking_id' => $booking->id,
                'provider' => $validated['provider'] ?? 'manual',
                'amount' => $amount,
                'currency' => strtoupper($validated['currency'] ?? $booking->currency),
                'status' => $paymentStatus,
                'transaction_reference' => $validated['transaction_reference'] ?? null,
                'paid_at' => $paymentStatus === 'paid' ? now() : null,
                'metadata' => $validated['metadata'] ?? null,
            ]);

            $this->refreshPaymentStatus($booking->id, (float) $booking->total_amount, $paymentStatus);

            return Booking::query()->findOrFail($booking->id);
        });
    }

    // Devuelve las unidades reservadas al calendario
    public function restoreAvailability(Booking $booking): void
    {
        $booking->loadMissing('roomType');

        $availability = RoomTypeAvailability::query()
            ->where('room_type_id', $booking->room_type_id)
            ->whereIn('date', $this->availabilityService->buildStayDates(
                CarbonImmutable::parse($booking->check_in),
                CarbonImmutable::parse($booking->check_out),
            ))
            ->lockForUpdate()
            ->get();

        $availability->each(function (RoomTypeAvailability $day) use ($booking): void {
            $day->available_units = min(
                $day->available_units + $booking->units_booked,
                $booking->roomType->total_units,
            );
            $day->save();
        });
    }

    // Bloquea una reserva para actualizarla con seguridad
    private function lockBooking(int $bookingId, ?int $ownerUserId = null, array $with = []): Booking
    {
        /** @var Booking $booking */
        $booking = Booking::query()
            ->whereKey($bookingId)
            ->when($ownerUserId !== null, fn ($query) => $query->whereHas(
                'hotel',
                fn ($query) => $query->where('owner_user_id', $ownerUserId),
            ))
            ->with($with)
            ->lockForUpdate()
            ->firstOrFail();

        return $booking;
    }

    // Comprueba si el cambio de estado está permitido
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

    // Cancela una reserva previamente bloqueada
    private function cancelLockedBooking(Booking $booking): Booking
    {
        if ($booking->status === 'cancelled') {
            throw ValidationException::withMessages([
                'booking' => ['The booking is already cancelled.'],
            ]);
        }

        if ($booking->status === 'completed') {
            throw ValidationException::withMessages([
                'booking' => ['Completed bookings cannot be cancelled.'],
            ]);
        }

        $this->restoreAvailability($booking);

        $booking->forceFill([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ])->save();

        return $booking;
    }

    // Actualiza el estado de pago de una reserva
    private function refreshPaymentStatus(int $bookingId, float $totalAmount, string $latestPaymentStatus): void
    {
        $paidAmount = (float) Payment::query()
            ->where('booking_id', $bookingId)
            ->where('status', 'paid')
            ->sum('amount');

        $paymentStatus = match (true) {
            $latestPaymentStatus === 'refunded' => 'refunded',
            $paidAmount >= $totalAmount => 'paid',
            $latestPaymentStatus === 'failed' => 'failed',
            default => 'pending',
        };

        Booking::query()
            ->whereKey($bookingId)
            ->update(['payment_status' => $paymentStatus]);
    }
}
