<?php

namespace App\Http\Controllers\Admin;

use App\Models\Booking;
use App\Models\Hotel;
use App\Services\BookingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BookingController extends Controller
{
    private readonly BookingService $bookings;

    public function __construct(BookingService $bookings)
    {
        $this->bookings = $bookings;
    }

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
            'paymentStatuses' => ['pending', 'paid', 'failed', 'refunded'],
        ]);
    }

    public function updateStatus(Request $request, Booking $booking): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in($this->statuses())],
        ]);

        $this->bookings->changeStatus($booking->id, $validated['status']);

        return redirect()
            ->route('admin.bookings.show', $booking)
            ->with('status', 'booking-updated');
    }

    public function storePayment(Request $request, Booking $booking): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'status' => ['required', 'string', 'in:paid,failed,refunded'],
            'transaction_reference' => ['nullable', 'string', 'max:100'],
        ]);

        $this->bookings->registerPayment($booking->id, $validated);

        return redirect()
            ->route('admin.bookings.show', $booking)
            ->with('status', 'payment-created');
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
        return ['pending', 'paid', 'failed', 'refunded'];
    }
}
