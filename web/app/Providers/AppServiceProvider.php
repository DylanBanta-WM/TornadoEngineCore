<?php

namespace App\Providers;

use App\Administration\Administration;
use App\Harness\HarnessMcp;
use App\Harness\HarnessView;
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
        $this->app->singleton(HarnessView::class);
        $this->app->singleton(HarnessMcp::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Who can open Administration. Until the Access module manages permissions,
        // it's the admin made during first-time setup.
        Gate::define('administration', fn (User $user) => (bool) $user->is_admin);

        // Core's own updates, run by the updater service.
        app(Administration::class)->tab('updates', __('Updates'), order: 90)->card('administration.updates', tab: 'updates');
    }
}
