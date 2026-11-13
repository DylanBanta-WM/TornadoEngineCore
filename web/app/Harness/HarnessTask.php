<?php

namespace App\Harness;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A prompt for Claude Code in the harness. Modules queue them; the harness runs them one at a
 * time and sends back what Claude Code prints (stream-json events).
 *
 *     HarnessTask::queue('chat', $prompt, reference: $conversation->id, session: $sessionId, mode: 'plan');
 *
 * Whatever goes in the prompt reaches Claude: never put student or staff information in it
 * (filter it through the broker first).
 */
class HarnessTask extends Model
{
    /** Claude Code's permission modes. */
    public const MODES = ['bypassPermissions', 'auto', 'acceptEdits', 'plan', 'manual'];

    protected $fillable = ['source', 'reference', 'prompt', 'session_id', 'permission_mode', 'permission_prompt_tool', 'system_prompt', 'cwd',
        'status', 'stop', 'result_session_id', 'error', 'started_at', 'finished_at'];

    public static function queue(string $source, string $prompt, ?string $reference = null, ?string $session = null, string $mode = 'bypassPermissions',
        ?string $systemPrompt = null, ?string $permissionPromptTool = null, string $cwd = 'web'): self
    {
        return self::create([
            'source' => $source,
            'reference' => $reference,
            'prompt' => $prompt,
            'session_id' => $session,
            'permission_mode' => in_array($mode, self::MODES, true) ? $mode : 'manual',
            'permission_prompt_tool' => $permissionPromptTool,
            'system_prompt' => $systemPrompt,
            'cwd' => $cwd,
        ]);
    }

    public function events(): HasMany
    {
        return $this->hasMany(HarnessTaskEvent::class);
    }

    public function finished(): bool
    {
        return in_array($this->status, ['done', 'failed', 'stopped'], true);
    }

    protected function casts(): array
    {
        return ['stop' => 'boolean', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }
}
