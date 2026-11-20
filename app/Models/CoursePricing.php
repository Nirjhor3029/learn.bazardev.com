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
#[Fillable(['course_slug', 'is_free', 'price_amount', 'currency', 'subscription_eligible', 'updated_by'])]
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
}
