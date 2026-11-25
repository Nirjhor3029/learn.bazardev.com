<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A link a member pinned for the whole group — no admin approval needed, unlike Contribution. */
#[Fillable(['title', 'url', 'user_id'])]
class StudyGroupResource extends Model
{
    public function studyGroup(): BelongsTo
    {
        return $this->belongsTo(StudyGroup::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** "docs.docker.com", so members see where a link goes before clicking. */
    public function host(): ?string
    {
        $host = parse_url($this->url, PHP_URL_HOST);

        return $host ? preg_replace('/^www\./', '', $host) : null;
    }
}
