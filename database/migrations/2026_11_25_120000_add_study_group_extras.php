<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Group Study follow-ups: a per-member privacy toggle, shared goals,
     * pinned resources, and lightweight reactions on posts.
     */
    public function up(): void
    {
        Schema::table('study_group_members', function (Blueprint $table) {
            $table->boolean('hide_counts')->default(false)->after('role');
        });

        Schema::create('study_group_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('study_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('title', 120);
            $table->string('target_chapter', 120)->nullable();
            $table->date('due_date')->nullable();
            $table->timestamps();
        });

        Schema::create('study_group_resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('study_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 150);
            $table->string('url', 500);
            $table->timestamps();
        });

        Schema::create('study_group_post_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('study_group_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['study_group_post_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('study_group_post_reactions');
        Schema::dropIfExists('study_group_resources');
        Schema::dropIfExists('study_group_goals');

        Schema::table('study_group_members', function (Blueprint $table) {
            $table->dropColumn('hide_counts');
        });
    }
};
