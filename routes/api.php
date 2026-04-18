<?php

use App\Http\Controllers\HotelController;
use App\Http\Controllers\RoomTypeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Endpoints públicos del catálogo de hoteles
Route::get('/hotels', [HotelController::class, 'index']);
Route::get('/hotels/{slug}', [HotelController::class, 'show']);
Route::get('/hotels/{slug}/reviews', [HotelController::class, 'reviews']);

// Endpoints públicos de tipos de habitación
Route::get('/room-types/{id}/availability', [RoomTypeController::class, 'availability']);

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
