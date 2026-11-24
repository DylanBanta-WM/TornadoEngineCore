<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Which Claude model runs the task (an alias like opus, or a model id); the harness's default when empty.
        Schema::table('harness_tasks', function (Blueprint $table) {
            $table->string('model', 60)->nullable()->after('cwd');
        });
    }

    public function down(): void
    {
        Schema::table('harness_tasks', function (Blueprint $table) {
            $table->dropColumn('model');
        });
    }
};
