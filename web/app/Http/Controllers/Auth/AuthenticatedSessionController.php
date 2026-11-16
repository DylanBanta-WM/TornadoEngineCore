<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\PasswordSignIn;
use App\Support\SignIn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Signs in with a website password (when that sign-in method is on).
     */
    public function store(Request $request, PasswordSignIn $others): RedirectResponse
    {
        abort_unless($others->shown(), 404);

        // An email, or with a module's sign-in (for example Active Directory) a username.
        $credentials = $request->validate(['email' => ['required', 'string', 'max:255'], 'password' => ['required', 'string']]);
        $key = 'login:'.strtolower($credentials['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['email' => __('Too many tries. Try again in :seconds seconds.', ['seconds' => RateLimiter::availableIn($key)])])->onlyInput('email');
        }

        $user = SignIn::enabled('password') ? User::where('email', strtolower($credentials['email']))->whereNotNull('password')->first() : null;
        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            $user = $others->attempt($credentials['email'], $credentials['password']);
        }
        if (! $user) {
            RateLimiter::hit($key);

            return back()->withErrors(['email' => __('That sign-in and password don\'t match.')])->onlyInput('email');
        }

        RateLimiter::clear($key);
        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
