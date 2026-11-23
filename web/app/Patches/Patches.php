<?php

namespace App\Patches;

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * A district's patches: changes to Core and to installed modules, kept in web/local/patches (core.patch,
 * <vendor>/<module>.patch) instead of in their files, so updates replace those files freely. The updater
 * puts them back on after every install and update (docker/patches.php); one that no longer fits is
 * turned off, and one a release already contains is marked included.
 */
class Patches
{
    /** The web folder (tests use a scratch one). */
    private string $base;

    public function __construct(?string $base = null)
    {
        $this->base = rtrim($base ?? base_path(), '/');
    }

    /** Core's own folders a patch can change (relative to the web folder). */
    public const CORE_PATHS = ['app/', 'bootstrap/app.php', 'config/', 'database/', 'resources/', 'routes/', 'tests/'];

    public function dir(): string
    {
        return $this->base.'/local/patches';
    }

    /**
     * What a path belongs to: Core, or an installed Tornado Engine module.
     *
     * @return array{target: string, root: string, path: string}
     */
    public function locate(string $path): array
    {
        $path = ltrim(Str::after(Str::after($path, '/workspace/web/'), $this->base.'/'), '/');
        if (str_contains($path, '..')) {
            throw new RuntimeException(__('That path isn\'t allowed.'));
        }

        if (preg_match('#^vendor/([a-z0-9_.-]+/[a-z0-9_.-]+)/(.+)$#', $path, $m)) {
            $root = $this->base."/vendor/{$m[1]}";
            if (is_link($root)) {
                throw new RuntimeException(__(':package is a working copy in web/modules: change it there instead.', ['package' => $m[1]]));
            }
            $composer = json_decode((string) @file_get_contents("{$root}/composer.json"), true);
            if (! isset($composer['extra']['tornado'])) {
                throw new RuntimeException(__('Only Core and Tornado Engine modules can be patched.'));
            }

            return ['target' => $m[1], 'root' => $root, 'path' => $m[2]];
        }

        foreach (self::CORE_PATHS as $allowed) {
            if (str_starts_with($path, $allowed)) {
                return ['target' => 'core', 'root' => $this->base, 'path' => $path];
            }
        }

        throw new RuntimeException(__('Patches change Core\'s code or an installed module\'s, not :path. Modules in web/modules and overrides in web/local are changed directly.', ['path' => $path]));
    }

    public function file(string $target): string
    {
        return $target === 'core' ? $this->dir().'/core.patch' : $this->dir()."/{$target}.patch";
    }

    /**
     * Changes a file of Core or an installed module through its patch: the patch records the difference from
     * the released file, and the change is made now.
     */
    public function change(string $path, string $content): string
    {
        ['target' => $target, 'root' => $root, 'path' => $relative] = $this->locate($path);
        $patch = $this->file($target);
        $current = "{$root}/{$relative}";

        $tmp = sys_get_temp_dir().'/patches-'.Str::random(8);
        File::ensureDirectoryExists("{$tmp}/old/".dirname($relative));
        File::ensureDirectoryExists("{$tmp}/new/".dirname($relative));
        try {
            // The released file: what's there now, without this patch's part for it.
            $released = is_file($current) ? (string) file_get_contents($current) : null;
            $blocks = $this->blocks(is_file($patch) ? (string) file_get_contents($patch) : '');
            if ($released !== null && isset($blocks[$relative])) {
                file_put_contents("{$tmp}/old/{$relative}", $released);
                file_put_contents("{$tmp}/block.patch", $blocks[$relative]);
                if (! $this->git("{$tmp}/old", ['apply', '--reverse', "{$tmp}/block.patch"])->successful()) {
                    throw new RuntimeException(__('The patch for :target doesn\'t match :path any more. Its file was changed some other way.', ['target' => $target, 'path' => $relative]));
                }
                $released = (string) file_get_contents("{$tmp}/old/{$relative}");
            } elseif ($released !== null) {
                file_put_contents("{$tmp}/old/{$relative}", $released);
            }
            file_put_contents("{$tmp}/new/{$relative}", $content);

            $diff = $this->git($tmp, ['diff', '--no-index', '--no-color', $released === null ? '/dev/null' : "old/{$relative}", "new/{$relative}"])->output();
            $diff = str_replace(["a/old/{$relative}", "a/new/{$relative}", "b/new/{$relative}"], ["a/{$relative}", "a/{$relative}", "b/{$relative}"], $diff);
            if ($diff === '') {
                unset($blocks[$relative]); // Back to the released file.
            } else {
                $blocks[$relative] = $diff;
            }
        } finally {
            File::deleteDirectory($tmp);
        }

        // The change, now; then the patch that keeps it.
        File::ensureDirectoryExists(dirname($current));
        file_put_contents($current, $content);
        ksort($blocks);
        if ($blocks === []) {
            @unlink($patch);
        } else {
            File::ensureDirectoryExists(dirname($patch));
            file_put_contents($patch, implode('', $blocks));
        }
        $state = $this->state();
        $state[$target] = $blocks === [] ? null : ['state' => 'on', 'version' => $this->version($target), 'applied_at' => now()->toIso8601String()];
        $this->saveState(array_filter($state));

        return $target;
    }

