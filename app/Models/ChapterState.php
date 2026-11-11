<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per user per chapter: done? bookmarked? last opened when?
 * The chapter itself is a pair of slugs from config/course.php.
 */
#[Fillable(['course', 'chapter', 'completed_at', 'bookmarked_at', 'last_viewed_at'])]
class ChapterState extends Model
{
    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'bookmarked_at' => 'datetime',
            'last_viewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function key(): string
    {
        return "{$this->course}/{$this->chapter}";
    }
}
