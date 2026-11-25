<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One row per user per group: their role and when they joined. */
#[Fillable(['user_id', 'role', 'joined_at'])]
class StudyGroupMember extends Model
{
    public const OWNER = 'owner';

    public const MEMBER = 'member';

    protected function casts(): array
    {
        return ['joined_at' => 'datetime'];
    }

    public function studyGroup(): BelongsTo
    {
        return $this->belongsTo(StudyGroup::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isOwner(): bool
    {
        return $this->role === self::OWNER;
    }
}
