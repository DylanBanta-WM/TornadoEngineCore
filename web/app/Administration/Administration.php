<?php

namespace App\Administration;

use Illuminate\Support\Facades\Route;

/**
 * The Administration area's sections. Modules add theirs in their service provider's boot():
 *
 *     app(Administration::class)->add('Docs', 'administration.docs.index', active: 'administration.docs.*');
 *
 * A module's admin pages go under /administration with the "web", "auth" and "admin" middleware,
 * and use the <x-admin-layout> component.
 */
class Administration
{
    /** @var array<string, Section> */
    private array $sections = [];

    /**
     * @param  string|null  $active  Route name pattern for the section's pages; defaults to the route itself.
     * @param  int  $order  Lower numbers come first.
     */
    public function add(string $label, string $route, ?string $active = null, int $order = 100, ?string $description = null): static
    {
        $this->sections[$route] = new Section($label, $route, $active ?? $route, $order, $description);

        return $this;
    }

    /**
     * The sections, in order. Sections whose route doesn't exist are left out.
     *
     * @return list<Section>
     */
    public function sections(): array
    {
        $sections = array_filter($this->sections, fn (Section $section) => Route::has($section->route));

        usort($sections, fn (Section $a, Section $b) => [$a->order, $a->label] <=> [$b->order, $b->label]);

        return $sections;
    }
}
