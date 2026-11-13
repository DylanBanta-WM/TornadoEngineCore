<?php

namespace App\Administration;

use Illuminate\Support\Facades\View;

/**
 * Extra cards on the Administration page, added by modules for their own settings:
 *
 *     app(Administration::class)->card('gam::administration-card', order: 50);
 *
 * The view is one card (use <x-settings-card>). Its forms post to the module's own routes,
 * which need the "can:administration" middleware.
 */
class Administration
{
    /** @var array<string, int> view => order */
    private array $cards = [];

    public function card(string $view, int $order = 100): static
    {
        $this->cards[$view] = $order;

        return $this;
    }

    /**
     * The module cards, in order. Cards whose view doesn't exist are left out.
     *
     * @return list<string>
     */
    public function cards(): array
    {
        $cards = array_filter($this->cards, fn (int $order, string $view) => View::exists($view), ARRAY_FILTER_USE_BOTH);
        asort($cards);

        return array_keys($cards);
    }
}
