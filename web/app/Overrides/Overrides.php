<?php

namespace App\Overrides;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\View;
use RuntimeException;
use SebastianBergmann\Diff\Differ;
use SebastianBergmann\Diff\Output\UnifiedDiffOutputBuilder;

/**
 * A district's own changes to how Core and modules look, kept in web/local (which updates never touch):
 *  - local/views/<namespace>/… replaces that module's view (local/views/core/… for Core's own),
 *  - local/local.css is loaded after everything else,
 *  - local/.originals keeps each overridden view as it was, so a later update that changes it is noticed.
 */
class Overrides
{
    public const CORE = 'core';

    public function dir(string $path = ''): string
    {
        return base_path('local'.($path !== '' ? '/'.$path : ''));
    }

    /** Puts local views in front of the originals. Not on the Overrides page, so a broken override can be fixed there. */
    public function register(): void
    {
        if (! app()->runningInConsole() && request()->is('administration/overrides*')) {
            return;
        }

        foreach (array_keys($this->namespaces()) as $namespace) {
            $dir = $this->dir("views/{$namespace}");
            if (! is_dir($dir)) {
                continue;
            }
            $namespace === self::CORE ? View::getFinder()->prependLocation($dir) : View::getFinder()->prependNamespace($namespace, $dir);
        }
    }

    /** @return array<string, string> Core's and modules' view namespaces => their own folder */
    public function namespaces(): array
    {
        $namespaces = [self::CORE => resource_path('views')];
        $local = $this->dir();
        foreach (View::getFinder()->getHints() as $namespace => $paths) {
            foreach ($paths as $path) {
                if (str_starts_with($path, $local)) {
                    continue;
                }
                $real = realpath($path) ?: $path;
                if (str_contains($real, '/vendor/tornado-engine/') || str_starts_with($real, base_path('modules/')) || str_starts_with($real, realpath(base_path('modules')) ?: base_path('modules'))) {
                    $namespaces[$namespace] = $path;
                }
                break;
            }
        }
        ksort($namespaces);

        return $namespaces;
    }

    /** @return list<string> every view that can be overridden, as namespace::view */
    public function views(): array
    {
        $views = [];
        foreach ($this->namespaces() as $namespace => $dir) {
            foreach (File::allFiles($dir) as $file) {
                if (str_ends_with($file->getFilename(), '.blade.php')) {
                    $views[] = $namespace.'::'.str_replace('/', '.', substr($file->getRelativePathname(), 0, -10));
                }
            }
        }
        sort($views);

        return $views;
    }

    /**
     * Copies a view into local/views to change, keeping the original as it was.
     */
    public function create(string $name): void
    {
        $original = $this->original($name);
        if (! is_file($original)) {
            throw new RuntimeException(__('There\'s no view called :name.', ['name' => $name]));
        }
        if (is_file($this->local($name))) {
            throw new RuntimeException(__(':name is already overridden.', ['name' => $name]));
        }
        File::ensureDirectoryExists(dirname($this->local($name)));
        File::ensureDirectoryExists(dirname($this->base($name)));
        copy($original, $this->local($name));
        copy($original, $this->base($name));
        $this->rebuildStyles();
    }

    /** Saves a changed override, if it compiles. */
    public function save(string $name, string $contents): void
    {
        $this->mustExist($name);
        $compiled = tempnam(sys_get_temp_dir(), 'override');
        file_put_contents($compiled, Blade::compileString($contents));
        $check = Process::run(['php', '-l', $compiled]);
        @unlink($compiled);
        if (! $check->successful()) {
            throw new RuntimeException(__('That has an error, so it wasn\'t saved: :error', ['error' => trim(preg_replace('/ in \S+ on line/', ' on line', strtok($check->output().$check->errorOutput(), "\n")))]));
        }
        file_put_contents($this->local($name), $contents);
        $this->rebuildStyles();
    }

