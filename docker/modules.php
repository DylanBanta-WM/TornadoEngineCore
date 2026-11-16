<?php

// Writes docker/modules.yml: an include for each installed module that brings containers
// ("extra": {"tornado": {"compose": "docker/compose.yml"}} in its composer.json), and
// docker/php-extensions.txt: the PHP extensions modules ask for ("php-extensions": ["ldap"]).
// Run from the web folder by composer.sh (composer container) and the updater.
// Usage: php modules.php [path/to/modules.yml]   (php-extensions.txt goes next to it)

// The extensions docker/php-extensions.sh knows how to install.
const ALLOWED_EXTENSIONS = ['bcmath', 'calendar', 'exif', 'gd', 'gmp', 'intl', 'ldap', 'mysqli', 'pcntl', 'pdo_pgsql', 'pgsql', 'soap', 'sockets', 'zip'];

$installed = json_decode(@file_get_contents('vendor/composer/installed.json') ?: '{}', true);
$includes = [];
$extensions = [];

foreach ($installed['packages'] ?? [] as $package) {
    $tornado = $package['extra']['tornado'] ?? [];
    $own = 'vendor/composer/'.($package['install-path'] ?? '').'/composer.json';
    if (is_file($own)) {
        $tornado = json_decode(file_get_contents($own), true)['extra']['tornado'] ?? $tornado;
    }
    $compose = $tornado['compose'] ?? null;

    foreach ((array) ($tornado['php-extensions'] ?? []) as $extension) {
        if (in_array($extension, ALLOWED_EXTENSIONS, true)) {
            $extensions[] = $extension;
        } else {
            fwrite(STDERR, "{$package['name']} asks for the PHP extension \"{$extension}\", which modules can't add.\n");
        }
    }

    if (is_string($compose) && $compose !== '' && ! str_contains($compose, '..')) {
        $includes[] = 'web/vendor/'.$package['name'].'/'.ltrim($compose, '/');
    }
}

sort($includes);

$yaml = "# Containers that modules bring with them. Written by docker/composer.sh on each start;\n"
    ."# don't edit by hand. A module lists its compose file under \"extra.tornado.compose\".\n";
$yaml .= $includes ? "include:\n" : "include: []\n";
foreach ($includes as $path) {
    $yaml .= "  - path: ../{$path}\n    env_file: ../.env\n";
}

$target = $argv[1] ?? '/tornado/modules.yml';
if (@file_get_contents($target) !== $yaml) {
    file_put_contents($target, $yaml);
    fwrite(STDOUT, "Modules' containers changed. Run \"docker compose up -d\" again to start them.\n");
}

$extensions = array_values(array_unique($extensions));
sort($extensions);
$list = $extensions ? implode("\n", $extensions)."\n" : '';
$extensionsFile = dirname($target).'/php-extensions.txt';
if ((string) @file_get_contents($extensionsFile) !== $list) {
    file_put_contents($extensionsFile, $list);
    fwrite(STDOUT, "PHP extensions changed. Run \"docker compose up -d --build\" to add them.\n");
}
