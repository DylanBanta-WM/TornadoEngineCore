<?php

return [

    // Tornado Engine Core's version. Modules say which Core versions they work with.
    'version' => '0.9.0',

    // Official modules and Core releases come from this GitHub account and repository.
    'official_owner' => 'DylanBanta-WM',
    'core_repository' => 'DylanBanta-WM/TornadoEngineCore',

    // Where the updater service sees the whole Tornado Engine folder (for git and docker/).
    'repository_path' => '/tornado',

    // How long the updater waits for the styles to rebuild (seconds); 0 skips it.
    // composer_local: where web/composer.local.json is (tests point it elsewhere).
    'updater' => ['assets_timeout' => 600, 'composer_local' => null],

];
