<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The billing audit trail — every attempt to pay via pay.bazardev (bKash/Nagad),
     * independent of whether the resulting entitlement is later revoked. Access
     * control never reads this table directly; see the `entitlements` table for that.
     */
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('intent', 30); // course_purchase | terminal_purchase | subscription
            $table->string('product_key', 60)->nullable(); // course slug; null for an all-access subscription
            $table->unsignedInteger('amount');
            $table->string('currency', 3)->default('BDT');
            $table->string('payment_method', 20)->nullable(); // bkash | nagad, as reported back by pay.bazardev
            $table->string('gateway_reference', 100)->nullable()->unique(); // pay.bazardev's reference_code - the webhook idempotency key
            $table->string('status', 20)->default('pending'); // pending | paid | failed | expired | manual_review | refunded (see a later migration for tran_id, our own id)
            $table->json('raw_payload')->nullable(); // the gateway's own response/webhook body, for dispute resolution
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
