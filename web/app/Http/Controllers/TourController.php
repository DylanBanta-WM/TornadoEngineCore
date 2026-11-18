<?php

namespace App\Http\Controllers;

use App\Support\Tour;
use Illuminate\Http\JsonResponse;
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

    public function next(Request $request): RedirectResponse|JsonResponse
    {
        Tour::move($request->user(), 1);

        return $this->answer($request);
    }

    public function back(Request $request): RedirectResponse|JsonResponse
    {
        Tour::move($request->user(), -1);

        return $this->answer($request);
    }

    /** The tour card asks for JSON (with the page it's on) to change steps without leaving the page. */
    private function answer(Request $request): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['tour' => Tour::state($request->user(), (string) $request->input('from', '/'))]);
        }

        return $this->resume($request);
    }

    public function skip(Request $request): RedirectResponse
    {
        Tour::end($request->user());

        return back();
    }
}
