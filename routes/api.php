<?php

use App\Http\Controllers\HotelController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Endpoints públicos del catálogo de hoteles
Route::get('/hotels', [HotelController::class, 'index']);
Route::get('/hotels/{slug}', [HotelController::class, 'show']);

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
