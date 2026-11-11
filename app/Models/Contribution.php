<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Something a reader sent in for one chapter. Stays private until an admin approves it.
 *
 * - resource   : a helpful link (title + url, optional "why")  -> shown under the chapter once approved
 * - tip        : a short piece of advice                       -> shown under the chapter once approved
 * - correction : "this part of the chapter is wrong"            -> only ever seen by the admin
 *
 * status, reviewed_by, reviewed_at and admin_note are deliberately NOT fillable —
 * only the admin screens set them, with forceFill().
 */
#[Fillable(['course', 'chapter', 'type', 'title', 'url', 'body'])]
class Contribution extends Model
{
    public const TYPES = ['resource', 'tip', 'correction'];

    /** Types that appear under the chapter once approved. */
    public const PUBLIC_TYPES = ['resource', 'tip'];

    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    public const TITLE_MAX = 120;

    public const BODY_MAX = 1000;

    /** How many a normal user may send in any 24 hours. */
    public const DAILY_LIMIT = 5;

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** What everybody may see under a chapter. */
    public function scopeVisible(Builder $query): void
    {
        $query->where('status', self::APPROVED)->whereIn('type', self::PUBLIC_TYPES);
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    public function isPublic(): bool
    {
        return $this->status === self::APPROVED && in_array($this->type, self::PUBLIC_TYPES, true);
    }

    /** "docs.docker.com" for a resource link, so readers see where a link goes before clicking. */
    public function host(): ?string
    {
        $host = $this->url ? parse_url($this->url, PHP_URL_HOST) : null;

        return $host ? preg_replace('/^www\./', '', $host) : null;
    }

    /** Words for the sender: where their contribution stands. */
    public function statusLabel(): string
    {
        return match (true) {
            $this->status === self::PENDING => 'Waiting for review',
            $this->status === self::REJECTED => 'Not accepted',
            $this->type === 'correction' => 'Resolved — thank you',
            default => 'Published',
        };
    }

    public function typeLabel(): string
    {
        return ['resource' => 'Resource', 'tip' => 'Tip', 'correction' => 'Mistake report'][$this->type] ?? 'Contribution';
    }
}
