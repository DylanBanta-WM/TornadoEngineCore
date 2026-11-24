<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // How far a running install or update is (0 to 100), for the progress bar.
    public function up(): void
    {
        Schema::table('update_jobs', function (Blueprint $table) {
            $table->unsignedTinyInteger('progress')->default(0)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('update_jobs', function (Blueprint $table) {
            $table->dropColumn('progress');
        });
    }
};
