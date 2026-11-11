<?php

namespace App\Http\Middleware;

use App\Support\Setup;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSetupComplete
{
    /**
     * Until setup is finished, every page leads to the setup page.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Setup::complete() && ! $request->routeIs('setup', 'setup.*', 'login', 'logout')) {
            return redirect()->route('setup');
        }

        return $next($request);
    }
}
