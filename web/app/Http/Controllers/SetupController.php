<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\User;
use App\Support\Setup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class SetupController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        if (Setup::complete()) {
            return redirect()->route('dashboard');
        }

        $step = Setup::step();

        if ($step !== 'admin' && ! $request->user()?->is_admin) {
            return redirect()->route('login');
        }

        return view('setup.index', [
            'step' => $step,
            'harnessToken' => session('harness_token'),
        ]);
    }

    public function storeAdmin(Request $request): RedirectResponse
    {
        abort_if(Setup::step() !== 'admin', 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = new User($data);
        $user->is_admin = true;
        $user->email_verified_at = now();
        $user->save();

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('setup');
    }

    public function storeHarness(Request $request): RedirectResponse
    {
        abort_unless($request->user()->is_admin && Setup::step() !== 'admin', 403);

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
