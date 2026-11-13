<?php

namespace App\Harness;

/**
 * MCP servers the harness gives Claude. Modules add theirs:
 *
 *     app(HarnessMcp::class)->server('tornado-broker', '/mcp/broker');
 *
 * The harness reaches them through nginx-harness with its token, so the routes need the
 * App\Http\Middleware\AuthenticateHarness middleware.
 */
class HarnessMcp
{
    /** @var array<string, string> name => path */
    private array $servers = [];

    public function server(string $name, string $path): static
    {
        $this->servers[$name] = '/'.ltrim($path, '/');

        return $this;
    }

    /** @return array<string, string> */
    public function servers(): array
    {
        return $this->servers;
    }
}
