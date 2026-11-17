<?php

use App\Http\Controllers\AdministrationController;
use App\Http\Controllers\OverridesController;
use App\Http\Controllers\UpdatesController;
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
    Route::post('/signin', [AdministrationController::class, 'signin'])->name('administration.signin');
    Route::post('/microsoft', [AdministrationController::class, 'microsoft'])->name('administration.microsoft');
    Route::post('/harness', [AdministrationController::class, 'harness'])->name('administration.harness');
    Route::post('/claude', [AdministrationController::class, 'claude'])->name('administration.claude');

    Route::post('/updates/core', [UpdatesController::class, 'core'])->name('administration.updates.core');
    Route::post('/updates/check', [UpdatesController::class, 'check'])->name('administration.updates.check');
    Route::post('/rebuild', [UpdatesController::class, 'rebuild'])->name('administration.rebuild');

    Route::post('/overrides', [OverridesController::class, 'create'])->name('administration.overrides.create');
    Route::post('/overrides/view', [OverridesController::class, 'save'])->name('administration.overrides.save');
    Route::post('/overrides/css', [OverridesController::class, 'css'])->name('administration.overrides.css');
    Route::get('/rebuild/status', [UpdatesController::class, 'rebuildStatus'])->name('administration.rebuild.status');

    // Tabs that modules add.
    Route::get('/{tab}', [AdministrationController::class, 'tab'])->name('administration.tab');
});
