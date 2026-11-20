<?php

namespace App\Services\Payments;

use Illuminate\Http\Request;

/**
 * One interface, so a second gateway could be added later without touching
 * call sites. PayBazarDevGateway is the only implementation that exists.
 */
interface PaymentGateway
{
    /** Start a payment; throws PaymentInitiationException if the gateway refuses. */
    public function initiate(PaymentIntent $intent): PaymentSession;

    /**
     * Ask the gateway directly for a transaction's current status. Needed
     * because this particular gateway does not fire a webhook for every
     * status (notably: expired and failed) - see PayBazarDevGateway's
     * class docblock.
     */
    public function check(string $tranId): PaymentStatus;

    /** True only if the request's signature proves it really came from the gateway. */
    public function verifyWebhookSignature(Request $request): bool;

    /** Only call after verifyWebhookSignature() has returned true. */
    public function parseWebhook(Request $request): PaymentStatus;
}
