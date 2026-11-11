<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Chapters live in config/course.php (not in the database), so a chapter is
     * identified by its course slug + chapter slug. Never rename those slugs.
     */
    public function up(): void
    {
        Schema::create('chapter_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('course', 60);
            $table->string('chapter', 120);
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('bookmarked_at')->nullable();
            $table->timestamp('last_viewed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'course', 'chapter']);
        });

        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('course', 60);
            $table->string('chapter', 120);
            $table->text('body');
            $table->timestamps();

            $table->unique(['user_id', 'course', 'chapter']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
        Schema::dropIfExists('chapter_states');
    }
};
