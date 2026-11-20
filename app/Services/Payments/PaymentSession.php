<?php

namespace App\Services\Payments;

use DateTimeInterface;

/** What a gateway hands back right after starting a payment - where to send the customer. */
final class PaymentSession
{
    public function __construct(
        public readonly string $referenceCode,
        public readonly string $paymentUrl,
        public readonly ?DateTimeInterface $expiresAt = null,
    ) {}
}
