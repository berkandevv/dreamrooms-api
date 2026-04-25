<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\HotelController;
use App\Http\Controllers\OwnerBookingController;
use App\Http\Controllers\OwnerHotelController;
use App\Http\Controllers\OwnerRoomTypeController;
use App\Http\Controllers\OwnerServiceController;
use App\Http\Controllers\RoomTypeController;
use Illuminate\Support\Facades\Route;

// Autenticación pública: no requiere token
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

// Sesión autenticada: usuarios API activos con token Sanctum
Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
});

// Catálogo público: visible sin login
Route::get('/hotels', [HotelController::class, 'index']);
Route::get('/hotels/{slug}', [HotelController::class, 'show']);
Route::get('/hotels/{slug}/reviews', [HotelController::class, 'reviews']);

// Disponibilidad pública de habitaciones publicadas
Route::get('/room-types/{roomTypeId}/availability', [RoomTypeController::class, 'availability']);

// Área de cliente: reservas y favoritos siempre usan el usuario del token
Route::middleware(['auth:sanctum', 'role:customer'])->group(function (): void {
    Route::post('/hotels/{hotelId}/favorite', [FavoriteController::class, 'store']);
    Route::delete('/hotels/{hotelId}/favorite', [FavoriteController::class, 'destroy']);

    Route::get('/bookings', [BookingController::class, 'index']);
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::post('/bookings/{bookingId}/cancel', [BookingController::class, 'cancel']);
    Route::post('/bookings/{bookingId}/review', [BookingController::class, 'review']);
    Route::get('/bookings/{bookingId}', [BookingController::class, 'show']);

    Route::get('/favorites', [FavoriteController::class, 'index']);
});

// Área de propietario: solo accede a hoteles, habitaciones y reservas de sus hoteles
Route::middleware(['auth:sanctum', 'role:owner'])->group(function (): void {
    Route::get('/owner/services', [OwnerServiceController::class, 'index']);
    Route::get('/owner/bookings', [OwnerBookingController::class, 'index']);
    Route::post('/owner/bookings/{bookingId}/payments', [OwnerBookingController::class, 'payments']);
    Route::put('/owner/bookings/{bookingId}/status', [OwnerBookingController::class, 'updateStatus']);
    Route::get('/owner/bookings/{bookingId}', [OwnerBookingController::class, 'show']);
    Route::get('/owner/hotels', [OwnerHotelController::class, 'index']);
    Route::post('/owner/hotels', [OwnerHotelController::class, 'store']);
    Route::put('/owner/hotels/{hotelId}', [OwnerHotelController::class, 'update']);
    Route::get('/owner/hotels/{hotelId}/room-types', [OwnerRoomTypeController::class, 'index']);
    Route::post('/owner/hotels/{hotelId}/room-types', [OwnerRoomTypeController::class, 'store']);
    Route::get('/owner/hotels/{hotelId}', [OwnerHotelController::class, 'show']);
    Route::put('/owner/room-types/{roomTypeId}', [OwnerRoomTypeController::class, 'update']);
    Route::post('/owner/room-types/{roomTypeId}/availability/bulk', [OwnerRoomTypeController::class, 'availabilityBulk']);
    Route::get('/owner/room-types/{roomTypeId}/availability', [OwnerRoomTypeController::class, 'availability']);
    Route::get('/owner/room-types/{roomTypeId}', [OwnerRoomTypeController::class, 'show']);
});
