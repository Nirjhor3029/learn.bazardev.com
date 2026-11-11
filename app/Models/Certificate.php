<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A course-completion certificate. Only the exam service creates these — nothing a
 * visitor sends can reach them. The code in the public link is the only proof needed.
 */
#[Fillable(['course', 'code', 'name', 'score', 'total'])]
class Certificate extends Model
{
    /** No 0/O or 1/I, so a code read from paper or a screenshot is hard to get wrong. */
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** A new unused code like "7K2M-9QXA-4HTR". */
    public static function newCode(): string
    {
        do {
            $raw = '';
            for ($i = 0; $i < 12; $i++) {
                $raw .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
            $code = implode('-', str_split($raw, 4));
        } while (static::where('code', $code)->exists());

        return $code;
    }

    public function courseTitle(): string
    {
        return config("course.courses.{$this->course}.title", $this->course);
    }
}
