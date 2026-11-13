<?php

namespace App\Providers;

use App\Modules\ModuleRegistry;
use Illuminate\Support\ServiceProvider;

/**
 * Starts each installed module that works with this version of Core.
 * Modules that need a different Core version stay off (listed by ModuleRegistry::incompatible()).
 */
class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $registry = new ModuleRegistry(
            base_path('vendor/composer/installed.json'),
            config('tornado.version'),
        );

        $this->app->instance(ModuleRegistry::class, $registry);

        foreach ($registry->compatible() as $module) {
            if ($module->provider && class_exists($module->provider)) {
                $this->app->register($module->provider);
            }
        }
    }
}
