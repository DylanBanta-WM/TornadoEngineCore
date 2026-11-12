<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Support\GoogleSignIn;
use App\Support\Setup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SetupController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        if (Setup::complete()) {
            return redirect()->route('dashboard');
        }

        $step = Setup::step();

        if (! in_array($step, ['google', 'admin'], true) && ! $request->user()?->is_admin) {
            return redirect()->route('login');
        }

        if ($step === 'admin' && $request->query('change') === 'google') {
            $step = 'google';
        }

        return view('setup.index', [
            'step' => $step,
            'harnessToken' => session('harness_token'),
            'domain' => GoogleSignIn::domain(),
            'callbackUrl' => GoogleSignIn::callbackUrl(),
        ]);
    }

    public function storeGoogle(Request $request): RedirectResponse
    {
        abort_unless(in_array(Setup::step(), ['google', 'admin'], true), 403);

        $data = $request->validate([
            'domain' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9-]+(\.[a-z0-9-]+)+$/i'],
            'client_id' => ['required', 'string', 'max:255'],
            'client_secret' => ['required', 'string', 'max:255'],
        ]);

        Setting::put('google.domain', strtolower(trim($data['domain'])));
        Setting::put('google.client_id', trim($data['client_id']));
        Setting::putSecret('google.client_secret', trim($data['client_secret']));

        return redirect()->route('setup');
    }

    public function storeHarness(Request $request): RedirectResponse
    {
        abort_unless($request->user()->is_admin && in_array(Setup::step(), ['harness', 'claude'], true), 403);

        $token = Str::random(48);
        Setting::put('harness.token_hash', hash('sha256', $token));
        Setting::put('harness.last_seen_at', null);

        return redirect()->route('setup')->with('harness_token', $token);
    }

    public function storeClaude(Request $request): RedirectResponse
    {
        abort_unless($request->user()->is_admin && Setup::step() === 'claude' && ! Setup::complete(), 403);

        $data = $request->validate([
            'type' => ['required', Rule::in(['subscription', 'api_key'])],
            'credential' => ['required', 'string', 'max:500'],
        ]);

        Setting::put('harness.claude_credential_type', $data['type']);
        Setting::putSecret('harness.claude_credential', trim($data['credential']));
        Setting::put('setup.completed_at', now()->toIso8601String());

        return redirect()->route('dashboard');
    }
}
