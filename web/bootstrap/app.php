<?php

use App\Http\Middleware\AuthenticateHarness;
use App\Http\Middleware\EnsureSetupComplete;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')
                ->group(base_path('routes/auth.php'));

            Route::middleware('web')
                ->group(base_path('routes/setup.php'));

            Route::middleware('web')
                ->group(base_path('routes/administration.php'));

            Route::middleware(AuthenticateHarness::class)
                ->group(base_path('routes/harness.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // HTTPS is usually handled by the district's reverse proxy.
        $middleware->trustProxies(at: '*');
        $middleware->web(append: [EnsureSetupComplete::class]);
        $middleware->prependToPriorityList(
            before: AuthenticatesRequests::class,
            prepend: EnsureSetupComplete::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
