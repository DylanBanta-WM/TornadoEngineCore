<?php

namespace App\Http\Controllers;

use App\Support\Tour;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TourController extends Controller
{
    /** Back to the page the current step is on. */
    public function resume(Request $request): RedirectResponse
    {
        $current = Tour::current($request->user());

        return redirect()->route($current ? $current['step']['route'] : 'dashboard');
    }

    public function next(Request $request): RedirectResponse
    {
        Tour::move($request->user(), 1);

        return $this->resume($request);
    }

    public function back(Request $request): RedirectResponse
    {
        Tour::move($request->user(), -1);

        return $this->resume($request);
    }

    public function skip(Request $request): RedirectResponse
    {
        Tour::end($request->user());

        return back();
    }
}
