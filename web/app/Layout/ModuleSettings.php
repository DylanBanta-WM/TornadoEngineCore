<?php

namespace App\Layout;

use App\Modules\Module;
use App\Modules\ModuleRegistry;

/**
 * Cards other modules add to every module's settings page (for example Chat's "What Claude knows
 * about this module"). Each view gets $module: the module whose settings page this is.
 */
class ModuleSettings
{
    /** @var list<string> */
    private array $views = [];

    public function add(string $view): static
    {
        $this->views[] = $view;

        return $this;
    }

    /** @return list<string> */
    public function views(): array
    {
        return $this->views;
    }

    /** The module whose settings page is at this path, if any. */
    public function moduleAt(string $path): ?Module
    {
        $path = '/'.trim($path, '/');
        foreach (app(ModuleRegistry::class)->compatible() as $module) {
            if ($module->settings !== null && rtrim($module->settings, '/') === $path) {
                return $module;
            }
        }

        return null;
    }
}
