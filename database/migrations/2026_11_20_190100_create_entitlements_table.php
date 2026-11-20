<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The one table every access decision goes through. One row = one grant of
     * access to a course, a course's terminal add-on, or the all-access
     * subscription — however it came to exist (real purchase, an admin's manual
     * grant, or a course the admin marked free). Never hard-deleted: a refund or
     * revocation sets `revoked_at` so the audit trail (and any certificate
     * already earned under it) survives.
     */
    public function up(): void
    {
        Schema::create('entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('product_type', 30); // course | terminal | subscription_all_access
            $table->string('product_key', 60)->nullable(); // course slug for course/terminal; null for subscription_all_access
            $table->string('source', 20); // purchase | admin_grant | free_course
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('grant_note')->nullable(); // why - required for admin_grant, for the audit trail
            $table->foreignId('payment_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('starts_at')->useCurrent();
            $table->timestamp('expires_at')->nullable(); // null = permanent (purchase, admin grant); set = subscription period end
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'product_type', 'product_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entitlements');
    }
};
