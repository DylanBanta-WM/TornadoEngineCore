<?php

namespace App\Harness;

use Closure;
use Illuminate\Http\Request;

/**
 * Broker view: how the website answers the harness (Claude). Requests that come through
 * nginx-harness are marked with TORNADO_VIEW=broker, and every response to them goes through
 * the filter a module registers, which puts pseudonyms in place of real people:
 *
 *     app(HarnessView::class)->filter(fn (string $text) => $broker->filter($text), ready: fn () => $broker->ready());
 *
 * With no filter, or one that isn't ready, broker view refuses to answer.
 */
class HarnessView
{
    private ?Closure $filter = null;

    private ?Closure $ready = null;

    public static function requested(Request $request): bool
    {
        return $request->server('TORNADO_VIEW') === 'broker';
    }

    /**
     * @param  Closure(string): string  $filter
     * @param  Closure(): bool  $ready
     */
    public function filter(Closure $filter, Closure $ready): static
    {
        $this->filter = $filter;
        $this->ready = $ready;

        return $this;
    }

    public function ready(): bool
    {
        return $this->filter !== null && $this->ready !== null && ($this->ready)();
    }

    public function apply(string $text): string
    {
        return ($this->filter)($text);
    }
}
