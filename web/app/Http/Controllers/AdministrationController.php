<?php

namespace App\Http\Controllers;

use App\Administration\Administration;
use App\Models\Setting;
use App\Support\ConnectionCheck;
use App\Support\GoogleSignIn;
use App\Support\Settings;
use App\Support\Setup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Change the settings from first-time setup.
 */
class AdministrationController extends Controller
{
    public function index(Administration $administration): View
    {
        return view('administration.index', [
            'cards' => $administration->cards(),
            'address' => Setup::address(),
            'domain' => GoogleSignIn::domain(),
            'clientId' => Setting::get('google.client_id'),
            'callbackUrl' => GoogleSignIn::callbackUrl(),
            'harnessSeen' => Setting::get('harness.last_seen_at'),
            'harnessToken' => session('harness_token'),
            'claudeType' => Setting::get('harness.claude_credential_type'),
        ]);
    }

    public function tab(Administration $administration, string $tab): View
    {
        abort_unless($administration->has($tab) && $tab !== Administration::SETTINGS, 404);

        return view('administration.tab', ['tab' => $tab, 'cards' => $administration->cards($tab)]);
    }

    public function address(Request $request): RedirectResponse
    {
        $address = Settings::saveAddress($request->validate(Settings::addressRules())['address']);

        // Carry on at the new address.
        return redirect()->away($address.'/administration')->with('status', 'address');
    }

    public function google(Request $request, ConnectionCheck $check): RedirectResponse
    {
        $data = $request->validate(Settings::googleRules());

        // Keep the working client until the new one passes.
        if ($problem = $check->google($data['client_id'], $data['client_secret'], GoogleSignIn::callbackUrl())) {
            return back()->withErrors(['google_keys' => $problem])->withInput($request->only('domain'));
        }

        Settings::saveGoogle($data);

        return redirect()->route('administration')->with('status', 'google');
    }

    public function harness(): RedirectResponse
    {
        return redirect()->route('administration')->with('harness_token', Settings::newHarnessToken());
    }

    public function claude(Request $request, ConnectionCheck $check): RedirectResponse
    {
        $data = $request->validate(Settings::claudeRules());

        // Keep the working login until the new one passes.
        if ($problem = $check->claude($data['type'], trim($data['credential']))) {
            return back()->withErrors(['credential' => $problem])->withInput($request->only('type'));
        }

        Settings::saveClaude($data);

        return redirect()->route('administration')->with('status', 'claude');
    }
}
