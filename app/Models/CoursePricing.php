<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Commercial metadata per course, admin-editable at runtime. Course *content*
 * (title, chapters, folder) stays in config/course.php - this table only adds
 * the money/access layer.
 *
 * Deliberate safe-by-default rule: a course with NO row here yet is treated
 * as open/free, not locked. The paywall only switches on for a course once an
 * admin explicitly configures it here with `is_free = false` (via the pricing
 * screen). This avoids every existing course on the live site becoming
 * instantly locked the moment this migration runs — the admin turns the
 * paywall on per course, deliberately, one at a time.
 */
#[Fillable(['course_slug', 'is_free', 'price_amount', 'currency', 'subscription_eligible', 'free_chapter_slugs', 'updated_by'])]
class CoursePricing extends Model
{
    protected $table = 'course_pricing';

    protected $primaryKey = 'course_slug';

    protected $keyType = 'string';

    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'is_free' => 'boolean',
            'subscription_eligible' => 'boolean',
            'free_chapter_slugs' => 'array',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** True unless a row exists and is explicitly marked paid (is_free = false). No row = free/open. */
    public static function isFree(string $courseSlug): bool
    {
        $row = static::query()->where('course_slug', $courseSlug)->first();

        return $row === null || $row->is_free;
    }

    public static function isConfigured(string $courseSlug): bool
    {
        return static::query()->where('course_slug', $courseSlug)->exists();
    }

    /**
     * Whether a given chapter stays a free preview on a paid course. An admin-picked
     * list (`free_chapter_slugs`, even an empty one) always wins; with no list saved
     * yet, the default is "only the first chapter of the course".
     *
     * @param  string[]  $orderedChapterSlugs  Every readable chapter of the course, in order.
     */
    public function isChapterFree(string $chapterSlug, array $orderedChapterSlugs): bool
    {
        if ($this->free_chapter_slugs !== null) {
            return in_array($chapterSlug, $this->free_chapter_slugs, true);
        }

        return ($orderedChapterSlugs[0] ?? null) === $chapterSlug;
    }
}
