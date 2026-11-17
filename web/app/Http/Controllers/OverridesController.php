<?php

namespace App\Http\Controllers;

use App\Overrides\Overrides;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class OverridesController extends Controller
{
    public function create(Request $request, Overrides $overrides): RedirectResponse
    {
        return $this->attempt(fn () => $overrides->create((string) $request->input('view')), __('Overridden. Change it below.'));
    }

    public function save(Request $request, Overrides $overrides): RedirectResponse
    {
        $name = (string) $request->input('view');

        return match ($request->input('action')) {
            'review' => $this->attempt(fn () => $overrides->review($name), __('Marked as reviewed.')),
            'remove' => $this->attempt(fn () => $overrides->remove($name), __('Removed. The module\'s own view is used again.')),
            default => $this->attempt(fn () => $overrides->save($name, (string) $request->input('contents')), __('Saved.')),
        };
    }

    public function css(Request $request, Overrides $overrides): RedirectResponse
    {
        $request->validate(['css' => ['nullable', 'string', 'max:100000']]);

        return $this->attempt(fn () => $overrides->saveCss((string) $request->input('css')), __('Saved. The styles are being rebuilt; reload in a minute.'));
    }

    private function attempt(callable $action, string $done): RedirectResponse
    {
        try {
            $action();
        } catch (Throwable $e) {
            return back()->withErrors(['overrides' => $e->getMessage()])->withInput();
        }

        return back()->with('overrides_status', $done);
    }
}
