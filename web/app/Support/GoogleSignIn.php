<?php

namespace App\Support;

use App\Models\Setting;
use Laravel\Socialite\Contracts\User as GoogleUser;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Google sign-in with the client ID, secret and domain saved in setup.
 */
class GoogleSignIn
{
    public static function configured(): bool
    {
        return Setting::get('google.client_id') !== null;
    }

    public static function domain(): ?string
    {
        return Setting::get('google.domain');
    }

    public static function callbackUrl(): string
    {
        return (Setup::address() ?? rtrim(config('app.url'), '/')).'/auth/google/callback';
    }

    public function redirect(): RedirectResponse
    {
        return $this->provider()->redirect();
    }

    public function user(): GoogleUser
    {
        return $this->provider()->user();
    }

    private function provider(): GoogleProvider
    {
        return Socialite::buildProvider(GoogleProvider::class, [
            'client_id' => Setting::get('google.client_id'),
            'client_secret' => Setting::getSecret('google.client_secret'),
            'redirect' => static::callbackUrl(),
        ])->with(['hd' => static::domain(), 'prompt' => 'select_account']);
    }
}
