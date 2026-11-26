<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One full mock-exam sitting. Listening/Reading carry a real score+band; Writing/Speaking
 * are deliberately never scored (see App\Services\MockExam) — they just store the
 * reader's own text/notes for side-by-side comparison with sample answers.
 */
#[Fillable(['listening_self_reported_raw', 'reading_answers', 'writing_task1_text', 'writing_task2_text', 'speaking_notes'])]
class MockExamAttempt extends Model
{
    public const IN_PROGRESS = 'in_progress';

    public const COMPLETED = 'completed';

    public const LISTENING = 'listening';

    public const READING = 'reading';

    public const WRITING = 'writing';

    public const SPEAKING = 'speaking';

    public const DONE = 'done';

    protected function casts(): array
    {
        return [
            'reading_answers' => 'array',
            'listening_started_at' => 'datetime',
            'reading_started_at' => 'datetime',
            'writing_started_at' => 'datetime',
            'speaking_started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function title(): string
    {
        return config("mock_exam.sets.{$this->mock_exam}.title", $this->mock_exam);
    }

    public function isInProgress(): bool
    {
        return $this->status === self::IN_PROGRESS;
    }

    /** "7" for 7.0, "7.5" for 7.5 — bands are whole or half numbers, never more precise. */
    public static function bandLabel(?float $band): string
    {
        if ($band === null) {
            return '—';
        }

        return fmod($band, 1.0) === 0.0 ? (string) (int) $band : (string) $band;
    }
}
