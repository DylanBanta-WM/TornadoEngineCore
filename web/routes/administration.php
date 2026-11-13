<?php

use App\Http\Controllers\AdministrationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Administration Routes
|--------------------------------------------------------------------------
|
| Change the settings from first-time setup. Needs the "administration" permission.
|
*/

Route::middleware(['auth', 'can:administration'])->prefix('administration')->group(function () {
    Route::get('/', [AdministrationController::class, 'index'])->name('administration');
    Route::post('/address', [AdministrationController::class, 'address'])->name('administration.address');
    Route::post('/google', [AdministrationController::class, 'google'])->name('administration.google');
    Route::post('/harness', [AdministrationController::class, 'harness'])->name('administration.harness');
    Route::post('/claude', [AdministrationController::class, 'claude'])->name('administration.claude');
});
