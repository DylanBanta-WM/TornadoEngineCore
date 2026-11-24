<?php

namespace App\Http\Controllers;

use App\Updates\CoreRelease;
use App\Updates\DistrictBranch;
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

    /** Installs a version of Core (or of a customized module) even though the district's changes clash: those are set aside. */
    public function anyway(Request $request): RedirectResponse
    {
        $data = $request->validate(['version' => ['required', 'string', 'regex:/^\d+\.\d+\.\d+$/'], 'package' => ['nullable', 'string']]);
        $package = $data['package'] ?? null;
        abort_if($package !== null && ! isset(DistrictBranch::copies()[$package]), 404);
        if (UpdateJob::busy()) {
            return back()->withErrors(['updates' => __('An install or update is already running.')]);
        }
        $job = $package ? UpdateJob::queue('module', $data['version'], $package) : UpdateJob::queue('core', $data['version']);
        $job->update(['anyway' => true]);

        return back()->with('district_status', __('Installing :name :version. Changes that clash will be set aside.', ['name' => $package ?? __('Core'), 'version' => $data['version']]));
    }

    public function dismissSetAside(Request $request): RedirectResponse
    {
        DistrictBranch::saveSetAside((string) $request->input('target', 'core'), null);

        return back();
    }

    /** Stops customizing a module: back to its official release (the customized copy is kept). */
    public function official(Request $request): RedirectResponse
    {
        $package = (string) $request->validate(['package' => ['required', 'string']])['package'];
        abort_unless(isset(DistrictBranch::copies()[$package]), 404);
        if (UpdateJob::busy()) {
            return back()->withErrors(['updates' => __('An install or update is already running.')]);
        }
        UpdateJob::queue('official', '', $package);

        return back()->with('district_status', __('Putting :package back to its official version.', ['package' => $package]));
    }
}
