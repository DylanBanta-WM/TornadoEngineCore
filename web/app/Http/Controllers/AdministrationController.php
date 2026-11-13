<?php

namespace App\Http\Controllers;

use App\Models\Setting;
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
    public function index(): View
    {
        return view('administration.index', [
            'address' => Setup::address(),
            'domain' => GoogleSignIn::domain(),
            'clientId' => Setting::get('google.client_id'),
            'callbackUrl' => GoogleSignIn::callbackUrl(),
            'harnessSeen' => Setting::get('harness.last_seen_at'),
            'harnessToken' => session('harness_token'),
            'claudeType' => Setting::get('harness.claude_credential_type'),
        ]);
    }

    public function address(Request $request): RedirectResponse
    {
        $address = Settings::saveAddress($request->validate(Settings::addressRules())['address']);

        // Carry on at the new address.
        return redirect()->away($address.'/administration')->with('status', 'address');
    }

    public function google(Request $request): RedirectResponse
    {
        Settings::saveGoogle($request->validate(Settings::googleRules()));

        return redirect()->route('administration')->with('status', 'google');
    }

    public function harness(): RedirectResponse
    {
        return redirect()->route('administration')->with('harness_token', Settings::newHarnessToken());
    }

    public function claude(Request $request): RedirectResponse
    {
        Settings::saveClaude($request->validate(Settings::claudeRules()));

        return redirect()->route('administration')->with('status', 'claude');
    }
}
