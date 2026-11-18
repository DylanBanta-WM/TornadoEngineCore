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
use App\Support\Tour;
use App\Updates\UpdateJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use TornadoEngine\ModuleFinder\Finder;

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

        // The harness step shows its connect command straight away (the same one until it connects).
        if ($step === 'harness' && $step === $current && ! $request->session()->has('harness_token')) {
            $request->session()->put('harness_token', Settings::newHarnessToken());
        }

        $listings = $step === 'modules' && class_exists(Finder::class)
            ? collect(app(Finder::class)->listings()) : collect();

        return view('setup.index', [
            // Official modules that ask to be offered here ("suggest" in extra.tornado), and aren't installed.
            'modules' => $listings->filter(fn ($listing) => $listing->official && ($listing->suggested ?? false) && $listing->url && $listing->latest && $listing->installed === null),
            'allModules' => $listings->all(),
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
        abort_unless($this->mayChange($request, 'address'), 403);

        $address = Settings::saveAddress($request->validate(Settings::addressRules())['address']);

        // Go straight on at the new address.
        return redirect()->away($address.'/setup');
    }

    public function storeSignin(Request $request): RedirectResponse
    {
        abort_unless($this->mayChange($request, 'signin'), 403);

        $data = $request->validate([
            'methods' => ['required', 'array', 'min:1'],
            'methods.*' => ['in:'.implode(',', array_keys(SignIn::METHODS))],
        ], ['methods.required' => __('Choose at least one.')]);

        // Once the admin account exists, keep a way for it to sign in.
        $admin = User::where('is_admin', true)->first();
        if ($admin) {
            $uses = array_keys(array_filter(['google' => $admin->google_id !== null, 'microsoft' => ($admin->microsoft_id ?? null) !== null, 'password' => $admin->password !== null]));
            if ($uses && ! array_intersect($uses, $data['methods'])) {
                return back()->withErrors(['methods' => __('Keep :method: the admin account signs in with it.', ['method' => SignIn::METHODS[$uses[0]] ?? $uses[0]])])->withInput();
            }
        }
        SignIn::save($data['methods']);

        return redirect()->route('setup');
    }

    public function storeMicrosoft(Request $request, ConnectionCheck $check): RedirectResponse
    {
        abort_unless($this->mayChange($request, 'microsoft'), 403);

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
        abort_unless($this->mayChange($request, 'google'), 403);

        $data = $request->validate(Settings::googleRules());
        if ($problem = $check->google($data['client_id'], $data['client_secret'], GoogleSignIn::callbackUrl())) {
            return back()->withErrors(['google_keys' => $problem])->withInput($request->only('domain'));
        }
        Settings::saveGoogle($data);

        return redirect()->route('setup');
    }

    public function storeHarness(Request $request): RedirectResponse
    {
        abort_unless($this->mayChange($request, 'harness'), 403);

        return redirect()->route('setup')->with('harness_token', Settings::newHarnessToken());
    }

    public function storeClaude(Request $request, ConnectionCheck $check): RedirectResponse
    {
        abort_unless($this->mayChange($request, 'claude'), 403);

        $data = $request->validate(Settings::claudeRules());
        if ($problem = $check->claude($data['type'], trim($data['credential']))) {
            return back()->withErrors(['credential' => $problem])->withInput($request->only('type'));
        }
        Settings::saveClaude($data);

        return redirect()->route('setup');
    }

    /**
     * The last step: the modules to install (Module Finder's list). They're queued for the updater.
     */
    public function storeModules(Request $request): RedirectResponse
    {
        abort_unless($request->user()->is_admin && Setup::step() === 'modules' && ! Setup::complete(), 403);

        $chosen = (array) $request->input('packages', []);
        $listings = class_exists(Finder::class) ? app(Finder::class)->listings() : [];
        $queued = 0;
        foreach ($chosen as $package) {
            $listing = $listings[$package] ?? null;
            if ($listing && $listing->official && ($listing->suggested ?? false) && $listing->url && $listing->latest && $listing->installed === null) {
                UpdateJob::queue('module', $listing->latest, $listing->package, $listing->onPackagist ? null : $listing->url, install: true);
                $queued++;
            }
        }

        Setting::put('setup.completed_at', now()->toIso8601String());
        Tour::start($request->user());

        // The tour starts on the dashboard; modules install meanwhile.
        return redirect()->route('dashboard');
    }

    /**
     * A step can be saved while it's the current one, or again from going back to it (until setup is done).
     * Once the admin account exists, only the admin can.
     */
    private function mayChange(Request $request, string $step): bool
    {
        if (Setup::complete()) {
            return false;
        }
        $steps = Setup::steps();
        $index = array_search($step, $steps, true);
        if ($index === false || $index > array_search(Setup::step(), $steps, true)) {
            return false;
        }

        return ! User::where('is_admin', true)->exists() || (bool) $request->user()?->is_admin;
    }
}
