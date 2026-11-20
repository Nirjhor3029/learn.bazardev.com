<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `tran_id` is OUR OWN id, generated here and sent to pay.bazardev on init -
     * required to look a transaction back up from its webhook (which carries
     * both tran_id and the gateway's own reference_code) without relying on
     * gateway_reference existing yet. Unique per our side, matching how
     * pay.bazardev scopes tran_id uniqueness per store+mode on theirs.
     */
    public function up(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->string('tran_id', 60)->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropColumn('tran_id');
        });
    }
};
