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
#[Fillable(['user_id', 'intent', 'product_key', 'amount', 'currency', 'payment_method', 'gateway_reference', 'status', 'raw_payload'])]
class PaymentTransaction extends Model
{
    public const PENDING = 'pending';

    public const COMPLETED = 'completed';

    public const FAILED = 'failed';

    public const REFUNDED = 'refunded';

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
