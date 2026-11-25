<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A question or comment in a study group, or a reply to one (parent_id set).
 * type is only set on top-level posts; replies inherit the thread's meaning from their parent.
 */
#[Fillable(['user_id', 'type', 'chapter', 'body', 'parent_id'])]
class StudyGroupPost extends Model
{
    public const QUESTION = 'question';

    public const COMMENT = 'comment';

    public function studyGroup(): BelongsTo
    {
        return $this->belongsTo(StudyGroup::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->oldest();
    }

    public function isQuestion(): bool
    {
        return $this->type === self::QUESTION;
    }

    public function typeLabel(): string
    {
        return $this->type === self::QUESTION ? 'Question' : 'Comment';
    }
}
