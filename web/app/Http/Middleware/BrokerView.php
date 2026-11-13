<?php

namespace App\Http\Middleware;

use App\Harness\ClaudeUser;
use App\Harness\HarnessView;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Requests from the harness (Claude) get broker view: signed in as the Claude user, and every
 * answer passed through the broker's filter. Anything that can't be filtered is refused.
 */
class BrokerView
{
    /** Content types the filter can read. */
    private const TEXT = ['text/', 'application/json', 'application/xml', 'application/javascript', 'application/x-ndjson'];

    public function __construct(private HarnessView $view) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! HarnessView::requested($request)) {
            return $next($request);
        }

        // The harness's own API and the MCP servers authenticate with the harness token.
        if ($request->is('api/harness/*') || $request->is('mcp/*')) {
            return $next($request);
        }

        if (! $this->view->ready()) {
            return $this->refuse(__('Broker view isn\'t available: the broker module isn\'t installed or set up, so pages can\'t be shown without real names.'), 503);
        }

        Auth::guard('web')->setUser(ClaudeUser::get());

        $response = $next($request);

        if ($response instanceof BinaryFileResponse || $response instanceof StreamedResponse) {
            return $this->refuse(__('Downloads aren\'t available in broker view.'));
        }

        $type = strtolower((string) $response->headers->get('Content-Type', 'text/html'));
        if (! collect(self::TEXT)->contains(fn ($text) => str_starts_with($type, $text))) {
            return $this->refuse(__('Images and files aren\'t shown in broker view.'));
        }

        $response->setContent($this->view->apply((string) $response->getContent()));
        if ($response->headers->has('Location')) {
            $response->headers->set('Location', $this->view->apply((string) $response->headers->get('Location')));
        }

        return $response;
    }

    private function refuse(string $message, int $status = 403): Response
    {
        return response($message, $status)->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
