<?php

namespace App\Http\Controllers;

use App\Support\Setup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

class DnsSetupController extends Controller
{
    /**
     * The DNS and HTTPS guide. Public during first-time setup; afterwards it lives in Docs.
     */
    public function __invoke(): View|RedirectResponse
    {
        if (Setup::complete() && Route::has('docs.show')) {
            return redirect()->route('docs.show', 'dns-setup');
        }

        return view('setup.dns');
    }
}
