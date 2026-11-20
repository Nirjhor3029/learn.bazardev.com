<?php

namespace App\Services\Payments;

/** What we're asking the gateway to start collecting payment for. */
final class PaymentIntent
{
    public function __construct(
        public readonly string $tranId,
        public readonly int $amount,
        public readonly string $currency,
        public readonly string $successUrl,
        public readonly string $failUrl,
        public readonly string $cancelUrl,
        public readonly ?string $customerName = null,
        public readonly ?string $customerPhone = null,
        public readonly ?string $customerEmail = null,
        public readonly array $meta = [],
    ) {}
}
