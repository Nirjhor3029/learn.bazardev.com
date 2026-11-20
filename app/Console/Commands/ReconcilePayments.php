<?php

namespace App\Console\Commands;

use App\Http\Controllers\PaymentWebhookController;
use App\Models\PaymentTransaction;
use App\Services\Payments\PaymentGateway;
use Illuminate\Console\Command;

/**
 * Polls pay.bazardev directly for any payment still `pending` on our side
 * after a while. Exists because pay.bazardev does not webhook `expired`,
 * and essentially never webhooks `failed` outside its sandbox simulator
 * (see PayBazarDevGateway's docblock) - without this, a transaction a
 * customer abandoned would stay "pending" forever.
 */
class ReconcilePayments extends Command
{
    protected $signature = 'app:reconcile-payments {--minutes=20 : only check transactions older than this}';

    protected $description = 'Poll pay.bazardev for any pending transaction the webhook never resolved (expired/failed/manual_review).';

    public function handle(PaymentGateway $gateway, PaymentWebhookController $webhooks): int
    {
        $stale = PaymentTransaction::query()
            ->where('status', PaymentTransaction::PENDING)
            ->where('created_at', '<=', now()->subMinutes((int) $this->option('minutes')))
            ->get();

        $this->info("Checking {$stale->count()} stale pending transaction(s).");

        foreach ($stale as $transaction) {
            try {
                $status = $gateway->check($transaction->tran_id);
            } catch (\Throwable $e) {
                $this->warn("Could not check {$transaction->tran_id}: {$e->getMessage()}");

                continue;
            }

            if ($status->status !== PaymentTransaction::PENDING) {
                $webhooks->apply($transaction, $status->status, json_encode($status));
                $this->line("{$transaction->tran_id}: pending -> {$status->status}");
            }
        }

        return self::SUCCESS;
    }
}
