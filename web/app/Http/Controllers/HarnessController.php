<?php

namespace App\Http\Controllers;

use App\Harness\HarnessMcp;
use App\Harness\HarnessTask;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
     * The next prompt for Claude Code, if any. Taking it marks it as running.
     */
    public function nextTask(): JsonResponse
    {
        // A task left running means the harness stopped part-way (for example, a restart).
        HarnessTask::where('status', 'running')->update(['status' => 'failed', 'error' => 'The harness stopped while this was running.', 'finished_at' => now()]);

        $task = HarnessTask::where('status', 'queued')->orderBy('id')->first();
        if (! $task) {
            return response()->json(['task' => null]);
        }

        $task->update(['status' => 'running', 'started_at' => now()]);

        return response()->json(['task' => $task->only(['id', 'prompt', 'session_id', 'permission_mode', 'permission_prompt_tool', 'system_prompt', 'cwd'])]);
    }

    /**
     * Lines Claude Code printed. The answer says whether to stop it.
     */
    public function taskEvents(Request $request, string $task): JsonResponse
    {
        $task = HarnessTask::findOrFail($task);
        foreach ((array) $request->input('lines', []) as $line) {
            $event = is_string($line) ? json_decode($line, true) : null;
            if (is_array($event)) {
                $task->events()->create(['event' => $event]);
                if (($event['type'] ?? null) === 'system' && isset($event['session_id'])) {
                    $task->result_session_id ??= $event['session_id'];
                }
            }
        }
        $task->save();

        return response()->json(['stop' => $task->fresh()->stop]);
    }

    public function finishTask(Request $request, string $task): JsonResponse
    {
        $task = HarnessTask::findOrFail($task);
        $task->update([
            'status' => $task->stop ? 'stopped' : ((int) $request->input('exit', 1) === 0 ? 'done' : 'failed'),
            'error' => $request->input('error') ? mb_substr((string) $request->input('error'), -2000) : null,
            'finished_at' => now(),
        ]);

        return response()->json(['ok' => true]);
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
