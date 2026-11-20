<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which course's terminal this ticket is for. There is only one terminal
     * product today (Linux & Shell Basics, course slug "linux-shell") -
     * TerminalLaunchController hardcodes that slug when minting a ticket.
     * This column exists now so the entitlement check (added in the same
     * change) has something concrete to record, without a second migration
     * if a second course ever gets its own terminal later.
     */
    public function up(): void
    {
        Schema::table('terminal_launch_tickets', function (Blueprint $table) {
            $table->string('course_slug', 60)->nullable()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('terminal_launch_tickets', function (Blueprint $table) {
            $table->dropColumn('course_slug');
        });
    }
};
