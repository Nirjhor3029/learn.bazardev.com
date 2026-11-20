<?php

namespace App\Http\Controllers;

use App\Models\Entitlement;
use App\Models\PaymentTransaction;
use App\Services\Payments\PaymentGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Server-to-server, from pay.bazardev — not a browser session, so no CSRF
 * token (excluded in bootstrap/app.php, same pattern as the terminal's
 * verify-ticket endpoint). This is the ONLY place that ever grants a course
 * entitlement from a real payment; the checkout return page never does.
 *
 * Reminder (see PayBazarDevGateway's docblock): this gateway does not fire a
 * webhook for `expired`, and only fires `failed` from its sandbox simulator —
 * the scheduled app:reconcile-payments command covers those via polling.
 */
class PaymentWebhookController extends Controller
{
    public function __construct(private PaymentGateway $gateway) {}

    public function handle(Request $request): JsonResponse
    {
        if (! $this->gateway->verifyWebhookSignature($request)) {
            Log::warning('pay.bazardev webhook: bad signature', ['body' => $request->getContent()]);

            return response()->json(['error' => 'invalid_signature'], 400);
        }

        $status = $this->gateway->parseWebhook($request);

        $transaction = PaymentTransaction::query()->where('tran_id', $status->tranId)->first();

        if (! $transaction) {
            // Idempotent on an unknown tran_id too - ack it so the gateway doesn't retry forever,
            // but there is nothing of ours to update.
            Log::warning('pay.bazardev webhook: unknown tran_id', ['tran_id' => $status->tranId]);

            return response()->json(['ok' => true]);
        }

        $this->apply($transaction, $status->status, $request->getContent());

        return response()->json(['ok' => true]);
    }

    /** Also called by the reconcile command after a direct check() poll - same idempotent logic either way. */
    public function apply(PaymentTransaction $transaction, string $newStatus, ?string $rawPayload = null): void
    {
        // Idempotent: a webhook that's already been applied (same status, already paid) is a no-op,
        // not a double-grant. Gateway retries and duplicate deliveries are expected, not an edge case.
        if ($transaction->status === $newStatus) {
            return;
        }

        if ($transaction->status === PaymentTransaction::PAID) {
            // Never move a transaction OFF paid because of a late/out-of-order webhook.
            return;
        }

        $transaction->forceFill([
            'status' => $newStatus,
            'raw_payload' => $rawPayload ? json_decode($rawPayload, true) : $transaction->raw_payload,
        ])->save();

        if ($newStatus === PaymentTransaction::PAID) {
            $this->grantEntitlement($transaction);
        }
    }

    private function grantEntitlement(PaymentTransaction $transaction): void
    {
        if ($transaction->entitlement()->exists()) {
            return;
        }

        Entitlement::create([
            'user_id' => $transaction->user_id,
            'product_type' => Entitlement::COURSE,
            'product_key' => $transaction->product_key,
            'source' => Entitlement::PURCHASE,
            'payment_transaction_id' => $transaction->id,
            'starts_at' => now(),
        ]);
    }
}
