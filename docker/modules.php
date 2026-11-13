<?php

// Writes docker/modules.yml: an include for each installed module that brings containers
// ("extra": {"tornado": {"compose": "docker/compose.yml"}} in its composer.json).
// Run by composer.sh inside the composer container, from the web folder.

$installed = json_decode(@file_get_contents('vendor/composer/installed.json') ?: '{}', true);
$includes = [];

foreach ($installed['packages'] ?? [] as $package) {
    $compose = $package['extra']['tornado']['compose'] ?? null;
    $own = 'vendor/composer/'.($package['install-path'] ?? '').'/composer.json';
    if (is_file($own)) {
        $compose = json_decode(file_get_contents($own), true)['extra']['tornado']['compose'] ?? $compose;
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

$target = '/tornado/modules.yml';
if (@file_get_contents($target) !== $yaml) {
    file_put_contents($target, $yaml);
    fwrite(STDOUT, "Modules' containers changed. Run \"docker compose up -d\" again to start them.\n");
}
