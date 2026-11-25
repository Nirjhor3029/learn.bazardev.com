<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('course_pricing', function (Blueprint $table) {
            // Null = default rule (only the first chapter stays a free preview). Once an
            // admin saves the pricing screen, this becomes the exact, explicit list of
            // chapters that stay free regardless of ownership — including an empty array,
            // meaning the admin deliberately locked every chapter.
            $table->json('free_chapter_slugs')->nullable()->after('subscription_eligible');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('course_pricing', function (Blueprint $table) {
            $table->dropColumn('free_chapter_slugs');
        });
    }
};
