<?php

namespace App\Services\Payments;

use DateTimeInterface;

/** A normalized read of a transaction's current state, whether from a webhook or a poll. */
final class PaymentStatus
{
    public function __construct(
        public readonly string $tranId,
        public readonly string $referenceCode,
        public readonly string $status, // pending | paid | failed | expired | manual_review
        public readonly int $amount,
        public readonly string $currency,
        public readonly ?DateTimeInterface $paidAt = null,
    ) {}

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }
}
