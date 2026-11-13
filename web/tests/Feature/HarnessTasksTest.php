<?php

namespace Tests\Feature;

use App\Harness\HarnessTask;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HarnessTasksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::put('harness.token_hash', hash('sha256', 'harness-token'));
    }

    private function harness(string $path, array $data = [])
    {
        return $this->withToken('harness-token')->postJson('/api/harness'.$path, $data);
    }

    public function test_the_harness_takes_tasks_in_order_and_reports_back(): void
    {
        $first = HarnessTask::queue('chat', 'Make a notes tool', reference: '7', mode: 'plan', systemPrompt: 'Follow the style guide.');
        HarnessTask::queue('chat', 'Second');

        $this->harness('/tasks/next')->assertJsonPath('task.id', $first->id)->assertJsonPath('task.permission_mode', 'plan')->assertJsonPath('task.prompt', 'Make a notes tool');
        $this->assertSame('running', $first->fresh()->status);

        $this->harness("/tasks/{$first->id}/events", ['lines' => [
            json_encode(['type' => 'system', 'subtype' => 'init', 'session_id' => 'abc-123']),
            json_encode(['type' => 'assistant', 'message' => ['content' => [['type' => 'text', 'text' => 'Here is a plan']]]]),
            'not json',
        ]])->assertJson(['stop' => false]);

        $this->harness("/tasks/{$first->id}/finish", ['exit' => 0]);

        $first->refresh();
        $this->assertSame(['done', 'abc-123', 2], [$first->status, $first->result_session_id, $first->events()->count()]);
        $this->harness('/tasks/next')->assertJsonPath('task.prompt', 'Second');
    }

    public function test_a_task_can_be_stopped(): void
    {
        $task = HarnessTask::queue('chat', 'Long job');
        $this->harness('/tasks/next');
        $task->update(['stop' => true]);

        $this->harness("/tasks/{$task->id}/events", ['lines' => ['{"type":"assistant"}']])->assertJson(['stop' => true]);
        $this->harness("/tasks/{$task->id}/finish", ['exit' => 143]);
        $this->assertSame('stopped', $task->fresh()->status);
    }

    public function test_a_task_left_running_fails_when_the_harness_asks_again(): void
    {
        $task = HarnessTask::queue('chat', 'Cut off');
        $this->harness('/tasks/next');
        $this->harness('/tasks/next')->assertJson(['task' => null]);

        $this->assertSame('failed', $task->fresh()->status);
    }

    public function test_only_the_harness_can_take_tasks(): void
    {
        HarnessTask::queue('chat', 'Secret');
        $this->withToken('wrong')->postJson('/api/harness/tasks/next')->assertUnauthorized();
        $this->postJson('/api/harness/tasks/next')->assertUnauthorized();
    }
}
