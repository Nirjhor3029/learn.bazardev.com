<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * The billing audit trail. Access control never reads this table directly -
 * see Entitlement for that. This is purely a record of money that moved
 * (or tried to) through pay.bazardev.
 */
#[Fillable(['user_id', 'tran_id', 'intent', 'product_key', 'amount', 'currency', 'payment_method', 'gateway_reference', 'status', 'raw_payload'])]
class PaymentTransaction extends Model
{
    // Matches pay.bazardev's own status vocabulary exactly (its /validate and
    // webhook payloads use these strings) - REFUNDED is the one status that's
    // entirely ours, since pay.bazardev has no refund concept of its own.
    public const PENDING = 'pending';

    public const PAID = 'paid';

    public const FAILED = 'failed';

    public const EXPIRED = 'expired';

    public const MANUAL_REVIEW = 'manual_review';

    public const REFUNDED = 'refunded';

    public const COURSE_PURCHASE = 'course_purchase';

    public const TERMINAL_PURCHASE = 'terminal_purchase';

    public const SUBSCRIPTION = 'subscription';

    protected function casts(): array
    {
        return [
            'raw_payload' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function entitlement(): HasOne
    {
        return $this->hasOne(Entitlement::class);
    }
}
