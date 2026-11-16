<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\User;
use App\Support\ConnectionCheck;
use App\Support\GoogleSignIn;
use App\Support\MicrosoftSignIn;
use App\Support\Settings;
use App\Support\Setup;
use App\Support\SignIn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class SetupController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        if (Setup::complete()) {
            return redirect()->route('dashboard');
        }

        $current = Setup::step();

        if (! in_array($current, Setup::BEFORE_ADMIN, true) && ! $request->user()?->is_admin) {
            return redirect()->route('login');
        }

        // ?step= looks back at a step that's already done.
        $steps = Setup::steps();
        $now = array_search($current, $steps);
        $asked = array_search($request->query('step'), $steps, true);
        $index = $asked !== false && $asked < $now ? $asked : $now;
        $step = $steps[$index];

        return view('setup.index', [
            'step' => $step,
            'current' => $current,
            'reviewing' => $step !== $current,
            'back' => $index > 0 ? route('setup', ['step' => $steps[$index - 1]]) : null,
            'next' => $index + 1 < $now ? route('setup', ['step' => $steps[$index + 1]]) : route('setup'),
            'user' => $request->user(),
            'address' => Setup::address(),
            'addressLocked' => User::where('is_admin', true)->exists(),
            'harnessToken' => session('harness_token'),
            'domain' => GoogleSignIn::domain(),
            'callbackUrl' => GoogleSignIn::callbackUrl(),
            'microsoftCallbackUrl' => MicrosoftSignIn::callbackUrl(),
            'tenant' => MicrosoftSignIn::tenant(),
            'methods' => SignIn::methods(),
            'steps' => $steps,
        ]);
    }

    public function storeAddress(Request $request): RedirectResponse
    {
        abort_if(User::where('is_admin', true)->exists(), 403);

        $address = Settings::saveAddress($request->validate(Settings::addressRules())['address']);

        // Go straight on at the new address.
        return redirect()->away($address.'/setup');
    }

    public function storeSignin(Request $request): RedirectResponse
    {
        abort_if(User::where('is_admin', true)->exists(), 403);

        $data = $request->validate([
            'methods' => ['required', 'array', 'min:1'],
            'methods.*' => ['in:'.implode(',', array_keys(SignIn::METHODS))],
        ], ['methods.required' => __('Choose at least one.')]);
        SignIn::save($data['methods']);

        return redirect()->route('setup');
    }

    public function storeMicrosoft(Request $request, ConnectionCheck $check): RedirectResponse
    {
        abort_unless(in_array(Setup::step(), ['microsoft', 'admin'], true) && SignIn::enabled('microsoft'), 403);

        $data = $request->validate(Settings::microsoftRules());
        if ($problem = $check->microsoft($data['tenant_id'], $data['client_id'], $data['client_secret'], MicrosoftSignIn::callbackUrl())) {
            return back()->withErrors(['microsoft_keys' => $problem])->withInput($request->only('tenant_id', 'client_id'));
        }
        Settings::saveMicrosoft($data);

        return redirect()->route('setup');
    }

    /**
     * The admin account, with a website password (when that's a chosen sign-in method).
     */
    public function storeAdmin(Request $request): RedirectResponse
    {
        abort_unless(Setup::step() === 'admin' && SignIn::enabled('password'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8), 'not_in:'.SignIn::DEFAULT_PASSWORD],
        ]);

        $user = new User;
        $user->forceFill(['name' => $data['name'], 'email' => strtolower($data['email']), 'password' => Hash::make($data['password']), 'is_admin' => true, 'email_verified_at' => now()])->save();
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('setup');
    }

    public function storeGoogle(Request $request, ConnectionCheck $check): RedirectResponse
    {
        abort_unless(in_array(Setup::step(), ['google', 'admin'], true), 403);

        $data = $request->validate(Settings::googleRules());
        if ($problem = $check->google($data['client_id'], $data['client_secret'], GoogleSignIn::callbackUrl())) {
            return back()->withErrors(['google_keys' => $problem])->withInput($request->only('domain'));
        }
        Settings::saveGoogle($data);

        return redirect()->route('setup');
    }

    public function storeHarness(Request $request): RedirectResponse
    {
        abort_unless($request->user()->is_admin && in_array(Setup::step(), ['harness', 'claude'], true), 403);

        return redirect()->route('setup')->with('harness_token', Settings::newHarnessToken());
    }

    public function storeClaude(Request $request, ConnectionCheck $check): RedirectResponse
    {
        abort_unless($request->user()->is_admin && Setup::step() === 'claude' && ! Setup::complete(), 403);

        $data = $request->validate(Settings::claudeRules());
        if ($problem = $check->claude($data['type'], trim($data['credential']))) {
            return back()->withErrors(['credential' => $problem])->withInput($request->only('type'));
        }
        Settings::saveClaude($data);
        Setting::put('setup.completed_at', now()->toIso8601String());

        return redirect()->route('dashboard');
    }
}
