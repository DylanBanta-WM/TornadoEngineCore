<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\SignIn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Changing a website password: when it's required at sign-in, or any time from the user menu.
 */
class PasswordController extends Controller
{
    public function edit(Request $request): View
    {
        abort_unless($request->user()->password !== null, 404);

        return view('auth.change-password', ['required' => $request->user()->must_change_password]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->password !== null, 404);

        $data = $request->validate([
            'current_password' => [$user->must_change_password ? 'nullable' : 'required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8), 'not_in:'.SignIn::DEFAULT_PASSWORD],
        ], ['password.not_in' => __('Choose a different password.')]);

        $user->forceFill(['password' => Hash::make($data['password']), 'must_change_password' => false])->save();

        return redirect()->intended(route('dashboard'))->with('status', __('Your password is changed.'));
    }
}
