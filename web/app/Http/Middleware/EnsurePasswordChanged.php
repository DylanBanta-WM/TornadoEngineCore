<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Someone whose password was set for them (for example ChangeMe!) changes it before anything else.
 */
class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->must_change_password && ! $request->routeIs('password.edit', 'password.update', 'logout')) {
            return redirect()->guest(route('password.edit'));
        }

        return $next($request);
    }
}
