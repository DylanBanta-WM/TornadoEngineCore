<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A Core update someone chose to run even though the district's changes clash (they're set aside).
        Schema::table('update_jobs', function (Blueprint $table) {
            $table->boolean('anyway')->default(false)->after('delete_data');
        });
    }

    public function down(): void
    {
        Schema::table('update_jobs', function (Blueprint $table) {
            $table->dropColumn('anyway');
        });
    }
};
