<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateHarness
{
    /**
     * The harness sends the token from `bin/harness connect` as a bearer token.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expected = Setting::get('harness.token_hash');
        $token = $request->bearerToken();

        if ($expected === null || $token === null || ! hash_equals($expected, hash('sha256', $token))) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
