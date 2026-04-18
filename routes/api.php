<?php

use App\Http\Controllers\HotelController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\RoomTypeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Endpoints públicos del catálogo de hoteles
Route::get('/hotels', [HotelController::class, 'index']);
Route::get('/hotels/{slug}', [HotelController::class, 'show']);
Route::get('/hotels/{slug}/reviews', [HotelController::class, 'reviews']);

// Endpoints públicos de tipos de habitación
Route::get('/room-types/{id}/availability', [RoomTypeController::class, 'availability']);

// Endpoints públicos de reservas
Route::get('/bookings', [BookingController::class, 'index']);
Route::post('/bookings', [BookingController::class, 'store']);
Route::post('/bookings/{id}/cancel', [BookingController::class, 'cancel']);
Route::post('/bookings/{id}/payments', [BookingController::class, 'payments']);
Route::get('/bookings/{id}', [BookingController::class, 'show']);

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
