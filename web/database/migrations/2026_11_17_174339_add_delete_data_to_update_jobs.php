<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Uninstalling a module ("remove" jobs) can also roll back its migrations.
    public function up(): void
    {
        Schema::table('update_jobs', function (Blueprint $table) {
            $table->boolean('delete_data')->default(false)->after('install');
        });
    }

    public function down(): void
    {
        Schema::table('update_jobs', function (Blueprint $table) {
            $table->dropColumn('delete_data');
        });
    }
};
