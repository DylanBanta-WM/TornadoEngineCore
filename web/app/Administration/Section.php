<?php

namespace App\Administration;

/**
 * One section of the Administration area, added by a module.
 */
final readonly class Section
{
    public function __construct(
        public string $label,
        public string $route,
        public string $active,
        public int $order,
        public ?string $description,
    ) {}
}
