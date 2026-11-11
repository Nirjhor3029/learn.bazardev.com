<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A private, plain-text note a user keeps beside one chapter. Never shown to anyone else. */
#[Fillable(['course', 'chapter', 'body'])]
class Note extends Model
{
    public const MAX_LENGTH = 10000;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
