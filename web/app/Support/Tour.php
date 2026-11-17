<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * The tour after first-time setup: a few steps across pages, each pointing at something on the
 * page. Someone's place is kept in users.tour_step. Steps on pages that aren't installed are left out.
 */
class Tour
{
    /**
     * @return array<string, array{route: string, text: string, targets?: list<string>, open?: list<string>, closest?: string, link?: array{0: string|list<string>, 1: string}}>
     */
    public static function steps(): array
    {
        $tabs = array_filter([
            'access.google.index' => __('Google: roles from Google OUs, Groups and users.'),
            'access.ad.index' => __('Active Directory: roles from AD OUs, groups and users.'),
            'access.entra.index' => __('Entra ID: roles from Entra ID groups and users.'),
        ], fn ($text, $route) => Route::has($route), ARRAY_FILTER_USE_BOTH);

        $steps = [
            'admin-menu' => [
                'route' => 'dashboard',
                'targets' => ['[data-tour="nav-group-administration"]', '[data-tour="menu-button"]'],
                'text' => __('Admin pages are in the Administration menu.'),
            ],
            'users-and-roles' => [
                'route' => 'dashboard',
                'open' => ['[data-tour="nav-group-administration"]', '[data-tour="menu-button"]'],
                'targets' => ['[data-tour="nav-item-access.users.index"]', '[data-tour="nav-mobile-access.users.index"]'],
                'text' => __('Users and roles: who can sign in, and what they can open.'),
            ],
            'access-tabs' => [
                'route' => 'access.users.index',
                'targets' => ['[data-tour="access-tab-users"]'],
                'closest' => 'nav',
                'text' => implode("\n", array_merge([
                    __('Users: people who can sign in.'),
                    __('Roles: what each role can open.'),
                    __('Email patterns: roles by email address.'),
                    __('Excluded: people imports leave out.'),
                ], array_values($tabs))),
            ],
            'access-imports' => [
                'route' => 'access.users.index',
                'text' => __('Give people roles here by hand, or from Google OUs and Groups with Access-Google, and from Microsoft AD or Entra ID with Access-Microsoft-AD and Access-Microsoft-Entra. You can install those on the Modules page.'),
            ],
            'modules' => [
                'route' => 'modules.index',
                'text' => implode("\n", [
                    __('Core modules are always installed.'),
                    __('Official modules are made by Tornado Engine.'),
                    __('Community modules are made by others. Read their code before you install one.'),
                ]),
            ],
            'share' => [
                'route' => 'modules.index',
                'text' => __('Made a module other districts could use? Share it.'),
                'link' => [['docs.show', 'sharing-modules'], __('Sharing a module')],
            ],
            'docs' => [
                'route' => 'docs.index',
                'text' => __('Guides for setting up and using Tornado Engine.'),
            ],
            'chat' => [
                'route' => 'chat.index',
                'text' => __('Ask Claude for a new tool, and it builds it.'),
            ],
        ];

        return array_filter($steps, fn ($step) => Route::has($step['route']) && (! isset($step['link']) || Route::has(((array) $step['link'][0])[0])));
    }

    public static function start(User $user): void
    {
        $user->forceFill(['tour_step' => array_key_first(self::steps())])->save();
    }

    /** @return array{key: string, number: int, total: int, step: array<string, mixed>}|null */
    public static function current(?User $user): ?array
    {
        $steps = self::steps();
        if (! $user?->tour_step || ! isset($steps[$user->tour_step])) {
            return null;
        }
        $keys = array_keys($steps);

        return ['key' => $user->tour_step, 'number' => array_search($user->tour_step, $keys) + 1, 'total' => count($keys), 'step' => $steps[$user->tour_step]];
    }

    /** Moves by $by steps (1 or -1); past the last step, the tour ends. */
    public static function move(User $user, int $by): ?string
    {
        $keys = array_keys(self::steps());
        $index = array_search($user->tour_step, $keys, true);
        $next = $index === false ? null : ($keys[max(0, $index + $by)] ?? null);
        $user->forceFill(['tour_step' => $next])->save();

        return $next;
    }

    public static function end(User $user): void
    {
        $user->forceFill(['tour_step' => null])->save();
    }
}