    /** The module's change has been looked at: compare against the current original from now on. */
    public function review(string $name): void
    {
        $this->mustExist($name);
        copy($this->original($name), $this->base($name));
    }

    public function remove(string $name): void
    {
        $this->mustExist($name);
        @unlink($this->local($name));
        @unlink($this->base($name));
        $this->rebuildStyles();
    }

    /**
     * @return list<array{name: string, status: string, contents: string, diff: ?string}>
     *                                                                                    status: current, changed (the module changed the original since), or gone (it no longer has it)
     */
    public function all(): array
    {
        $dir = $this->dir('views');
        if (! is_dir($dir)) {
            return [];
        }

        $overrides = [];
        foreach (File::allFiles($dir) as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }
            $parts = explode('/', $file->getRelativePathname(), 2);
            if (count($parts) < 2) {
                continue;
            }
            $name = $parts[0].'::'.str_replace('/', '.', substr($parts[1], 0, -10));
            $original = $this->original($name);
            $base = (string) @file_get_contents($this->base($name));
            $status = ! is_file($original) ? 'gone' : (sha1_file($original) === sha1($base) ? 'current' : 'changed');
            $overrides[] = [
                'name' => $name,
                'status' => $status,
                'contents' => (string) file_get_contents($file->getPathname()),
                'diff' => $status === 'changed' ? rtrim((new Differ(new UnifiedDiffOutputBuilder("--- before\n+++ now\n")))->diff($base, (string) file_get_contents($original))) : null,
            ];
        }
        usort($overrides, fn ($a, $b) => $a['name'] <=> $b['name']);

        return $overrides;
    }

    /** How many overrides need looking at after an update. */
    public function needingReview(): int
    {
        return count(array_filter($this->all(), fn ($override) => $override['status'] !== 'current'));
    }

    public function css(): string
    {
        return (string) @file_get_contents($this->dir('local.css'));
    }

    public function saveCss(string $css): void
    {
        File::ensureDirectoryExists($this->dir());
        trim($css) === '' ? @unlink($this->dir('local.css')) : file_put_contents($this->dir('local.css'), $css);
        $this->rebuildStyles();
    }

    /** Whether local.css has been built, so pages can load it. */
    public static function cssBuilt(): bool
    {
        static $built = null;

        return $built ??= is_file(base_path('local/local.css'))
            && array_key_exists('local/local.css', (array) json_decode((string) @file_get_contents(public_path('build/manifest.json')), true));
    }

    /** Asks the assets-watcher service to rebuild the styles (for local.css, and Tailwind classes in local views). */
    public function rebuildStyles(): void
    {
        $dir = storage_path('app/tornado');
        File::ensureDirectoryExists($dir);
        file_put_contents("{$dir}/assets.request", now()->toIso8601String());
    }

    private function split(string $name): array
    {
        $parts = explode('::', $name, 2);
        if (count($parts) !== 2 || ! preg_match('/^[\w-]+$/', $parts[0]) || ! preg_match('/^[\w-]+(\.[\w-]+)*$/', $parts[1])) {
            throw new RuntimeException(__('There\'s no view called :name.', ['name' => $name]));
        }

        return [$parts[0], str_replace('.', '/', $parts[1]).'.blade.php'];
    }

    private function original(string $name): string
    {
        [$namespace, $path] = $this->split($name);

        return ($this->namespaces()[$namespace] ?? '/nonexistent').'/'.$path;
    }

    private function local(string $name): string
    {
        [$namespace, $path] = $this->split($name);

        return $this->dir("views/{$namespace}/{$path}");
    }

    private function base(string $name): string
    {
        [$namespace, $path] = $this->split($name);

        return $this->dir(".originals/{$namespace}/{$path}");
    }

    private function mustExist(string $name): void
    {
        if (! is_file($this->local($name))) {
            throw new RuntimeException(__(':name isn\'t overridden.', ['name' => $name]));
        }
    }
}
