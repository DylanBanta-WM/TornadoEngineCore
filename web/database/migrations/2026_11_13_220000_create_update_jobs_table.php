<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Installs and updates admins ask for; the updater service works through them in order.
        Schema::create('update_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 10); // "core" or "module"
            $table->string('package')->nullable();
            $table->string('version');
            $table->string('repository')->nullable(); // GitHub URL, for modules not on Packagist
            $table->string('status', 10)->default('queued'); // queued, running, done, failed
            $table->longText('log')->nullable();
            $table->json('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('update_jobs');
    }
};
