<?php

// A district's patches (web/local/patches): changes to Core (core.patch) and to installed modules
// (<vendor>/<module>.patch), kept outside their files so updates replace those files freely.
// Run from the web folder by composer.sh (composer container), the updater, and the website.
//
//   php patches.php apply          puts every patch that's on onto its files (skips ones already on)
//   php patches.php remove core    takes the Core patch off (before a Core update)
//
// A patch that no longer fits the new version is turned off, not forced; one the new version already
// contains is marked included. Their state is in web/local/patches/state.json. Linked working copies
// (web/modules/<folder>, symlinked into vendor) are never patched: they're the real source.

const DIR = 'local/patches';

function state(): array
{
    return json_decode(@file_get_contents(DIR.'/state.json') ?: '{}', true) ?: [];
}

function save(array $state): void
{
    @mkdir(DIR, 0775, true);
    file_put_contents(DIR.'/state.json', json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
}

/** Where a patch's paths start: the web folder for Core, the module's folder in vendor otherwise. */
function root(string $target): ?string
{
    if ($target === 'core') {
        return getcwd();
    }
    $dir = 'vendor/'.$target;

    return is_dir($dir) && ! is_link($dir) ? realpath($dir) : null;
}

/** git apply outside any repository (the web folder sits inside Core's), in the target's folder. */
function git(string $root, array $args): bool
{
    $cmd = 'cd '.escapeshellarg($root).' && GIT_CEILING_DIRECTORIES='.escapeshellarg(dirname($root)).' git -c safe.directory=* apply '.implode(' ', array_map('escapeshellarg', $args)).' 2>&1';
    exec($cmd, $output, $code);

    return $code === 0;
}

/** @return array<string, string> target => patch file */
function patches(): array
{
    $found = [];
    if (is_file(DIR.'/core.patch')) {
        $found['core'] = realpath(DIR.'/core.patch');
    }
    foreach (glob(DIR.'/*/*.patch') ?: [] as $file) {
        $found[basename(dirname($file)).'/'.basename($file, '.patch')] = realpath($file);
    }

    return $found;
}

function apply(): void
{
    $state = state();
    foreach (patches() as $target => $file) {
        $entry = $state[$target] ?? ['state' => 'on'];
        if (in_array($entry['state'] ?? 'on', ['off', 'included'], true) && ($entry['version'] ?? null) === version($target)) {
            continue; // Off (or already in the release) for this version; a new version gets another look.
        }
        if (! ($root = root($target))) {
            continue; // Not installed, or a linked working copy.
        }
        if (git($root, ['--reverse', '--check', $file])) {
            // Already on. Unless the version changed: an update brings fresh files, so then the release has it.
            $included = isset($entry['version']) && $entry['version'] !== version($target) && ($entry['state'] ?? 'on') !== 'included';
            $state[$target] = $included
                ? ['state' => 'included', 'version' => version($target), 'included_at' => date('c')]
                : ['state' => ($entry['state'] ?? 'on') === 'included' ? 'included' : 'on', 'version' => version($target)] + $entry;

            continue;
        }
        if (git($root, ['--check', $file]) && git($root, [$file])) {
            $state[$target] = ['state' => 'on', 'version' => version($target), 'applied_at' => date('c')];
        } else {
            $state[$target] = ['state' => 'off', 'version' => version($target), 'reason' => 'It doesn\'t fit version '.version($target).'.', 'off_at' => date('c')];
            fwrite(STDERR, "The patch for {$target} doesn't fit version ".version($target)." and is off.\n");
        }
    }
    save($state);
}

function remove(string $target): void
{
    $file = patches()[$target] ?? null;
    $root = root($target);
    if ($file && $root && git($root, ['--reverse', '--check', $file])) {
        git($root, ['--reverse', $file]);
    }
}

/** The installed version of a module, or Core's. */
function version(string $target): string
{
    if ($target === 'core') {
        $config = @file_get_contents('config/tornado.php') ?: '';

        return preg_match("/'version'\\s*=>\\s*'([^']+)'/", $config, $m) ? $m[1] : '?';
    }
    $installed = json_decode(@file_get_contents('vendor/composer/installed.json') ?: '{}', true);
    foreach ($installed['packages'] ?? [] as $package) {
        if (($package['name'] ?? '') === $target) {
            return ltrim((string) ($package['version'] ?? '?'), 'v');
        }
    }

    return '?';
}

match ($argv[1] ?? '') {
    'apply' => apply(),
    'remove' => remove($argv[2] ?? ''),
    default => fwrite(STDERR, "Usage: php patches.php apply | remove <target>\n"),
};
