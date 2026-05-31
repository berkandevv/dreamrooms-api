<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CustomerBookingController;
use App\Http\Controllers\Api\CustomerFavoriteController;
use App\Http\Controllers\Api\HotelController;
use App\Http\Controllers\Api\OwnerBookingController;
use App\Http\Controllers\Api\OwnerHotelController;
use App\Http\Controllers\Api\OwnerRoomTypeController;
use App\Http\Controllers\Api\OwnerServiceController;
use App\Http\Controllers\Api\RoomTypeController;
use Illuminate\Support\Facades\Route;

// Autenticación pública: no requiere token
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

// Sesión autenticada: usuarios API activos con token Sanctum
Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::put('/auth/password', [AuthController::class, 'updatePassword']);
    Route::delete('/auth/account', [AuthController::class, 'deactivateAccount']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
});

// Catálogo público: visible sin login
Route::get('/hotels', [HotelController::class, 'index']);
Route::get('/hotels/{slug}', [HotelController::class, 'show']);
Route::get('/hotels/{slug}/reviews', [HotelController::class, 'reviews']);

// Disponibilidad pública de habitaciones publicadas
Route::get('/room-types/{roomTypeId}/availability', [RoomTypeController::class, 'availability']);
Route::get('/room-types/{roomTypeId}/availability/quote', [RoomTypeController::class, 'quote']);

// Área de cliente: reservas y favoritos siempre usan el usuario del token
Route::middleware(['auth:sanctum', 'role:customer'])->group(function (): void {
    Route::post('/customer/hotels/{hotelId}/favorite', [CustomerFavoriteController::class, 'store']);
    Route::delete('/customer/hotels/{hotelId}/favorite', [CustomerFavoriteController::class, 'destroy']);

    Route::get('/customer/bookings', [CustomerBookingController::class, 'index']);
    Route::post('/customer/bookings', [CustomerBookingController::class, 'store']);
    Route::post('/customer/bookings/{bookingId}/cancel', [CustomerBookingController::class, 'cancel']);
    Route::post('/customer/bookings/{bookingId}/review', [CustomerBookingController::class, 'review']);
    Route::get('/customer/bookings/{bookingId}', [CustomerBookingController::class, 'show']);

    Route::get('/customer/favorites', [CustomerFavoriteController::class, 'index']);
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
    Route::post('/owner/hotels/{hotelId}/images', [OwnerHotelController::class, 'images']);
    Route::get('/owner/hotels/{hotelId}/room-types', [OwnerRoomTypeController::class, 'index']);
    Route::post('/owner/hotels/{hotelId}/room-types', [OwnerRoomTypeController::class, 'store']);
    Route::get('/owner/hotels/{hotelId}', [OwnerHotelController::class, 'show']);
    Route::put('/owner/room-types/{roomTypeId}', [OwnerRoomTypeController::class, 'update']);
    Route::post('/owner/room-types/{roomTypeId}/images', [OwnerRoomTypeController::class, 'images']);
    Route::post('/owner/room-types/{roomTypeId}/availability/bulk', [OwnerRoomTypeController::class, 'availabilityBulk']);
    Route::get('/owner/room-types/{roomTypeId}/availability', [OwnerRoomTypeController::class, 'availability']);
    Route::get('/owner/room-types/{roomTypeId}', [OwnerRoomTypeController::class, 'show']);
});
