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
        /** @var list<string> PHP extensions it needs that this server doesn't have yet. */
        public array $missingExtensions = [],
        /** Its settings page, e.g. "/administration/gam" ("settings" in extra.tornado). */
        public ?string $settings = null,
        /** It needs its settings filled in before it works ("needs-setup" in extra.tornado). */
        public bool $needsSetup = false,
    ) {}
}
