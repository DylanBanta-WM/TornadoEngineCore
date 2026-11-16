<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\User;

/**
 * First-time setup: the web address, Google sign-in, the admin account, the harness, then its Claude Code login.
 */
class Setup
{
    /** Every step, in order. Google and Microsoft only appear when they're chosen at Sign-in. */
    public const STEPS = ['address', 'signin', 'google', 'microsoft', 'admin', 'harness', 'claude'];

    /** Steps someone can do before the admin account exists. */
    public const BEFORE_ADMIN = ['address', 'signin', 'google', 'microsoft', 'admin'];

    /** @return list<string> the steps for the sign-in methods chosen so far */
    public static function steps(): array
    {
        return array_values(array_filter(self::STEPS, fn ($step) => ! in_array($step, ['google', 'microsoft'], true) || SignIn::enabled($step)));
    }

    public static function complete(): bool
    {
        return Setting::get('setup.completed_at') !== null;
    }

    /**
     * The site's https address, e.g. https://te.example.org.
     */
    public static function address(): ?string
    {
        return Setting::get('site.url');
    }

    /**
     * Whether this request reached the site over its saved https address.
     */
    public static function onAddress(): bool
    {
        $address = static::address();
        $request = request();

        return $address !== null
            && $request->isSecure()
            && strcasecmp($request->getSchemeAndHttpHost(), $address) === 0;
    }

    public static function step(): string
    {
        if (! static::onAddress()) {
            return 'address';
        }

        if (! SignIn::chosen()) {
            return 'signin';
        }

        if (SignIn::enabled('google') && ! GoogleSignIn::configured()) {
            return 'google';
        }

        if (SignIn::enabled('microsoft') && ! MicrosoftSignIn::configured()) {
            return 'microsoft';
        }

        if (! User::where('is_admin', true)->exists()) {
            return 'admin';
        }

        if (Setting::get('harness.last_seen_at') === null) {
            return 'harness';
        }

        return 'claude';
    }
}
