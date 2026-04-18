<?php

namespace App\Http\Controllers;

use App\Http\Resources\BookingResource;
use App\Models\Booking;

class BookingController extends Controller
{
    public function index()
    {
        // Devuelve las reservas existentes para poder probar el flujo sin autenticación
        $bookings = Booking::query()
            ->with([
                'user:id,name,email',
                'hotel:id,name,slug',
                'roomType:id,name',
            ])
            ->orderBy('id')
            ->get();

        return BookingResource::collection($bookings);
    }

    public function show(int $id): BookingResource
    {
        // Devuelve los detalles de una reserva concreta
        $booking = Booking::query()
            ->with([
                'user:id,name,email',
                'hotel:id,name,slug',
                'roomType:id,name',
                'guests',
                'payments',
            ])
            ->findOrFail($id);

        return new BookingResource($booking);
    }
}
