<?php

namespace App\Providers;

use App\Administration\Administration;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Administration::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Who can open Administration. Until the Access module manages permissions,
        // it's the admin made during first-time setup.
        Gate::define('administration', fn (User $user) => (bool) $user->is_admin);
    }
}
