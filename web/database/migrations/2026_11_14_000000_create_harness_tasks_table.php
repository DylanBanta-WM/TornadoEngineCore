<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Prompts modules give Claude Code in the harness. The harness picks them up, one at a time.
        Schema::create('harness_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('source'); // the module that asked, e.g. "chat"
            $table->string('reference')->nullable(); // the module's own id, e.g. a conversation
            $table->longText('prompt');
            $table->string('session_id')->nullable(); // Claude Code session to continue
            $table->string('permission_mode')->default('bypassPermissions');
            $table->string('permission_prompt_tool')->nullable();
            $table->text('system_prompt')->nullable();
            $table->string('cwd')->default('web'); // inside the Tornado Engine folder
            $table->string('status', 10)->default('queued'); // queued, running, done, failed, stopped
            $table->boolean('stop')->default(false);
            $table->string('result_session_id')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        // What Claude Code printed (stream-json), line by line.
        Schema::create('harness_task_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('harness_task_id')->constrained()->cascadeOnDelete();
            $table->json('event');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('harness_task_events');
        Schema::dropIfExists('harness_tasks');
    }
};
