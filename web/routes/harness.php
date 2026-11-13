<?php

use App\Http\Controllers\HarnessController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Harness API Routes
|--------------------------------------------------------------------------
|
| Called by the harness container with the token saved by `harness-connect`
|
*/

Route::prefix('api/harness')->name('harness.')->group(function () {
    Route::post('/hello', [HarnessController::class, 'hello'])->name('hello');
    Route::get('/claude', [HarnessController::class, 'claude'])->name('claude');
    Route::get('/mcp', [HarnessController::class, 'mcp'])->name('mcp');

    // Prompts for Claude Code (App\Harness\HarnessTask).
    Route::post('/tasks/next', [HarnessController::class, 'nextTask'])->name('tasks.next');
    Route::post('/tasks/{task}/events', [HarnessController::class, 'taskEvents'])->name('tasks.events');
    Route::post('/tasks/{task}/finish', [HarnessController::class, 'finishTask'])->name('tasks.finish');
});