    /** Takes a patch off and deletes it. */
    public function remove(string $target): void
    {
        $patch = $this->file($target);
        if (is_file($patch)) {
            $root = $target === 'core' ? $this->base : $this->base."/vendor/{$target}";
            if (is_dir($root) && ! is_link($root)) {
                $this->git($root, ['apply', '--reverse', $patch]);
            }
            @unlink($patch);
        }
        $state = $this->state();
        unset($state[$target]);
        $this->saveState($state);
    }

    /** @return array<string, array{state: string, version?: string, reason?: string, files: list<string>}> */
    public function all(): array
    {
        $found = [];
        $state = $this->state();
        foreach (array_merge(glob($this->dir().'/core.patch') ?: [], glob($this->dir().'/*/*.patch') ?: []) as $file) {
            $target = basename($file) === 'core.patch' ? 'core' : basename(dirname($file)).'/'.basename($file, '.patch');
            $found[$target] = ($state[$target] ?? ['state' => 'on']) + ['files' => array_keys($this->blocks((string) file_get_contents($file)))];
        }

        return $found;
    }

    /** @return list<string> targets whose patch is off (it didn't fit an update) */
    public function off(): array
    {
        return array_keys(array_filter($this->all(), fn ($patch) => $patch['state'] === 'off'));
    }

    /** @return array<string, string> a patch split by file: path => its part */
    private function blocks(string $patch): array
    {
        $blocks = [];
        foreach (preg_split('/^(?=diff --git )/m', $patch, -1, PREG_SPLIT_NO_EMPTY) as $block) {
            if (preg_match('#^diff --git a/(\S+) b/#', $block, $m)) {
                $blocks[$m[1]] = $block;
            }
        }

        return $blocks;
    }

    private function state(): array
    {
        return json_decode((string) @file_get_contents($this->dir().'/state.json'), true) ?: [];
    }

    private function saveState(array $state): void
    {
        File::ensureDirectoryExists($this->dir());
        file_put_contents($this->dir().'/state.json', json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    }

    private function version(string $target): string
    {
        if ($target === 'core') {
            return (string) config('tornado.version');
        }
        $installed = json_decode((string) @file_get_contents($this->base.'/vendor/composer/installed.json'), true);
        foreach ($installed['packages'] ?? [] as $package) {
            if (($package['name'] ?? '') === $target) {
                return ltrim((string) ($package['version'] ?? '?'), 'v');
            }
        }

        return '?';
    }

    /** git outside any repository (the web folder sits inside Core's). */
    private function git(string $cwd, array $args): ProcessResult
    {
        return Process::path($cwd)->env(['GIT_CEILING_DIRECTORIES' => dirname($cwd), 'GIT_CONFIG_COUNT' => '1', 'GIT_CONFIG_KEY_0' => 'safe.directory', 'GIT_CONFIG_VALUE_0' => '*', 'HOME' => sys_get_temp_dir()])
            ->run(array_merge(['git'], $args));
    }
}
