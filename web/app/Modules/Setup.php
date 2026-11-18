<?php

namespace App\Modules;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Modules whose settings must be filled in before they work ("needs-setup"), and the way through them:
 * after installing a module, its settings open, but first those of the modules it needs that aren't set up.
 * Saving one opens the next (App\Http\Middleware\AdvanceSetup); the last one stays on its page.
 */
class Setup
{
    public function __construct(private ModuleRegistry $modules) {}

    /** Set up: its "setup-done" check says so, or (without one) its settings have been saved once. */
    public function done(Module $module, bool $fresh = false): bool
    {
        if (! $module->needsSetup || ! $module->compatible || $module->settings === null) {
            return true;
        }
        if ($module->setupDone === null) {
            return Setting::get('setup.saved.'.$module->package) !== null;
        }
        $key = 'setup.done.'.$module->package;
        if ($fresh) {
            Cache::forget($key);
        }
        if (($cached = Cache::get($key)) !== null) {
            return $cached;
        }
        [$class, $method] = explode('@', $module->setupDone, 2);
        try {
            $done = class_exists($class) && (bool) ((new \ReflectionMethod($class, $method))->isStatic() ? $class::$method() : app()->call([app($class), $method]));
        } catch (Throwable) {
            $done = false;
        }
        Cache::put($key, $done, $done ? now()->addHour() : now()->addMinutes(5));

        return $done;
    }

    /** @return list<Module> installed modules still to be set up */
    public function pending(): array
    {
        return array_values(array_filter($this->modules->compatible(), fn (Module $module) => ! $this->done($module)));
    }

    /** @return list<Module> what to set up for this module, in order: the modules it needs first, then itself */
    public function chain(Module $module, array &$seen = []): array
    {
        if (isset($seen[$module->package])) {
            return [];
        }
        $seen[$module->package] = true;
        $all = $this->modules->all();
        $chain = [];
        foreach ($module->requires as $package) {
            if (isset($all[$package])) {
                array_push($chain, ...$this->chain($all[$package], $seen));
            }
        }
        if (! $this->done($module, fresh: true)) {
            $chain[] = $module;
        }

        return $chain;
    }

    /** Starts setting up a module (and what it needs). The settings page to open first, or null if there's nothing to do. */
    public function start(Module $module): ?string
    {
        $chain = $this->chain($module);
        if ($chain === []) {
            session()->forget('setup.chain');

            return null;
        }
        session()->put('setup.chain', array_map(fn (Module $m) => $m->package, $chain));

        return url($chain[0]->settings);
    }

    /** @return list<Module> the rest of the way, the page being set up first */
    public function current(): array
    {
        $all = $this->modules->all();

        return array_values(array_filter(array_map(fn ($package) => $all[$package] ?? null, (array) session('setup.chain', []))));
    }

    /** Its settings were saved from its settings page. The next page to open, if it's the one being set up and it's now done. */
    public function saved(Module $module): ?string
    {
        if ($module->needsSetup) {
            Setting::put('setup.saved.'.$module->package, now()->toIso8601String());
        }
        $chain = (array) session('setup.chain', []);
        if (($chain[0] ?? null) !== $module->package || ! $this->done($module, fresh: true)) {
            return null;
        }
        $all = $this->modules->all();
        $rest = array_values(array_filter(array_slice($chain, 1), fn ($package) => isset($all[$package]) && ! $this->done($all[$package], fresh: true)));
        if ($rest === []) {
            // The last one: stay on its page.
            session()->forget('setup.chain');

            return url($module->settings);
        }
        session()->put('setup.chain', $rest);

        return url($all[$rest[0]]->settings);
    }
}
