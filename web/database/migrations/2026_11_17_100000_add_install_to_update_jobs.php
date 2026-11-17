<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // A fresh install of a module that needs setting up opens its settings page once, when it's done.
    public function up(): void
    {
        Schema::table('update_jobs', function (Blueprint $table) {
            $table->boolean('install')->default(false)->after('repository');
            $table->timestamp('opened_at')->nullable()->after('finished_at');
        });
    }

    public function down(): void
    {
        Schema::table('update_jobs', function (Blueprint $table) {
            $table->dropColumn(['install', 'opened_at']);
        });
    }
};
