<?php

namespace App\Administration;

use Illuminate\Support\Facades\View;

/**
 * The Administration area: a Settings tab (Core's settings, at /administration) and tabs that
 * modules add, each holding cards (views using <x-settings-card>):
 *
 *     $administration->tab('cron', 'Scheduled jobs', order: 20);             // /administration/cron
 *     $administration->card('cron::administration-card', tab: 'cron');
 *
 * A card without a tab goes on the Settings tab. Card forms post to the module's own routes,
 * which need the "can:administration" middleware.
 */
class Administration
{
    public const SETTINGS = 'settings';

    /** @var array<string, array{label: string, order: int}> */
    private array $tabs = [];

    /** @var array<string, array{order: int, tab: string}> view => where it goes */
    private array $cards = [];

    public function tab(string $key, string $label, int $order = 100): static
    {
        $this->tabs[$key] = ['label' => $label, 'order' => $order];

        return $this;
    }

    public function card(string $view, int $order = 100, string $tab = self::SETTINGS): static
    {
        $this->cards[$view] = ['order' => $order, 'tab' => $tab];

        return $this;
    }

    public function has(string $tab): bool
    {
        return $tab === self::SETTINGS || isset($this->tabs[$tab]);
    }

    /**
     * Every tab, Settings first, as [key => label].
     *
     * @return array<string, string>
     */
    public function tabs(): array
    {
        $tabs = $this->tabs;
        uasort($tabs, fn ($a, $b) => [$a['order'], $a['label']] <=> [$b['order'], $b['label']]);

        return [self::SETTINGS => __('Settings')] + array_map(fn ($tab) => $tab['label'], $tabs);
    }

    /**
     * A tab's cards, in order. Cards whose view doesn't exist are left out.
     *
     * @return list<string>
     */
    public function cards(string $tab = self::SETTINGS): array
    {
        $cards = array_filter($this->cards, fn (array $card, string $view) => $card['tab'] === $tab && View::exists($view), ARRAY_FILTER_USE_BOTH);
        uasort($cards, fn ($a, $b) => $a['order'] <=> $b['order']);

        return array_keys($cards);
    }
}
