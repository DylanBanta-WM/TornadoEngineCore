<?php

namespace App\Support;

use App\Models\Setting;

/**
 * How people sign in: Google, Microsoft (Entra ID), and/or passwords kept by the website.
 * Chosen during first-time setup; changed under Administration.
 */
class SignIn
{
    public const METHODS = ['google' => 'Google', 'microsoft' => 'Microsoft', 'password' => 'Website passwords'];

    /** The password new accounts get when none is typed. */
    public const DEFAULT_PASSWORD = 'ChangeMe!';

    /** @return list<string> */
    public static function methods(): array
    {
        $saved = json_decode(Setting::get('signin.methods') ?? 'null', true);
        if (is_array($saved)) {
            return array_values(array_intersect(array_keys(self::METHODS), $saved));
        }

        // Before sign-in methods could be chosen, Google was the only one.
        return GoogleSignIn::configured() ? ['google'] : [];
    }

    public static function chosen(): bool
    {
        return Setting::get('signin.methods') !== null || GoogleSignIn::configured();
    }

    public static function enabled(string $method): bool
    {
        return in_array($method, self::methods(), true);
    }

    /** @param  list<string>  $methods */
    public static function save(array $methods): void
    {
        Setting::put('signin.methods', json_encode(array_values(array_intersect(array_keys(self::METHODS), $methods))));
    }
}
