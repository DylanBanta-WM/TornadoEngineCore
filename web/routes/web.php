<?php

use App\Http\Controllers\TourController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware('auth')->name('dashboard');

// The tour after first-time setup (App\Support\Tour).
Route::middleware('auth')->prefix('tour')->name('tour.')->group(function () {
    Route::get('/', [TourController::class, 'resume'])->name('resume');
    Route::post('/next', [TourController::class, 'next'])->name('next');
    Route::post('/back', [TourController::class, 'back'])->name('back');
    Route::post('/skip', [TourController::class, 'skip'])->name('skip');
});
