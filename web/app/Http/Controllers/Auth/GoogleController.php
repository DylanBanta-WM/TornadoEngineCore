<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\GoogleSignIn;
use App\Support\Setup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

class GoogleController extends Controller
{
    public function redirect(GoogleSignIn $google): SymfonyRedirect
    {
        abort_unless(GoogleSignIn::configured(), 404);

        return $google->redirect();
    }

    public function callback(Request $request, GoogleSignIn $google): RedirectResponse
    {
        abort_unless(GoogleSignIn::configured(), 404);

        try {
            $account = $google->user();
        } catch (Throwable) {
            return $this->failed(__('Google sign-in didn\'t work. Try again.'));
        }

        $raw = $account->getRaw();
        $domain = GoogleSignIn::domain();

        if (! ($raw['email_verified'] ?? false) || strcasecmp($raw['hd'] ?? '', $domain) !== 0) {
            return $this->failed(__('Sign in with a :domain account.', ['domain' => $domain]));
        }

        $email = strtolower($account->getEmail());
        $user = User::where('google_id', $account->getId())->first()
            ?? User::where('email', $email)->whereNull('google_id')->first();

        if (! $user && $this->choosingAdmin()) {
            $user = new User;
            $user->is_admin = true;
        }

        if (! $user) {
            return $this->failed(__('This account doesn\'t have access to Tornado Engine.'));
        }

        $user->forceFill([
            'google_id' => $account->getId(),
            'name' => $account->getName() ?: $email,
            'email' => $email,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    private function failed(string $message): RedirectResponse
    {
        $route = $this->choosingAdmin() ? 'setup' : 'login';

        return redirect()->route($route)->withErrors(['google' => $message]);
    }

    /**
     * During setup, the first school account to sign in becomes the admin.
     */
    private function choosingAdmin(): bool
    {
        return ! Setup::complete() && Setup::step() === 'admin';
    }
}
