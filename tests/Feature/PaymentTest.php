<?php

namespace Tests\Feature;

use App\Models\CoursePricing;
use App\Models\Entitlement;
use App\Models\PaymentTransaction;
use App\Services\Payments\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fakes\FakePaymentGateway;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private function fake(): FakePaymentGateway
    {
        $fake = new FakePaymentGateway;
        $this->instance(PaymentGateway::class, $fake);

        return $fake;
    }

    private function markPaid(string $course = 'docker', int $price = 500): void
    {
        CoursePricing::create(['course_slug' => $course, 'is_free' => false, 'price_amount' => $price]);
    }

    public function test_checking_out_a_paid_course_creates_a_pending_transaction_and_redirects_to_the_gateway(): void
    {
        $fake = $this->fake();
        $this->markPaid();
        $user = \App\Models\User::factory()->create();

        $response = $this->actingAs($user)->post('/pay/course/docker');

        $response->assertRedirect();
        $this->assertStringStartsWith('https://pay.bazardev.com/checkout/', $response->headers->get('Location'));

        $this->assertDatabaseHas('payment_transactions', [
            'user_id' => $user->id,
            'intent' => PaymentTransaction::COURSE_PURCHASE,
            'product_key' => 'docker',
            'amount' => 500,
            'status' => PaymentTransaction::PENDING,
        ]);

        $this->assertNotNull($fake->lastIntent);
        $this->assertSame(500, $fake->lastIntent->amount);
    }

    public function test_checking_out_a_course_already_owned_does_not_start_a_new_payment(): void
    {
        $this->fake();
        $this->markPaid();
        $user = \App\Models\User::factory()->create();
        $user->entitlements()->create([
            'product_type' => Entitlement::COURSE,
            'product_key' => 'docker',
            'source' => Entitlement::ADMIN_GRANT,
            'starts_at' => now(),
        ]);

        $this->actingAs($user)->post('/pay/course/docker')->assertRedirect(route('course.show', 'docker'));

        $this->assertDatabaseCount('payment_transactions', 0);
    }

    public function test_checking_out_a_free_course_is_not_allowed(): void
    {
        $this->fake();
        $user = \App\Models\User::factory()->create();

        // Free (unconfigured) course -> canAccessCourse() is already true,
        // so checkout redirects as "already owned" rather than ever reaching
        // the price check. No payment_transactions row should be created either way.
        $this->actingAs($user)->post('/pay/course/docker')->assertRedirect(route('course.show', 'docker'));

        $this->assertDatabaseCount('payment_transactions', 0);
    }

    public function test_a_gateway_failure_marks_the_transaction_failed_and_sends_the_user_back(): void
    {
        $fake = $this->fake();
        $fake->shouldFailInit = true;
        $this->markPaid();
        $user = \App\Models\User::factory()->create();

        $this->actingAs($user)->post('/pay/course/docker')->assertSessionHas('status');

        $this->assertDatabaseHas('payment_transactions', ['status' => PaymentTransaction::FAILED]);
    }

    public function test_a_valid_paid_webhook_grants_a_course_entitlement(): void
    {
        $this->fake();
        $this->markPaid();
        $user = \App\Models\User::factory()->create();
        $transaction = PaymentTransaction::create([
            'user_id' => $user->id,
            'tran_id' => 'ltx-test-1',
            'intent' => PaymentTransaction::COURSE_PURCHASE,
            'product_key' => 'docker',
            'amount' => 500,
            'status' => PaymentTransaction::PENDING,
        ]);

        $this->postJson('/webhooks/pay-bazardev', [
            'event' => 'payment.paid',
            'tran_id' => 'ltx-test-1',
            'reference_code' => 'ref-ltx-test-1',
            'amount' => 500,
            'currency' => 'BDT',
            'status' => 'paid',
        ])->assertOk();

        $transaction->refresh();
        $this->assertSame(PaymentTransaction::PAID, $transaction->status);

        $this->assertDatabaseHas('entitlements', [
            'user_id' => $user->id,
            'product_type' => Entitlement::COURSE,
            'product_key' => 'docker',
            'source' => Entitlement::PURCHASE,
            'payment_transaction_id' => $transaction->id,
        ]);
    }

    public function test_a_webhook_with_a_bad_signature_is_rejected_and_changes_nothing(): void
    {
        $fake = $this->fake();
        $fake->validSignature = false;
        $user = \App\Models\User::factory()->create();
        $transaction = PaymentTransaction::create([
            'user_id' => $user->id, 'tran_id' => 'ltx-test-2', 'intent' => PaymentTransaction::COURSE_PURCHASE,
            'product_key' => 'docker', 'amount' => 500, 'status' => PaymentTransaction::PENDING,
        ]);

        $this->postJson('/webhooks/pay-bazardev', [
            'tran_id' => 'ltx-test-2', 'reference_code' => 'ref', 'amount' => 500, 'currency' => 'BDT', 'status' => 'paid',
        ])->assertStatus(400);

        $transaction->refresh();
        $this->assertSame(PaymentTransaction::PENDING, $transaction->status);
        $this->assertDatabaseCount('entitlements', 0);
    }

    public function test_the_same_paid_webhook_delivered_twice_only_grants_one_entitlement(): void
    {
        $this->fake();
        $user = \App\Models\User::factory()->create();
        PaymentTransaction::create([
            'user_id' => $user->id, 'tran_id' => 'ltx-test-3', 'intent' => PaymentTransaction::COURSE_PURCHASE,
            'product_key' => 'docker', 'amount' => 500, 'status' => PaymentTransaction::PENDING,
        ]);

        $payload = ['tran_id' => 'ltx-test-3', 'reference_code' => 'ref', 'amount' => 500, 'currency' => 'BDT', 'status' => 'paid'];

        $this->postJson('/webhooks/pay-bazardev', $payload)->assertOk();
        $this->postJson('/webhooks/pay-bazardev', $payload)->assertOk();

        $this->assertDatabaseCount('entitlements', 1);
    }

    public function test_a_webhook_for_an_unknown_tran_id_is_acknowledged_but_does_nothing(): void
    {
        $this->fake();

        $this->postJson('/webhooks/pay-bazardev', [
            'tran_id' => 'does-not-exist', 'reference_code' => 'ref', 'amount' => 500, 'currency' => 'BDT', 'status' => 'paid',
        ])->assertOk();

        $this->assertDatabaseCount('entitlements', 0);
    }

    public function test_reconcile_command_polls_and_resolves_a_stale_pending_transaction(): void
    {
        $fake = $this->fake();
        $user = \App\Models\User::factory()->create();
        $transaction = PaymentTransaction::create([
            'user_id' => $user->id, 'tran_id' => 'ltx-test-4', 'intent' => PaymentTransaction::COURSE_PURCHASE,
            'product_key' => 'docker', 'amount' => 500, 'status' => PaymentTransaction::PENDING,
        ]);
        $transaction->forceFill(['created_at' => now()->subHour()])->save();

        $fake->statusesByTranId['ltx-test-4'] = new \App\Services\Payments\PaymentStatus(
            'ltx-test-4', 'ref-ltx-test-4', 'expired', 500, 'BDT',
        );

        $this->artisan('app:reconcile-payments')->assertSuccessful();

        $transaction->refresh();
        $this->assertSame(PaymentTransaction::EXPIRED, $transaction->status);
        $this->assertDatabaseCount('entitlements', 0);
    }

    public function test_reconcile_command_leaves_recent_pending_transactions_alone(): void
    {
        $fake = $this->fake();
        $user = \App\Models\User::factory()->create();
        PaymentTransaction::create([
            'user_id' => $user->id, 'tran_id' => 'ltx-test-5', 'intent' => PaymentTransaction::COURSE_PURCHASE,
            'product_key' => 'docker', 'amount' => 500, 'status' => PaymentTransaction::PENDING,
        ]);

        $this->artisan('app:reconcile-payments')->assertSuccessful();

        $this->assertDatabaseHas('payment_transactions', ['tran_id' => 'ltx-test-5', 'status' => PaymentTransaction::PENDING]);
    }
}
