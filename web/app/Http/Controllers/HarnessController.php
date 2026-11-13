<?php

namespace App\Http\Controllers;

use App\Harness\HarnessMcp;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;

class HarnessController extends Controller
{
    /**
     * The harness checks in when it starts and every minute after.
     */
    public function hello(): JsonResponse
    {
        Setting::put('harness.last_seen_at', now()->toIso8601String());

        return response()->json(['ok' => true]);
    }

    /**
     * The MCP servers modules give Claude. The harness adds its address and token.
     */
    public function mcp(HarnessMcp $mcp): JsonResponse
    {
        return response()->json(['servers' => $mcp->servers()]);
    }

    /**
     * The Claude Code login saved on the setup page.
     */
    public function claude(): JsonResponse
    {
        $credential = Setting::getSecret('harness.claude_credential');

        if ($credential === null) {
            return response()->json(['message' => 'No Claude Code login saved yet'], 404);
        }

        return response()->json([
            'type' => Setting::get('harness.claude_credential_type'),
            'credential' => $credential,
        ]);
    }
}
