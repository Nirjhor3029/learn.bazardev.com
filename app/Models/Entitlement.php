<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row = one grant of access to a course, a course's terminal add-on, or
 * the all-access subscription - however it came to exist. Never hard-deleted:
 * a refund or admin revocation sets `revoked_at` instead, so the audit trail
 * (and any certificate already earned) survives. See EntitlementService for
 * the actual access-control logic that reads this table.
 */
#[Fillable(['user_id', 'product_type', 'product_key', 'source', 'granted_by', 'grant_note', 'payment_transaction_id', 'starts_at', 'expires_at'])]
class Entitlement extends Model
{
    public const COURSE = 'course';

    public const TERMINAL = 'terminal';

    public const SUBSCRIPTION_ALL_ACCESS = 'subscription_all_access';

    public const PURCHASE = 'purchase';

    public const ADMIN_GRANT = 'admin_grant';

    public const FREE_COURSE = 'free_course';

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    public function paymentTransaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class);
    }

    /** Not revoked, and not expired (or never expires). */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
