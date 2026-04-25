<?php

use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\HotelController as AdminHotelController;
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
        Route::resource('users', AdminUserController::class)->only(['index', 'edit', 'update']);
    });

require __DIR__.'/auth.php';
