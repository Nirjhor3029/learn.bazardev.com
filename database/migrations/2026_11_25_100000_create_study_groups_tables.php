<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A small, private study group scoped to one course: members join with an
     * invite code and can see each other's progress, ask questions, and comment.
     */
    public function up(): void
    {
        Schema::create('study_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('course', 60);
            $table->string('invite_code', 10)->unique();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('member_limit')->default(20);
            $table->timestamps();
        });

        Schema::create('study_group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('study_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 10)->default('member'); // owner | member
            $table->timestamp('joined_at');
            $table->timestamps();

            $table->unique(['study_group_id', 'user_id']);
        });

        Schema::create('study_group_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('study_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('study_group_posts')->cascadeOnDelete();
            $table->string('type', 10)->nullable(); // question | comment, only on top-level posts
            $table->string('chapter', 120)->nullable();
            $table->text('body');
            $table->timestamps();

            $table->index(['study_group_id', 'parent_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('study_group_posts');
        Schema::dropIfExists('study_group_members');
        Schema::dropIfExists('study_groups');
    }
};
