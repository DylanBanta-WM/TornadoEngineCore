<?php

namespace App\Http\Controllers;

use App\Patches\Patches;
use App\Updates\CoreRelease;
use App\Updates\Rebuild;
use App\Updates\UpdateJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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

    public function rebuild(): RedirectResponse
    {
        if (! Rebuild::helper()) {
            return back()->withErrors(['rebuild' => __('The rebuild helper isn\'t installed on the server.')]);
        }
        if (UpdateJob::busy() || Rebuild::running()) {
            return back()->withErrors(['rebuild' => __('Wait for the install, update or rebuild that\'s running.')]);
        }

        Rebuild::request();

        return back();
    }

    /** For the page to follow a rebuild (the website restarts part-way through). */
    public function rebuildStatus(): JsonResponse
    {
        return response()->json(['running' => Rebuild::running(), 'status' => Rebuild::status()]);
    }

    public function check(): RedirectResponse
    {
        $latest = CoreRelease::latest(fresh: true);

        return back()->with('updates_status', $latest === null
            ? __('Couldn\'t reach GitHub to check. Try again later.')
            : __('Checked. The newest official version is :version.', ['version' => $latest]));
    }

    /** Takes a patch off (undoing its changes) and deletes it. */
    public function removePatch(Request $request, Patches $patches): RedirectResponse
    {
        $target = (string) $request->validate(['target' => ['required', 'string', 'max:200']])['target'];
        abort_unless(array_key_exists($target, $patches->all()), 404);
        $patches->remove($target);

        return back()->with('patches_status', __('Removed the patch for :target.', ['target' => $target === 'core' ? __('Core') : $target]));
    }
}
