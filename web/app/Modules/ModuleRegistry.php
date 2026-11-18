<?php

namespace App\Modules;

use Composer\Semver\Semver;
use Throwable;

/**
 * The installed modules: Composer packages with a "tornado" section under "extra" in their composer.json.
 *
 *     "extra": { "tornado": { "name": "Nav", "core": "^0.1", "core-module": true, "provider": "Vendor\\Nav\\NavServiceProvider" } }
 */
class ModuleRegistry
{
    /** @var array<string, Module> */
    private array $modules = [];

    public function __construct(string $installedJson, string $coreVersion)
    {
        $installed = is_file($installedJson) ? json_decode(file_get_contents($installedJson), true) : [];

        foreach ($installed['packages'] ?? [] as $package) {
            $tornado = $package['extra']['tornado'] ?? null;

            // Read the module's own composer.json when it's there, so a module being developed
            // in modules/ is seen as it is now, not as it was when Composer last installed it.
            $own = dirname($installedJson).'/'.($package['install-path'] ?? '').'/composer.json';
            $requires = array_keys((array) ($package['require'] ?? []));
            if (isset($package['install-path']) && is_file($own)) {
                $composer = json_decode(file_get_contents($own), true);
                $tornado = $composer['extra']['tornado'] ?? $tornado;
                $requires = array_keys((array) ($composer['require'] ?? $package['require'] ?? []));
            }

            if (! is_array($tornado)) {
                continue;
            }

            $core = (string) ($tornado['core'] ?? '');
            // It stays off until the image has its PHP extensions (docker compose up -d --build).
            $missing = array_values(array_filter((array) ($tornado['php-extensions'] ?? []), fn ($extension) => ! extension_loaded((string) $extension)));

            $this->modules[$package['name']] = new Module(
                package: $package['name'],
                name: (string) ($tornado['name'] ?? $package['name']),
                version: (string) ($package['pretty_version'] ?? $package['version'] ?? ''),
                core: $core,
                coreModule: (bool) ($tornado['core-module'] ?? false),
                provider: $tornado['provider'] ?? null,
                compatible: $core !== '' && $this->satisfies($coreVersion, $core) && ! $missing,
                missingExtensions: $missing,
                settings: is_string($tornado['settings'] ?? null) && str_starts_with($tornado['settings'], '/') ? $tornado['settings'] : null,
                needsSetup: (bool) ($tornado['needs-setup'] ?? false),
                prompt: is_string($tornado['prompt'] ?? null) && trim($tornado['prompt']) !== '' ? trim($tornado['prompt']) : null,
                requires: $requires,
                setupDone: is_string($tornado['setup-done'] ?? null) && str_contains($tornado['setup-done'], '@') ? $tornado['setup-done'] : null,
                path: isset($package['install-path']) ? (realpath(dirname($installedJson).'/'.$package['install-path']) ?: null) : null,
            );
        }
    }

    /** @return array<string, Module> */
    public function all(): array
    {
        return $this->modules;
    }

    /** @return array<string, Module> */
    public function compatible(): array
    {
        return array_filter($this->modules, fn (Module $module) => $module->compatible);
    }

    /** @return array<string, Module> */
    public function incompatible(): array
    {
        return array_filter($this->modules, fn (Module $module) => ! $module->compatible);
    }

    public function has(string $package): bool
    {
        return isset($this->compatible()[$package]);
    }

    private function satisfies(string $version, string $constraint): bool
    {
        try {
            return Semver::satisfies($version, $constraint);
        } catch (Throwable) {
            return false;
        }
    }
}
