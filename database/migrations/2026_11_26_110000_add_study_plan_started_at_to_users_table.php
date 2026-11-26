<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Set once, on "Start my 3-month plan" — never overwritten, so the day
            // count never silently resets. Null means the reader hasn't started yet.
            $table->timestamp('study_plan_started_at')->nullable()->after('show_email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('study_plan_started_at');
        });
    }
};
