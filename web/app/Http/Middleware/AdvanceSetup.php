<?php

namespace App\Http\Middleware;

use App\Layout\ModuleSettings;
use App\Modules\Setup;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * After a module's settings are saved from its settings page: remembers it, and while setting up a module
 * and what it needs (App\Modules\Setup), opens the next settings page, then the dashboard.
 */
class AdvanceSetup
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->isMethod('POST') || ! $request->user() || ! $response instanceof RedirectResponse || $request->session()->has('errors')) {
            return $response;
        }
        $from = parse_url((string) $request->headers->get('referer'), PHP_URL_PATH);
        $module = is_string($from) ? app(ModuleSettings::class)->moduleAt($from) : null;
        if ($module === null || ($next = app(Setup::class)->saved($module)) === null) {
            return $response;
        }

        return redirect()->to($next)->with('setup_status', __(':name is set up.', ['name' => $module->name]));
    }
}
