<?php

namespace App\Http\Controllers;

use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\Payment;
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
            'hotel_id' => ['nullable', 'integer', 'exists:hotels,id'],
            'status' => ['nullable', 'string', 'in:pending,confirmed,cancelled,completed'],
            'payment_status' => ['nullable', 'string', 'in:pending,partial,paid,failed,refunded'],
        ]);
        // El owner solo lista reservas de hoteles que le pertenecen
        $ownerUserId = $request->user()->id;

        $bookings = Booking::query()
            ->whereHas('hotel', fn ($query) => $query->where('owner_user_id', $ownerUserId))
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
        // La reserva debe pertenecer a un hotel del owner autenticado
        $ownerUserId = $request->user()->id;

        $booking = Booking::query()
            ->whereKey($bookingId)
            ->whereHas('hotel', fn ($query) => $query->where('owner_user_id', $ownerUserId))
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
            'status' => ['required', 'string', 'in:pending,confirmed,cancelled,completed'],
        ]);
        // El propietario no llega desde el request; se deriva del token
        $validated['owner_user_id'] = $request->user()->id;

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

    public function payments(Request $request, int $bookingId)
    {
        $validated = $request->validate([
            'provider' => ['required', 'string', 'in:stripe,paypal,manual'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'size:3'],
            'status' => ['nullable', 'string', 'in:pending,authorized,paid,failed,refunded,partially_refunded'],
            'transaction_reference' => ['nullable', 'string', 'max:100'],
            'metadata' => ['nullable', 'array'],
        ]);

        $booking = DB::transaction(fn (): Booking => $this->registerPayment(
            $bookingId,
            $request->user()->id,
            $validated,
        ));

        $booking->load([
            'user:id,name,email',
            'hotel:id,name,slug',
            'roomType:id,name',
            'guests',
            'payments',
        ]);

        return (new BookingResource($booking))
            ->response()
            ->setStatusCode(201);
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

        if ($newStatus === 'confirmed' && $this->bookingExpired($booking)) {
            throw ValidationException::withMessages([
                'booking' => ['This booking has expired and cannot be confirmed.'],
            ]);
        }

        if ($newStatus === 'completed' && $booking->payment_status !== 'paid') {
            throw ValidationException::withMessages([
                'payment_status' => ['Only paid bookings can be completed.'],
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

    private function registerPayment(int $bookingId, int $ownerUserId, array $validated): Booking
    {
        // Registra pagos solo sobre reservas de hoteles del owner autenticado
        $booking = Booking::query()
            ->whereKey($bookingId)
            ->whereHas('hotel', fn ($query) => $query->where('owner_user_id', $ownerUserId))
            ->with('payments')
            ->lockForUpdate()
            ->firstOrFail();

        if ($booking->status === 'cancelled') {
            throw ValidationException::withMessages([
                'booking' => ['Cancelled bookings cannot receive payments.'],
            ]);
        }

        if ($this->bookingExpired($booking)) {
            throw ValidationException::withMessages([
                'booking' => ['This booking has expired and cannot receive payments.'],
            ]);
        }

        $paymentStatus = $validated['status'] ?? 'paid';
        $amount = round((float) $validated['amount'], 2);
        $paidAmount = (float) Payment::query()
            ->where('booking_id', $booking->id)
            ->where('status', 'paid')
            ->sum('amount');
        $remainingAmount = round((float) $booking->total_amount - $paidAmount, 2);

        if ($paymentStatus === 'paid' && $amount > $remainingAmount) {
            throw ValidationException::withMessages([
                'amount' => ['The payment amount cannot exceed the remaining booking total.'],
            ]);
        }

        Payment::query()->create([
            'booking_id' => $booking->id,
            'provider' => $validated['provider'],
            'amount' => $amount,
            'currency' => strtoupper($validated['currency'] ?? $booking->currency),
            'status' => $paymentStatus,
            'transaction_reference' => $validated['transaction_reference'] ?? null,
            'paid_at' => $paymentStatus === 'paid' ? now() : null,
            'metadata' => $validated['metadata'] ?? null,
        ]);

        $this->refreshBookingPaymentStatus($booking->id, (float) $booking->total_amount, $paymentStatus);

        return Booking::query()->findOrFail($bookingId);
    }

    private function refreshBookingPaymentStatus(int $bookingId, float $totalAmount, string $latestPaymentStatus): void
    {
        // Sincroniza el estado agregado de pago usando los pagos confirmados
        $paidAmount = (float) Payment::query()
            ->where('booking_id', $bookingId)
            ->where('status', 'paid')
            ->sum('amount');

        $paymentStatus = match (true) {
            $paidAmount >= $totalAmount => 'paid',
            $paidAmount > 0 => 'partial',
            $latestPaymentStatus === 'refunded' => 'refunded',
            $latestPaymentStatus === 'failed' => 'failed',
            default => 'pending',
        };

        Booking::query()
            ->whereKey($bookingId)
            ->update(['payment_status' => $paymentStatus]);
    }

    private function bookingExpired($booking): bool
    {
        return $booking->status === 'pending'
            && $booking->expires_at !== null
            && $booking->expires_at->isPast();
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
