<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\User;

/**
 * First-time setup: Google sign-in, the admin account, the harness, then its Claude Code login.
 */
class Setup
{
    public static function complete(): bool
    {
        return Setting::get('setup.completed_at') !== null;
    }

    public static function step(): string
    {
        if (! GoogleSignIn::configured()) {
            return 'google';
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
