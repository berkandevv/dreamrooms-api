<?php

namespace App\Http\Controllers;

use App\Http\Resources\BookingResource;
use App\Http\Resources\ReviewResource;
use App\Models\Booking;
use App\Models\RoomType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookingController extends Controller
{
    /**
     * List bookings
     *
     * Returns the bookings that belong to the authenticated customer
     */
    public function index(Request $request)
    {
        $bookings = Booking::query()
            ->where('user_id', $request->user()->id)
            ->with([
                'user:id,name,email',
                'hotel:id,name,slug',
                'roomType:id,name',
            ])
            ->orderBy('id')
            ->get();

        return BookingResource::collection($bookings);
    }

    /**
     * Show a booking
     *
     * Returns the full details for one of the authenticated customer's bookings
     */
    public function show(Request $request, int $bookingId): BookingResource
    {
        $booking = Booking::query()
            ->where('user_id', $request->user()->id)
            ->with([
                'user:id,name,email',
                'hotel:id,name,slug',
                'roomType:id,name',
                'guests',
                'payments',
            ])
            ->findOrFail($bookingId);

        return new BookingResource($booking);
    }

    /**
     * Cancel a booking
     *
     * Cancels an active booking and restores the booked units to availability
     */
    public function cancel(Request $request, int $bookingId): BookingResource
    {
        $booking = DB::transaction(fn (): Booking => $this->cancelBooking($bookingId, $request->user()->id));

        $booking->load([
            'user:id,name,email',
            'hotel:id,name,slug',
            'roomType:id,name',
            'guests',
            'payments',
        ]);

        return new BookingResource($booking);
    }

    /**
     * Create a booking review
     *
     * Creates a single public review for a completed booking
     */
    public function review(Request $request, int $bookingId)
    {
        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string'],
        ]);

        $review = DB::transaction(fn () => $this->createReview($bookingId, $request->user()->id, $validated));

        $review->load('user:id,name');

        return (new ReviewResource($review))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Create a booking
     *
     * Creates a booking for an available room type, validates occupancy and dates,
     * stores optional guests, and assigns it to the authenticated customer
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'room_type_id' => ['required', 'integer', 'exists:room_types,id'],
            'check_in' => ['required', 'date', 'after_or_equal:today'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'adults_count' => ['required', 'integer', 'min:1'],
            'children_count' => ['nullable', 'integer', 'min:0'],
            'units_booked' => ['nullable', 'integer', 'min:1'],
            'customer_name' => ['required', 'string', 'max:150'],
            'customer_email' => ['required', 'email', 'max:150'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string'],
            'guests' => ['nullable', 'array'],
            'guests.*.full_name' => ['required_with:guests', 'string', 'max:200'],
            'guests.*.document_type' => ['nullable', 'string', 'max:30'],
            'guests.*.document_number' => ['nullable', 'string', 'max:50'],
            'guests.*.birth_date' => ['nullable', 'date'],
            'guests.*.is_primary' => ['nullable', 'boolean'],
        ]);
        $validated['user_id'] = $request->user()->id;

        $booking = DB::transaction(fn (): Booking => $this->createBooking($validated));

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

    private function createBooking(array $validated): Booking
    {
        $checkIn = CarbonImmutable::parse($validated['check_in']);
        $checkOut = CarbonImmutable::parse($validated['check_out']);
        $nights = (int) $checkIn->diffInDays($checkOut);
        $unitsBooked = $validated['units_booked'] ?? 1;
        $childrenCount = $validated['children_count'] ?? 0;
        $stayDates = $this->buildStayDates($checkIn, $checkOut);

        $roomType = $this->findBookableRoomType($validated['room_type_id']);
        $this->validateOccupancy($roomType, $validated['adults_count'], $childrenCount);

        $availability = $this->lockAvailability($roomType, $stayDates);
        $this->validateAvailability($availability, $stayDates, $nights, $unitsBooked);

        $amounts = $this->calculateAmounts($availability, $unitsBooked);
        $user = $this->findRegisteredCustomer($validated['user_id']);
        $booking = $this->persistBooking($validated, $roomType, $user, $checkIn, $checkOut, $nights, $childrenCount, $amounts);

        $this->decrementAvailability($availability, $unitsBooked);
        $this->createBookingGuests($booking, $validated);

        return $booking;
    }

    private function cancelBooking(int $id, int $userId): Booking
    {
        // Cancela una reserva activa y devuelve sus unidades al calendario de disponibilidad
        $booking = Booking::query()
            ->where('user_id', $userId)
            ->lockForUpdate()
            ->findOrFail($id);

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

        $stayDates = $this->buildStayDates(
            CarbonImmutable::parse($booking->check_in),
            CarbonImmutable::parse($booking->check_out),
        );

        $availability = $booking->roomType
            ->availability()
            ->whereIn('date', $stayDates)
            ->lockForUpdate()
            ->get();

        foreach ($availability as $day) {
            $day->increment('available_units', $booking->units_booked);
        }

        $booking->forceFill([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ])->save();

        return $booking;
    }

    private function createReview(int $id, int $userId, array $validated)
    {
        // Crea una única reseña para reservas completadas
        $booking = Booking::query()
            ->where('user_id', $userId)
            ->with('review')
            ->lockForUpdate()
            ->findOrFail($id);

        if ($booking->status !== 'completed') {
            throw ValidationException::withMessages([
                'booking' => ['Only completed bookings can be reviewed.'],
            ]);
        }

        if ($booking->review) {
            throw ValidationException::withMessages([
                'booking' => ['This booking already has a review.'],
            ]);
        }

        return $booking->review()->create([
            'hotel_id' => $booking->hotel_id,
            'user_id' => $booking->user_id,
            'rating' => $validated['rating'],
            'comment' => $validated['comment'] ?? null,
            'status' => 'published',
        ]);
    }

    private function findBookableRoomType(int $roomTypeId): RoomType
    {
        // Busca una habitación activa dentro de un hotel publicado
        return RoomType::query()
            ->with('hotel')
            ->where('status', 'active')
            ->whereHas('hotel', fn ($query) => $query->where('status', 'published'))
            ->findOrFail($roomTypeId);
    }

    private function validateOccupancy(RoomType $roomType, int $adultsCount, int $childrenCount): void
    {
        // Comprueba que la ocupación solicitada cabe en este tipo de habitación
        if ($adultsCount <= $roomType->capacity_adults && $childrenCount <= $roomType->capacity_children) {
            return;
        }

        throw ValidationException::withMessages([
            'room_type_id' => ['The selected room type does not support the requested occupancy.'],
        ]);
    }

    private function buildStayDates(CarbonImmutable $checkIn, CarbonImmutable $checkOut): array
    {
        // Genera las noches de estancia; el día de salida no consume disponibilidad
        $stayDates = [];

        for ($date = $checkIn; $date->lt($checkOut); $date = $date->addDay()) {
            $stayDates[] = $date->toDateString();
        }

        return $stayDates;
    }

    private function lockAvailability(RoomType $roomType, array $stayDates): Collection
    {
        // Bloquea las filas de disponibilidad para evitar dobles reservas simultáneas
        return $roomType->availability()
            ->whereIn('date', $stayDates)
            ->lockForUpdate()
            ->get()
            ->keyBy(fn ($day) => $day->date->toDateString());
    }

    private function validateAvailability(Collection $availability, array $stayDates, int $nights, int $unitsBooked): void
    {
        // Verifica que todas las noches existen, están abiertas y tienen unidades suficientes
        if ($availability->count() !== $nights) {
            throw ValidationException::withMessages([
                'check_in' => ['There is no availability for every night in the selected stay.'],
            ]);
        }

        foreach ($stayDates as $stayDate) {
            $day = $availability[$stayDate];

            if ($day->status !== 'open' || $day->available_units < $unitsBooked) {
                throw ValidationException::withMessages([
                    'check_in' => ['The selected room type is not available for the requested dates.'],
                ]);
            }

            if ($day->min_stay_nights !== null && $nights < $day->min_stay_nights) {
                throw ValidationException::withMessages([
                    'check_out' => ["The selected dates require at least {$day->min_stay_nights} nights."],
                ]);
            }
        }
    }

    private function calculateAmounts(Collection $availability, int $unitsBooked): array
    {
        // Calcula importes a partir del precio diario de cada noche reservada
        $subtotal = $availability->sum(fn ($day) => (float) $day->price) * $unitsBooked;
        $taxes = round($subtotal * 0.1, 2);
        $discount = 0;

        return [
            'subtotal' => round($subtotal, 2),
            'taxes' => $taxes,
            'discount' => $discount,
            'total' => round($subtotal + $taxes - $discount, 2),
        ];
    }

    private function findRegisteredCustomer(int $userId): User
    {
        // La reserva solo puede continuar con un cliente registrado y activo
        $user = User::query()
            ->where('status', 'active')
            ->find($userId);

        if ($user) {
            return $user;
        }

        throw ValidationException::withMessages([
            'user_id' => ['The selected customer is not active.'],
        ]);
    }

    private function persistBooking(
        array $validated,
        RoomType $roomType,
        User $user,
        CarbonImmutable $checkIn,
        CarbonImmutable $checkOut,
        int $nights,
        int $childrenCount,
        array $amounts,
    ): Booking {
        // Guarda la reserva con una copia de nombres e importes (instante snapshot) para evitar inconsistencias si se actualizan datos relacionados posteriormente
        return Booking::query()->create([
            'booking_reference' => $this->generateBookingReference(),
            'user_id' => $user->id,
            'hotel_id' => $roomType->hotel_id,
            'room_type_id' => $roomType->id,
            'hotel_name' => $roomType->hotel->name,
            'room_type_name' => $roomType->name,
            'customer_name' => $validated['customer_name'],
            'customer_email' => $validated['customer_email'],
            'customer_phone' => $validated['customer_phone'] ?? null,
            'check_in' => $checkIn->toDateString(),
            'check_out' => $checkOut->toDateString(),
            'nights' => $nights,
            'adults_count' => $validated['adults_count'],
            'children_count' => $childrenCount,
            'units_booked' => $validated['units_booked'] ?? 1,
            'status' => 'pending',
            'payment_status' => 'pending',
            'subtotal_amount' => $amounts['subtotal'],
            'taxes_amount' => $amounts['taxes'],
            'discount_amount' => $amounts['discount'],
            'total_amount' => $amounts['total'],
            'currency' => 'EUR',
            'booked_at' => now(),
            'expires_at' => now()->addMinutes(30),
            'notes' => $validated['notes'] ?? null,
        ]);
    }

    private function decrementAvailability(Collection $availability, int $unitsBooked): void
    {
        // Descuenta las unidades reservadas en cada noche de la estancia
        foreach ($availability as $day) {
            $day->decrement('available_units', $unitsBooked);
        }
    }

    private function createBookingGuests(Booking $booking, array $validated): void
    {
        // Crea los huéspedes enviados o usa el cliente como huésped principal
        $guests = $validated['guests'] ?? [[
            'full_name' => $validated['customer_name'],
            'is_primary' => true,
        ]];

        foreach ($guests as $guest) {
            $booking->guests()->create([
                'full_name' => $guest['full_name'],
                'document_type' => $guest['document_type'] ?? null,
                'document_number' => $guest['document_number'] ?? null,
                'birth_date' => $guest['birth_date'] ?? null,
                'is_primary' => $guest['is_primary'] ?? false,
            ]);
        }
    }

    private function generateBookingReference(): string
    {
        // Genera una referencia corta y única para identificar la reserva
        do {
            $reference = strtoupper(Str::random(10));
        } while (Booking::query()->where('booking_reference', $reference)->exists());

        return $reference;
    }
}
