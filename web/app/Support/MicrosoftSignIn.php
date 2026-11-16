<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as MicrosoftUser;

/**
 * Microsoft sign-in (Entra ID), with the app registration saved during setup or in Administration.
 */
class MicrosoftSignIn
{
    public static function configured(): bool
    {
        return Setting::get('microsoft.client_id') !== null;
    }

    public static function tenant(): ?string
    {
        return Setting::get('microsoft.tenant_id');
    }

    public static function callbackUrl(): string
    {
        return (Setup::address() ?? rtrim(config('app.url'), '/')).'/auth/microsoft/callback';
    }

    public function redirect(): RedirectResponse
    {
        return $this->provider()->redirect();
    }

    public function user(): MicrosoftUser
    {
        return $this->provider()->user();
    }

    private function provider(): MicrosoftProvider
    {
        return Socialite::buildProvider(MicrosoftProvider::class, [
            'client_id' => Setting::get('microsoft.client_id'),
            'client_secret' => Setting::getSecret('microsoft.client_secret'),
            'redirect' => static::callbackUrl(),
        ])->with(['tenant' => static::tenant(), 'prompt' => 'select_account']);
    }
}
