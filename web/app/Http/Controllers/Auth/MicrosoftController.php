<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\MicrosoftSignIn;
use App\Support\Setup;
use App\Support\SignIn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

/**
 * Microsoft sign-in (Entra ID). Only accounts in the saved tenant can sign in, and only if
 * they've been added (or during setup, to become the admin).
 */
class MicrosoftController extends Controller
{
    public function redirect(MicrosoftSignIn $microsoft): SymfonyRedirect
    {
        abort_unless(MicrosoftSignIn::configured() && SignIn::enabled('microsoft'), 404);

        return $microsoft->redirect();
    }

    public function callback(Request $request, MicrosoftSignIn $microsoft): RedirectResponse
    {
        abort_unless(MicrosoftSignIn::configured() && SignIn::enabled('microsoft'), 404);

        try {
            $account = $microsoft->user();
        } catch (Throwable $e) {
            report($e);

            return $this->failed(__('Microsoft sign-in didn\'t work. Try again.'));
        }

        $email = strtolower((string) $account->getEmail());
        if ($email === '' || ! $account->getId()) {
            return $this->failed(__('Microsoft didn\'t say which account this is.'));
        }

        $user = User::where('microsoft_id', $account->getId())->first()
            ?? User::where('email', $email)->whereNull('microsoft_id')->first();

        if (! $user && $this->choosingAdmin()) {
            $user = new User;
            $user->is_admin = true;
        }

        if (! $user) {
            return $this->failed(__('This account doesn\'t have access to Tornado Engine.'));
        }

        $user->forceFill([
            'microsoft_id' => $account->getId(),
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
        return redirect()->route($this->choosingAdmin() ? 'setup' : 'login')->withErrors(['microsoft' => $message]);
    }

    private function choosingAdmin(): bool
    {
        return ! Setup::complete() && Setup::step() === 'admin';
    }
}
