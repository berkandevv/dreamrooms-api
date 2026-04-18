<?php

use App\Http\Controllers\HotelController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\OwnerHotelController;
use App\Http\Controllers\RoomTypeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Endpoints públicos del catálogo de hoteles
Route::get('/hotels', [HotelController::class, 'index']);
Route::post('/hotels/{id}/favorite', [FavoriteController::class, 'store']);
Route::delete('/hotels/{id}/favorite', [FavoriteController::class, 'destroy']);
Route::get('/hotels/{slug}', [HotelController::class, 'show']);
Route::get('/hotels/{slug}/reviews', [HotelController::class, 'reviews']);

// Endpoints públicos de tipos de habitación
Route::get('/room-types/{id}/availability', [RoomTypeController::class, 'availability']);

// Endpoints públicos de reservas
Route::get('/bookings', [BookingController::class, 'index']);
Route::post('/bookings', [BookingController::class, 'store']);
Route::post('/bookings/{id}/cancel', [BookingController::class, 'cancel']);
Route::post('/bookings/{id}/payments', [BookingController::class, 'payments']);
Route::post('/bookings/{id}/review', [BookingController::class, 'review']);
Route::get('/bookings/{id}', [BookingController::class, 'show']);

// Endpoints públicos de favoritos
Route::get('/favorites', [FavoriteController::class, 'index']);

// Endpoints temporales de propietario hasta activar auth
Route::get('/owner/hotels', [OwnerHotelController::class, 'index']);
Route::post('/owner/hotels', [OwnerHotelController::class, 'store']);
Route::get('/owner/hotels/{id}', [OwnerHotelController::class, 'show']);

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
