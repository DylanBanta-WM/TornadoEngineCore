<?php

use App\Http\Controllers\SetupController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| First-time Setup Routes
|--------------------------------------------------------------------------
|
| Create the admin account, connect the harness, save its Claude Code login
|
*/

Route::get('/setup', [SetupController::class, 'index'])->name('setup');
Route::post('/setup/admin', [SetupController::class, 'storeAdmin'])->name('setup.admin');

Route::middleware('auth')->prefix('setup')->name('setup.')->group(function () {
    Route::post('/harness', [SetupController::class, 'storeHarness'])->name('harness');
    Route::post('/claude', [SetupController::class, 'storeClaude'])->name('claude');
});
