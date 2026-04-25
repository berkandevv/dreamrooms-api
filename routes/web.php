<?php

use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\HotelController as AdminHotelController;
use App\Http\Controllers\Admin\AvailabilityController as AdminAvailabilityController;
use App\Http\Controllers\Admin\BookingController as AdminBookingController;
use App\Http\Controllers\Admin\ReviewController as AdminReviewController;
use App\Http\Controllers\Admin\ServiceController as AdminServiceController;
use App\Http\Controllers\Admin\RoomTypeController as AdminRoomTypeController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/docs/swagger', function () {
    return view('docs.swagger');
});

Route::get('/dashboard', function () {
    if (request()->user()?->hasRole('admin')) {
        return redirect()->route('admin.users.index');
    }

    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::resource('hotels', AdminHotelController::class)->only(['index', 'edit', 'update']);
        Route::resource('room-types', AdminRoomTypeController::class)->only(['index', 'edit', 'update']);
        Route::resource('availability', AdminAvailabilityController::class)->only(['index', 'edit', 'update']);
        Route::get('bookings', [AdminBookingController::class, 'index'])->name('bookings.index');
        Route::get('bookings/{booking}', [AdminBookingController::class, 'show'])->name('bookings.show');
        Route::patch('bookings/{booking}/status', [AdminBookingController::class, 'updateStatus'])->name('bookings.status');
        Route::post('bookings/{booking}/payments', [AdminBookingController::class, 'storePayment'])->name('bookings.payments.store');
        Route::resource('reviews', AdminReviewController::class)->only(['index', 'edit', 'update']);
        Route::resource('services', AdminServiceController::class)->only(['index', 'create', 'store', 'edit', 'update']);
        Route::resource('users', AdminUserController::class)->only(['index', 'edit', 'update']);
    });

require __DIR__.'/auth.php';
