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

// The pages are at /settings (Administration → Settings in the menu); the old /administration addresses lead there.
Route::middleware(['auth', 'can:administration'])->group(function () {
    Route::get('/settings', [AdministrationController::class, 'index'])->name('administration');
    Route::get('/settings/{tab}', [AdministrationController::class, 'tab'])->name('administration.tab');
    Route::get('/administration', fn () => redirect('/settings', 301));
    Route::get('/administration/{tab}', fn (string $tab) => redirect('/settings/'.$tab, 301));
});

Route::middleware(['auth', 'can:administration'])->prefix('administration')->group(function () {
    Route::post('/address', [AdministrationController::class, 'address'])->name('administration.address');
    Route::post('/google', [AdministrationController::class, 'google'])->name('administration.google');
    Route::post('/signin', [AdministrationController::class, 'signin'])->name('administration.signin');
    Route::post('/microsoft', [AdministrationController::class, 'microsoft'])->name('administration.microsoft');
    Route::post('/harness', [AdministrationController::class, 'harness'])->name('administration.harness');
    Route::post('/claude', [AdministrationController::class, 'claude'])->name('administration.claude');

    Route::post('/updates/core', [UpdatesController::class, 'core'])->name('administration.updates.core');
    Route::post('/updates/check', [UpdatesController::class, 'check'])->name('administration.updates.check');
    Route::post('/rebuild', [UpdatesController::class, 'rebuild'])->name('administration.rebuild');
    Route::post('/patches/remove', [UpdatesController::class, 'removePatch'])->name('administration.patches.remove');

    Route::post('/overrides', [OverridesController::class, 'create'])->name('administration.overrides.create');
    Route::post('/overrides/view', [OverridesController::class, 'save'])->name('administration.overrides.save');
    Route::post('/overrides/css', [OverridesController::class, 'css'])->name('administration.overrides.css');
    Route::get('/rebuild/status', [UpdatesController::class, 'rebuildStatus'])->name('administration.rebuild.status');

});
