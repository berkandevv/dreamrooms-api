<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Hotel;
use App\Models\Payment;
use App\Models\RoomTypeAvailability;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('q')->toString();
        $hotelId = $request->string('hotel_id', 'all')->toString();
        $status = $request->string('status', 'all')->toString();
        $paymentStatus = $request->string('payment_status', 'all')->toString();
        $from = $request->string('from')->toString();
        $to = $request->string('to')->toString();

        $bookings = Booking::query()
            ->with(['hotel:id,name', 'roomType:id,name', 'user:id,name,email'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('booking_reference', 'like', "%{$search}%")
                        ->orWhere('customer_name', 'like', "%{$search}%")
                        ->orWhere('customer_email', 'like', "%{$search}%")
                        ->orWhere('hotel_name', 'like', "%{$search}%");
                });
            })
            ->when($hotelId !== 'all', fn ($query) => $query->where('hotel_id', $hotelId))
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->when($paymentStatus !== 'all', fn ($query) => $query->where('payment_status', $paymentStatus))
            ->when($from !== '', fn ($query) => $query->whereDate('check_in', '>=', $from))
            ->when($to !== '', fn ($query) => $query->whereDate('check_in', '<=', $to))
            ->orderByDesc('booked_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.bookings.index', [
            'bookings' => $bookings,
            'hotels' => $this->hotels(),
            'search' => $search,
            'hotelId' => $hotelId,
            'status' => $status,
            'paymentStatus' => $paymentStatus,
            'from' => $from,
            'to' => $to,
            'statuses' => $this->statuses(),
            'paymentStatuses' => $this->paymentStatuses(),
        ]);
    }

    public function show(Booking $booking): View
    {
        return view('admin.bookings.show', [
            'booking' => $booking->load(['hotel:id,name,slug', 'roomType:id,name,total_units', 'user:id,name,email', 'guests', 'payments']),
            'statuses' => $this->statuses(),
            'paymentStatuses' => ['pending', 'authorized', 'paid', 'failed', 'refunded'],
        ]);
    }

    public function updateStatus(Request $request, Booking $booking): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in($this->statuses())],
        ]);

        DB::transaction(fn () => $this->changeBookingStatus($booking->id, $validated['status']));

        return redirect()
            ->route('admin.bookings.show', $booking)
            ->with('status', 'booking-updated');
    }

    public function storePayment(Request $request, Booking $booking): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'status' => ['required', 'string', 'in:pending,authorized,paid,failed,refunded'],
            'transaction_reference' => ['nullable', 'string', 'max:100'],
        ]);

        DB::transaction(fn () => $this->registerPayment($booking->id, $validated));

        return redirect()
            ->route('admin.bookings.show', $booking)
            ->with('status', 'payment-created');
    }

    private function changeBookingStatus(int $bookingId, string $newStatus): Booking
    {
        /** @var Booking $booking */
        $booking = Booking::query()
            ->whereKey($bookingId)
            ->with('roomType')
            ->lockForUpdate()
            ->firstOrFail();

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
    }

    private function registerPayment(int $bookingId, array $validated): void
    {
        /** @var Booking $booking */
        $booking = Booking::query()
            ->whereKey($bookingId)
            ->lockForUpdate()
            ->firstOrFail();

        $paymentStatus = $validated['status'];

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
            'provider' => 'manual',
            'amount' => $amount,
            'currency' => $booking->currency,
            'status' => $paymentStatus,
            'transaction_reference' => $validated['transaction_reference'] ?? null,
            'paid_at' => $paymentStatus === 'paid' ? now() : null,
        ]);

        $this->refreshBookingPaymentStatus($booking->id, (float) $booking->total_amount, $paymentStatus);
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

        $availability = RoomTypeAvailability::query()
            ->where('room_type_id', $booking->room_type_id)
            ->whereIn('date', $stayDates)
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

    private function refreshBookingPaymentStatus(int $bookingId, float $totalAmount, string $latestPaymentStatus): void
    {
        $paidAmount = (float) Payment::query()
            ->where('booking_id', $bookingId)
            ->where('status', 'paid')
            ->sum('amount');

        $paymentStatus = match (true) {
            $latestPaymentStatus === 'refunded' => 'refunded',
            $paidAmount >= $totalAmount => 'paid',
            $paidAmount > 0 => 'partial',
            $latestPaymentStatus === 'failed' => 'failed',
            default => 'pending',
        };

        Booking::query()
            ->whereKey($bookingId)
            ->update(['payment_status' => $paymentStatus]);
    }

    private function buildStayDates(CarbonImmutable $checkIn, CarbonImmutable $checkOut): array
    {
        $stayDates = [];

        for ($date = $checkIn; $date->lt($checkOut); $date = $date->addDay()) {
            $stayDates[] = $date->toDateString();
        }

        return $stayDates;
    }

    private function hotels()
    {
        return Hotel::query()
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function statuses(): array
    {
        return ['pending', 'confirmed', 'cancelled', 'completed'];
    }

    private function paymentStatuses(): array
    {
        return ['pending', 'partial', 'paid', 'failed', 'refunded'];
    }
}
