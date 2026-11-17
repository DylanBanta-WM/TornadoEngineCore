<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Where someone is in the tour after first-time setup (null: not taking it).
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('tour_step')->nullable()->after('must_change_password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('tour_step');
        });
    }
};
