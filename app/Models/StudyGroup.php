<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A small, private study group for one course. Members join with invite_code;
 * nothing here is discoverable without it.
 */
#[Fillable(['name', 'course', 'member_limit'])]
class StudyGroup extends Model
{
    public const MEMBER_LIMIT_DEFAULT = 20;

    /** No 0/O or 1/I, so a code read aloud or typed from memory is hard to get wrong. */
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): HasMany
    {
        return $this->hasMany(StudyGroupMember::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(StudyGroupPost::class);
    }

    public function goals(): HasMany
    {
        return $this->hasMany(StudyGroupGoal::class);
    }

    public function resources(): HasMany
    {
        return $this->hasMany(StudyGroupResource::class);
    }

    public function isOwner(User $user): bool
    {
        return $this->members()->where('user_id', $user->id)->where('role', 'owner')->exists();
    }

    public function hasMember(User $user): bool
    {
        return $this->members()->where('user_id', $user->id)->exists();
    }

    public function courseTitle(): string
    {
        return config("course.courses.{$this->course}.title", $this->course);
    }

    /** A new unused code like "7K2M9QXA". */
    public static function newInviteCode(): string
    {
        do {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
        } while (self::query()->where('invite_code', $code)->exists());

        return $code;
    }
}
