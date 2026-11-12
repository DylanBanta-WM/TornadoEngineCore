<?php

use App\Http\Controllers\SetupController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| First-time Setup Routes
|--------------------------------------------------------------------------
|
| Google sign-in, the admin account, the harness, its Claude Code login
|
*/

Route::get('/setup', [SetupController::class, 'index'])->name('setup');
Route::post('/setup/google', [SetupController::class, 'storeGoogle'])->name('setup.google');

Route::middleware('auth')->prefix('setup')->name('setup.')->group(function () {
    Route::post('/harness', [SetupController::class, 'storeHarness'])->name('harness');
    Route::post('/claude', [SetupController::class, 'storeClaude'])->name('claude');
});
