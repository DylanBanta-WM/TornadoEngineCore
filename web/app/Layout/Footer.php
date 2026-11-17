<?php

namespace App\Layout;

/**
 * The footer on every page with the menu bar. Modules add views to it:
 * app(Footer::class)->add('my-module::footer'), lowest order first.
 */
class Footer
{
    /** @var list<array{view: string, order: int}> */
    private array $views = [];

    public function add(string $view, int $order = 100): static
    {
        $this->views[] = ['view' => $view, 'order' => $order];

        return $this;
    }

    /** @return list<string> */
    public function views(): array
    {
        $views = $this->views;
        usort($views, fn ($a, $b) => $a['order'] <=> $b['order']);

        return array_column($views, 'view');
    }
}
