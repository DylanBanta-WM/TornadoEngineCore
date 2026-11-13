<?php

namespace App\Http\Controllers;

use App\Updates\CoreRelease;
use App\Updates\UpdateJob;
use Illuminate\Http\RedirectResponse;

class UpdatesController extends Controller
{
    public function core(): RedirectResponse
    {
        $version = CoreRelease::available();

        if (! $version) {
            return back()->withErrors(['updates' => __('Tornado Engine Core is up to date.')]);
        }
        if (UpdateJob::busy()) {
            return back()->withErrors(['updates' => __('Another install or update is still running. Wait for it to finish.')]);
        }

        UpdateJob::queue('core', $version);

        return back()->with('updates_status', __('Updating to :version. This page shows how it goes.', ['version' => $version]));
    }

    public function check(): RedirectResponse
    {
        $latest = CoreRelease::latest(fresh: true);

        return back()->with('updates_status', $latest === null
            ? __('Couldn\'t reach GitHub to check. Try again later.')
            : __('Checked. The newest official version is :version.', ['version' => $latest]));
    }
}
