<?php

namespace App\Http\Controllers;

use App\Models\CoursePricing;
use App\Models\PaymentTransaction;
use App\Services\Entitlements;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymentInitiationException;
use App\Services\Payments\PaymentIntent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Starts a real payment via pay.bazardev. Only course purchases are wired
 * up here. Two things deliberately NOT done yet, both flagged in
 * plans/paid-access-and-entitlements.md rather than guessed at:
 *   - The terminal add-on has no price/self-serve purchase yet (CoursePricing
 *     only tracks course pricing today) - admin grants via /admin/entitlements
 *     are the only path to terminal access for now.
 *   - The all-access subscription (Phase 3) isn't started - its renewal
 *     period and price aren't decided.
 *
 * Nothing in this controller ever grants access on its own - only
 * PaymentWebhookController does that, and only once pay.bazardev confirms
 * payment. This controller just starts the attempt and records it.
 */
class PaymentController extends Controller
{
    public function __construct(private PaymentGateway $gateway, private Entitlements $entitlements) {}

    public function checkoutCourse(Request $request, string $course): RedirectResponse
    {
        abort_unless(array_key_exists($course, config('course.courses')), 404);

        $user = $request->user();

        if ($this->entitlements->canAccessCourse($user, $course)) {
            return redirect()->route('course.show', $course)->with('status', 'You already have access to this course.');
        }

        $price = CoursePricing::query()->where('course_slug', $course)->first();
        abort_if(! $price || $price->is_free || $price->price_amount <= 0, 404);

        return $this->startCheckout($request, PaymentTransaction::COURSE_PURCHASE, $course, $price->price_amount, $price->currency);
    }

    private function startCheckout(Request $request, string $intent, string $productKey, int $amount, string $currency): RedirectResponse
    {
        $user = $request->user();
        $tranId = 'ltx-'.Str::random(24);

        $transaction = PaymentTransaction::create([
            'user_id' => $user->id,
            'tran_id' => $tranId,
            'intent' => $intent,
            'product_key' => $productKey,
            'amount' => $amount,
            'currency' => $currency,
            'status' => PaymentTransaction::PENDING,
        ]);

        try {
            $session = $this->gateway->initiate(new PaymentIntent(
                tranId: $tranId,
                amount: $amount,
                currency: $currency,
                successUrl: route('payments.return', ['status' => 'success', 'tran_id' => $tranId]),
                failUrl: route('payments.return', ['status' => 'fail', 'tran_id' => $tranId]),
                cancelUrl: route('payments.return', ['status' => 'cancel', 'tran_id' => $tranId]),
                customerName: $user->name,
                customerEmail: $user->email,
                meta: ['product_key' => $productKey, 'intent' => $intent],
            ));
        } catch (PaymentInitiationException $e) {
            $transaction->forceFill(['status' => PaymentTransaction::FAILED])->save();

            return back()->with('status', 'Could not start payment: '.$e->getMessage());
        }

        $transaction->forceFill(['gateway_reference' => $session->referenceCode])->save();

        return redirect()->away($session->paymentUrl);
    }

    /** Landing page after pay.bazardev's checkout - informational only, never grants access itself. */
    public function returned(Request $request): \Illuminate\View\View
    {
        $tranId = (string) $request->query('tran_id');
        $transaction = PaymentTransaction::query()->where('tran_id', $tranId)->where('user_id', $request->user()->id)->first();

        return view('payments.return', [
            'redirectStatus' => $request->query('status'),
            'transaction' => $transaction,
        ]);
    }
}
