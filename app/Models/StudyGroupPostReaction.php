<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A single 👍 from one user on one post. One per user per post (toggle, not a counter). */
#[Fillable(['user_id'])]
class StudyGroupPostReaction extends Model
{
    public function post(): BelongsTo
    {
        return $this->belongsTo(StudyGroupPost::class, 'study_group_post_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
