<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\BookingResource;
use App\Http\Resources\ReviewResource;
use App\Models\Booking;
use App\Models\RoomType;
use App\Models\RoomTypeAvailability;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerBookingController extends Controller
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
            ->with($this->bookingListRelations())
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
            ->with($this->bookingDetailRelations())
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

        $this->loadBookingDetails($booking);

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
        $this->validateGuestsCount($validated);
        $this->expirePendingBookings();

        $booking = DB::transaction(fn (): Booking => $this->createBooking($validated));

        $this->loadBookingDetails($booking);

        return (new BookingResource($booking))
            ->response()
            ->setStatusCode(201);
    }

    // Helpers de carga para respuestas de reservas

    // Devuelve las relaciones necesarias para el listado de reservas
    private function bookingListRelations(): array
    {
        return [
            'user:id,name,email',
            'hotel:id,name,slug',
            'roomType:id,name',
        ];
    }

    // Devuelve las relaciones necesarias para el detalle de una reserva
    private function bookingDetailRelations(): array
    {
        return [
            ...$this->bookingListRelations(),
            'guests',
            'payments',
        ];
    }

    // Carga en memoria las relaciones del detalle de reserva
    private function loadBookingDetails(Booking $booking): void
    {
        $booking->load($this->bookingDetailRelations());
    }

    // Orquesta la creación completa de la reserva y sus datos asociados
    private function createBooking(array $validated): Booking
    {
        $stayData = $this->buildStayData($validated);

        $roomType = $this->findBookableRoomType($validated['room_type_id']);
        $this->validateOccupancy(
            $roomType,
            $validated['adults_count'],
            $stayData['children_count'],
            $stayData['units_booked'],
        );

        $availability = $this->resolveBookingAvailability($roomType, $stayData);
        $booking = $this->createBookingRecord($validated, $roomType, $stayData, $availability);

        $this->decrementAvailability($availability, $stayData['units_booked']);
        $this->createBookingGuests($booking, $validated);

        return $booking;
    }

    // Prepara los datos derivados de fechas y ocupación antes de reservar
    private function buildStayData(array $validated): array
    {
        $checkIn = CarbonImmutable::parse($validated['check_in']);
        $checkOut = CarbonImmutable::parse($validated['check_out']);

        return [
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'nights' => (int) $checkIn->diffInDays($checkOut),
            'units_booked' => $validated['units_booked'] ?? 1,
            'children_count' => $validated['children_count'] ?? 0,
            'stay_dates' => $this->buildStayDates($checkIn, $checkOut),
        ];
    }

    // Bloquea y valida la disponibilidad necesaria para la estancia solicitada
    private function resolveBookingAvailability(RoomType $roomType, array $stayData): Collection
    {
        $availability = $this->lockAvailability($roomType, $stayData['stay_dates']);

        $this->validateAvailability(
            $availability,
            $stayData['stay_dates'],
            $stayData['nights'],
            $stayData['units_booked'],
        );

        return $availability;
    }

    // Crea el registro principal de la reserva con importes y datos snapshot
    private function createBookingRecord(
        array $validated,
        RoomType $roomType,
        array $stayData,
        Collection $availability,
    ): Booking {
        $amounts = $this->calculateAmounts($availability, $roomType, $stayData['units_booked']);
        $user = $this->findRegisteredCustomer($validated['user_id']);

        return $this->persistBooking(
            $validated,
            $roomType,
            $user,
            $stayData['check_in'],
            $stayData['check_out'],
            $stayData['nights'],
            $stayData['children_count'],
            $amounts,
        );
    }

    // Cancela una reserva activa y devuelve sus unidades al calendario de disponibilidad
    private function cancelBooking(int $id, int $userId): Booking
    {
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

        $this->restoreBookingAvailability($booking);

        $booking->forceFill([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ])->save();

        return $booking;
    }

    // Crea una única reseña para reservas completadas
    private function createReview(int $id, int $userId, array $validated)
    {
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

    // Busca un tipo de habitación activo dentro de un hotel publicado
    private function findBookableRoomType(int $roomTypeId): RoomType
    {
        return RoomType::query()
            ->with('hotel')
            ->where('status', 'active')
            ->whereHas('hotel', fn ($query) => $query->where('status', 'published'))
            ->findOrFail($roomTypeId);
    }

    // Cancela reservas pendientes caducadas antes de intentar crear una reserva nueva
    private function expirePendingBookings(): void
    {
        Booking::query()
            ->where('status', 'pending')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->pluck('id')
            ->each(function (int $bookingId): void {
                DB::transaction(function () use ($bookingId): void {
                    /** @var Booking|null $booking */
                    $booking = Booking::query()
                        ->with('roomType')
                        ->lockForUpdate()
                        ->find($bookingId);

                    if (! $booking || $booking->status !== 'pending') {
                        return;
                    }

                    $this->restoreBookingAvailability($booking);

                    $booking->forceFill([
                        'status' => 'cancelled',
                        'cancelled_at' => now(),
                    ])->save();
                });
            });
    }

    // Devuelve las unidades de una reserva al calendario de disponibilidad
    private function restoreBookingAvailability(Booking $booking): void
    {
        $stayDates = $this->buildStayDates(
            CarbonImmutable::parse($booking->check_in),
            CarbonImmutable::parse($booking->check_out),
        );

        $availability = $booking->roomType
            ->availability()
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

    // Comprueba que la ocupación solicitada cabe en las unidades reservadas
    private function validateOccupancy(RoomType $roomType, int $adultsCount, int $childrenCount, int $unitsBooked): void
    {
        $maxAdults = $roomType->capacity_adults * $unitsBooked;
        $maxChildren = $roomType->capacity_children * $unitsBooked;

        if ($adultsCount <= $maxAdults && $childrenCount <= $maxChildren) {
            return;
        }

        throw ValidationException::withMessages([
            'room_type_id' => ['The selected room type does not support the requested occupancy.'],
        ]);
    }

    // Valida que el número de huéspedes coincida con la ocupación enviada
    private function validateGuestsCount(array $validated): void
    {
        if (! isset($validated['guests'])) {
            return;
        }

        $expectedGuests = $validated['adults_count'] + ($validated['children_count'] ?? 0);

        if (\count($validated['guests']) === $expectedGuests) {
            return;
        }

        throw ValidationException::withMessages([
            'guests' => ['The number of guests must match adults_count plus children_count.'],
        ]);
    }

    // Genera las noches de estancia sin incluir el día de salida
    private function buildStayDates(CarbonImmutable $checkIn, CarbonImmutable $checkOut): array
    {
        $stayDates = [];

        for ($date = $checkIn; $date->lt($checkOut); $date = $date->addDay()) {
            $stayDates[] = $date->toDateString();
        }

        return $stayDates;
    }

    // Bloquea la disponibilidad para evitar dobles reservas simultáneas
    private function lockAvailability(RoomType $roomType, array $stayDates): Collection
    {
        return $roomType->availability()
            ->whereIn('date', $stayDates)
            ->lockForUpdate()
            ->get()
            ->keyBy(fn ($day) => $day->date->toDateString());
    }

    // Comprueba que todas las noches estén abiertas y tengan unidades suficientes
    private function validateAvailability(Collection $availability, array $stayDates, int $nights, int $unitsBooked): void
    {
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

    // Calcula los importes finales a partir del precio diario de la estancia
    private function calculateAmounts(Collection $availability, RoomType $roomType, int $unitsBooked): array
    {
        $subtotal = $availability->sum(fn ($day) => (float) $day->price) * $unitsBooked;
        $taxes = round($subtotal * ((float) $roomType->hotel->tax_rate_percent / 100), 2);
        $discount = round($subtotal * ((float) $roomType->hotel->discount_rate_percent / 100), 2);

        return [
            'subtotal' => round($subtotal, 2),
            'taxes' => $taxes,
            'discount' => $discount,
            'total' => round($subtotal + $taxes - $discount, 2),
        ];
    }

    // Busca un cliente activo antes de completar la reserva
    private function findRegisteredCustomer(int $userId): User
    {
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

    // Guarda la reserva con una copia de nombres e importes en el momento de compra
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

    // Descuenta las unidades reservadas en cada noche de la estancia
    private function decrementAvailability(Collection $availability, int $unitsBooked): void
    {
        foreach ($availability as $day) {
            $day->decrement('available_units', $unitsBooked);
        }
    }

    // Crea los huéspedes asociados a la reserva o un huésped principal por defecto
    private function createBookingGuests(Booking $booking, array $validated): void
    {
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

    // Genera una referencia única y corta para identificar la reserva
    private function generateBookingReference(): string
    {
        do {
            $reference = strtoupper(Str::random(10));
        } while (Booking::query()->where('booking_reference', $reference)->exists());

        return $reference;
    }
}
