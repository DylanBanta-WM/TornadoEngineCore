<?php

use App\Http\Controllers\AdministrationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Administration Routes
|--------------------------------------------------------------------------
|
| Tornado Engine admins only. Modules add their own sections (see App\Administration\Administration).
|
*/

Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/administration', [AdministrationController::class, 'index'])->name('administration');
});
