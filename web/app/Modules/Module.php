<?php

namespace App\Modules;

/**
 * An installed module, from the "tornado" section of its composer.json.
 */
final readonly class Module
{
    public function __construct(
        public string $package,
        public string $name,
        public string $version,
        public string $core,
        public bool $coreModule,
        public ?string $provider,
        public bool $compatible,
    ) {}
}
