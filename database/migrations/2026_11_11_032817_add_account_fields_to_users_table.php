<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Admins can approve accounts and (later) moderate contributions.
            $table->boolean('is_admin')->default(false)->after('password');
            // Email stays private unless the user opts in to showing it next to their contributions.
            $table->boolean('show_email')->default(false)->after('is_admin');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_admin', 'show_email']);
        });
    }
};
