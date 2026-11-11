<?php

use App\Http\Controllers\HarnessController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Harness API Routes
|--------------------------------------------------------------------------
|
| Called by the harness container with the token from `bin/harness connect`
|
*/

Route::prefix('api/harness')->name('harness.')->group(function () {
    Route::post('/hello', [HarnessController::class, 'hello'])->name('hello');
    Route::get('/claude', [HarnessController::class, 'claude'])->name('claude');
});
