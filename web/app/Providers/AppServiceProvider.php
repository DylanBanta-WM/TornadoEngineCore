<?php

namespace App\Providers;

use App\Administration\Administration;
use App\Harness\HarnessMcp;
use App\Harness\HarnessView;
use App\Layout\Footer;
use App\Layout\ModuleSettings;
use App\Layout\Navbar;
use App\Models\User;
use App\Modules\Setup;
use App\Overrides\Overrides;
use App\Support\PasswordSignIn;
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
        $this->app->singleton(Footer::class);
        $this->app->singleton(Navbar::class);
        $this->app->singleton(Setup::class);
        $this->app->singleton(ModuleSettings::class);
        $this->app->singleton(Overrides::class);
        $this->app->singleton(PasswordSignIn::class);
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
        app(Administration::class)->tab('updates', __('Updates'), order: 90)->card('administration.updates', tab: 'updates')->card('administration.district', order: 110, tab: 'updates')->card('administration.patches', order: 120, tab: 'updates');
        app(Administration::class)->tab('overrides', __('Overrides'), order: 95)->card('administration.overrides', tab: 'overrides');

        // A district's own views (web/local), in front of Core's and modules', once every module has registered its own.
        $this->app->booted(fn () => app(Overrides::class)->register());
    }
}
