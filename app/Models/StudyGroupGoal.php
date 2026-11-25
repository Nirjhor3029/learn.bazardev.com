<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A shared target the group owner sets, e.g. "Finish Chapter 5 by Friday". */
#[Fillable(['title', 'target_chapter', 'due_date', 'created_by'])]
class StudyGroupGoal extends Model
{
    protected function casts(): array
    {
        return ['due_date' => 'date'];
    }

    public function studyGroup(): BelongsTo
    {
        return $this->belongsTo(StudyGroup::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isOverdue(): bool
    {
        return $this->due_date && $this->due_date->isPast();
    }

    /** Whether a member's own progress already reaches this goal's target chapter. */
    public function isMetBy(array $doneSlugs): bool
    {
        return $this->target_chapter && in_array($this->target_chapter, $doneSlugs, true);
    }
}
