<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\User;
use App\Support\ConnectionCheck;
use App\Support\GoogleSignIn;
use App\Support\Settings;
use App\Support\Setup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SetupController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        if (Setup::complete()) {
            return redirect()->route('dashboard');
        }

        $current = Setup::step();

        if (! in_array($current, ['address', 'google', 'admin'], true) && ! $request->user()?->is_admin) {
            return redirect()->route('login');
        }

        // ?step= looks back at a step that's already done.
        $steps = Setup::STEPS;
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
        ]);
    }

    public function storeAddress(Request $request): RedirectResponse
    {
        abort_if(User::where('is_admin', true)->exists(), 403);

        $address = Settings::saveAddress($request->validate(Settings::addressRules())['address']);

        // Go straight on at the new address.
        return redirect()->away($address.'/setup');
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
