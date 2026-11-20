<?php

namespace Tests\Fakes;

use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymentInitiationException;
use App\Services\Payments\PaymentIntent;
use App\Services\Payments\PaymentSession;
use App\Services\Payments\PaymentStatus;
use Illuminate\Http\Request;

/** A controllable stand-in for pay.bazardev, since tests can't hit the real API. */
class FakePaymentGateway implements PaymentGateway
{
    public ?PaymentIntent $lastIntent = null;

    public bool $shouldFailInit = false;

    public bool $validSignature = true;

    /** @var array<string, PaymentStatus> keyed by tranId, for check() */
    public array $statusesByTranId = [];

    public function initiate(PaymentIntent $intent): PaymentSession
    {
        $this->lastIntent = $intent;

        if ($this->shouldFailInit) {
            throw new PaymentInitiationException('fake gateway: forced failure');
        }

        return new PaymentSession(
            referenceCode: 'ref-'.$intent->tranId,
            paymentUrl: 'https://pay.bazardev.com/checkout/ref-'.$intent->tranId,
        );
    }

    public function check(string $tranId): PaymentStatus
    {
        return $this->statusesByTranId[$tranId]
            ?? new PaymentStatus($tranId, 'ref-'.$tranId, 'pending', 0, 'BDT');
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        return $this->validSignature;
    }

    public function parseWebhook(Request $request): PaymentStatus
    {
        $data = $request->json()->all();

        return new PaymentStatus(
            tranId: $data['tran_id'],
            referenceCode: $data['reference_code'] ?? 'ref-'.$data['tran_id'],
            status: $data['status'],
            amount: (int) ($data['amount'] ?? 0),
            currency: $data['currency'] ?? 'BDT',
        );
    }
}
