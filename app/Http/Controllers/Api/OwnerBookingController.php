<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Http\Request;

class OwnerBookingController extends Controller
{
    private readonly BookingService $bookings;

    public function __construct(BookingService $bookings)
    {
        $this->bookings = $bookings;
    }

    // Lista las reservas de los hoteles del propietario autenticado
    public function index(Request $request)
    {
        $validated = $request->validate([
            'hotel_id' => ['nullable', 'integer', 'exists:hotels,id'],
            'status' => ['nullable', 'string', 'in:pending,confirmed,cancelled,completed'],
            'payment_status' => ['nullable', 'string', 'in:pending,paid,failed,refunded'],
        ]);
        // El owner solo lista reservas de hoteles que le pertenecen
        $ownerUserId = $request->user()->id;

        $bookings = Booking::query()
            ->ownedBy($ownerUserId)
            ->when($validated['hotel_id'] ?? null, fn ($query, $hotelId) => $query->where('hotel_id', $hotelId))
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($validated['payment_status'] ?? null, fn ($query, $paymentStatus) => $query->where('payment_status', $paymentStatus))
            ->with($this->ownerBookingRelations())
            ->orderByDesc('booked_at')
            ->orderByDesc('id')
            ->get();

        return BookingResource::collection($bookings);
    }

    // Muestra el detalle de una reserva que pertenece al propietario autenticado
    public function show(Request $request, int $bookingId): BookingResource
    {
        // La reserva debe pertenecer a un hotel del owner autenticado
        $ownerUserId = $request->user()->id;

        $booking = Booking::query()
            ->whereKey($bookingId)
            ->ownedBy($ownerUserId)
            ->with($this->ownerBookingRelations())
            ->firstOrFail();

        return BookingResource::make($booking);
    }

    // Actualiza el estado de una reserva respetando las transiciones permitidas
    public function updateStatus(Request $request, int $bookingId): BookingResource
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:pending,confirmed,cancelled,completed'],
        ]);
        $booking = $this->bookings->changeStatus($bookingId, $validated['status'], $request->user()->id);

        $this->loadOwnerBookingRelations($booking);

        return BookingResource::make($booking);
    }

    // Registra un pago sobre una reserva de uno de los hoteles del propietario
    public function payments(Request $request, int $bookingId)
    {
        $validated = $request->validate([
            'provider' => ['nullable', 'string', 'in:manual'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'size:3'],
            'status' => ['nullable', 'string', 'in:paid,failed,refunded'],
            'transaction_reference' => ['nullable', 'string', 'max:100'],
            'metadata' => ['nullable', 'array'],
        ]);

        $booking = $this->bookings->registerPayment($bookingId, $validated, $request->user()->id);

        $this->loadOwnerBookingRelations($booking);

        return BookingResource::make($booking)
            ->response()
            ->setStatusCode(201);
    }

    // Helpers de carga para respuestas de reservas del propietario

    // Devuelve las relaciones necesarias para las reservas del propietario
    private function ownerBookingRelations(): array
    {
        return [
            'user:id,name,email',
            'hotel:id,name,slug',
            'roomType:id,name',
            'guests',
            'payments',
        ];
    }

    // Carga en memoria las relaciones del detalle de reserva del propietario
    private function loadOwnerBookingRelations(Booking $booking): void
    {
        $booking->load($this->ownerBookingRelations());
    }
}
