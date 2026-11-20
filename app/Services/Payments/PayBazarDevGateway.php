<?php

namespace App\Services\Payments;

use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * pay.bazardev.com, from a merchant's side. Confirmed by reading its own
 * source (it's the owner's other project) rather than guessed - see
 * plans/paid-access-and-entitlements.md §4 for the full writeup. Two things
 * about it that shape this class and aren't obvious from a normal gateway:
 *
 * 1. It is NOT a real bKash/Nagad API integration. It shows the customer a
 *    personal bKash/Nagad number and matches payment via SMS capture or
 *    manual admin review - "paid" can take a while, and `manual_review` is
 *    a real, expected status, not an edge case.
 * 2. It does NOT fire a webhook for every status change - `expired` and
 *    (outside its sandbox simulator) `failed` never produce a webhook call
 *    at all. Anything that needs to notice those must poll check() instead
 *    of waiting on a webhook that will never arrive. See the scheduled
 *    reconciliation command this plan calls for.
 *
 * Also note: pay.bazardev signs every webhook with its LIVE api_secret, even
 * for a sandbox-mode transaction - verifyWebhookSignature() below always
 * uses the live secret for that reason, deliberately, not by oversight.
 */
class PayBazarDevGateway implements PaymentGateway
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly string $apiSecret,
        private readonly string $liveApiSecret,
    ) {}

    public function initiate(PaymentIntent $intent): PaymentSession
    {
        $response = $this->client()->post('/api/v1/payment/init', [
            'tran_id' => $intent->tranId,
            'amount' => $intent->amount,
            'currency' => $intent->currency,
            'customer_name' => $intent->customerName,
            'customer_phone' => $intent->customerPhone,
            'customer_email' => $intent->customerEmail,
            'success_url' => $intent->successUrl,
            'fail_url' => $intent->failUrl,
            'cancel_url' => $intent->cancelUrl,
            'meta' => $intent->meta,
        ]);

        if ($response->status() === 422) {
            throw new PaymentInitiationException($response->json('message') ?? 'pay.bazardev rejected this payment request.');
        }

        $response->throw();
        $data = $response->json();

        return new PaymentSession(
            referenceCode: $data['reference_code'],
            paymentUrl: $data['payment_url'],
            expiresAt: isset($data['expires_at']) ? new \DateTimeImmutable($data['expires_at']) : null,
        );
    }

    public function check(string $tranId): PaymentStatus
    {
        $response = $this->client()->get("/api/v1/payment/validate/{$tranId}");
        $response->throw();

        return $this->toStatus($response->json());
    }

    /**
     * Always checked against the LIVE api_secret, matching pay.bazardev's
     * own (slightly surprising) behavior of signing every webhook with it
     * regardless of mode - see this class's docblock.
     */
    public function verifyWebhookSignature(Request $request): bool
    {
        $expected = hash_hmac('sha256', $request->getContent(), $this->liveApiSecret);
        $actual = (string) $request->header('X-Pay-Signature');

        return $actual !== '' && hash_equals($expected, $actual);
    }

    public function parseWebhook(Request $request): PaymentStatus
    {
        return $this->toStatus($request->json()->all());
    }

    private function toStatus(array $data): PaymentStatus
    {
        return new PaymentStatus(
            tranId: $data['tran_id'],
            referenceCode: $data['reference_code'],
            status: $data['status'],
            amount: (int) $data['amount'],
            currency: $data['currency'],
            paidAt: ! empty($data['paid_at']) ? new \DateTimeImmutable($data['paid_at']) : null,
        );
    }

    private function client(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withHeaders(['X-Api-Key' => $this->apiKey, 'X-Api-Secret' => $this->apiSecret])
            ->acceptJson()
            ->timeout(15);
    }
}
