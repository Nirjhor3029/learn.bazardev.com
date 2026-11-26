<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per full mock-exam sitting (Listening, Reading, Writing, Speaking in one
     * attempt). Listening has no questions of our own — the reader takes the real test
     * on British Council's site and self-reports their raw score here, so
     * `listening_self_reported_raw` is the only Listening column; everything else is
     * graded or saved directly by this app.
     */
    public function up(): void
    {
        Schema::create('mock_exam_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('mock_exam', 60);
            $table->string('status', 20)->default('in_progress');
            $table->string('current_section', 20)->default('listening');

            $table->timestamp('listening_started_at')->nullable();
            $table->unsignedTinyInteger('listening_self_reported_raw')->nullable();
            $table->unsignedTinyInteger('listening_total')->nullable();
            $table->decimal('listening_band', 3, 1)->nullable();

            $table->timestamp('reading_started_at')->nullable();
            $table->json('reading_answers')->nullable();
            $table->unsignedTinyInteger('reading_correct')->nullable();
            $table->unsignedTinyInteger('reading_total')->nullable();
            $table->decimal('reading_band', 3, 1)->nullable();

            $table->timestamp('writing_started_at')->nullable();
            $table->text('writing_task1_text')->nullable();
            $table->text('writing_task2_text')->nullable();

            $table->timestamp('speaking_started_at')->nullable();
            $table->text('speaking_notes')->nullable();

            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'mock_exam']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mock_exam_attempts');
    }
};
