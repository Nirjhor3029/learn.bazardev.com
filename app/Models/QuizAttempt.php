<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The result of one quiz attempt by a logged-in reader. A chapter of NULL means the
 * course's final exam. The picked answers are deliberately not stored.
 */
#[Fillable(['course', 'chapter', 'score', 'total', 'passed'])]
class QuizAttempt extends Model
{
    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'total' => 'integer',
            'passed' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
