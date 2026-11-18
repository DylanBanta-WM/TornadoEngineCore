<?php

namespace App\Layout;

/**
 * The menu bar, beside the user menu (a notification bell, say). Modules add views to it:
 * app(Navbar::class)->add('my-module::navbar'), lowest order first.
 */
class Navbar
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
