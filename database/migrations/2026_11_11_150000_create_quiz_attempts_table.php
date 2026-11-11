<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per quiz attempt a logged-in reader makes. `chapter` is a chapter slug from
     * config/course.php; NULL is reserved for the course's final exam (added in a later step).
     * Only the result is stored — never the answers the reader picked.
     */
    public function up(): void
    {
        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('course', 60);
            $table->string('chapter', 120)->nullable();
            $table->unsignedSmallInteger('score');
            $table->unsignedSmallInteger('total');
            $table->boolean('passed');
            $table->timestamps();

            $table->index(['user_id', 'course', 'chapter']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_attempts');
    }
};
