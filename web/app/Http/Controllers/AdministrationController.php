<?php

namespace App\Http\Controllers;

use App\Administration\Administration;
use App\Models\Setting;
use App\Support\ConnectionCheck;
use App\Support\GoogleSignIn;
use App\Support\MicrosoftSignIn;
use App\Support\Settings;
use App\Support\Setup;
use App\Support\SignIn;
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
            'methods' => SignIn::methods(),
            'tenant' => MicrosoftSignIn::tenant(),
            'microsoftConfigured' => MicrosoftSignIn::configured(),
            'microsoftCallbackUrl' => MicrosoftSignIn::callbackUrl(),
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
        return redirect()->away($address.'/settings')->with('status', 'address');
    }

    public function signin(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'methods' => ['required', 'array', 'min:1'],
            'methods.*' => ['in:'.implode(',', array_keys(SignIn::METHODS))],
        ], ['methods.required' => __('Choose at least one.')]);

        // Don't lock yourself out.
        $user = $request->user();
        $still = ($user->google_id && in_array('google', $data['methods'], true))
            || ($user->microsoft_id && in_array('microsoft', $data['methods'], true))
            || ($user->password !== null && in_array('password', $data['methods'], true));
        if (! $still) {
            return back()->withErrors(['methods' => __('Keep on the way you sign in yourself.')]);
        }

        SignIn::save($data['methods']);

        return redirect()->route('administration')->with('status', 'signin');
    }

    public function microsoft(Request $request, ConnectionCheck $check): RedirectResponse
    {
        $data = $request->validate(Settings::microsoftRules());

        // Keep the working registration until the new one passes.
        if ($problem = $check->microsoft($data['tenant_id'], $data['client_id'], $data['client_secret'], MicrosoftSignIn::callbackUrl())) {
            return back()->withErrors(['microsoft_keys' => $problem])->withInput($request->only('tenant_id'));
        }

        Settings::saveMicrosoft($data);

        return redirect()->route('administration')->with('status', 'microsoft');
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
