<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Services\PayPalWalletServices;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReconcilePayPalWalletPayments extends Command
{
    protected $signature = 'payments:reconcile-paypal-wallet
        {--minutes= : Minimum payment age in minutes}
        {--limit=100 : Maximum payments to inspect}';

    protected $description = 'Reconcile old pending/approved PayPal wallet deposits without relying on browser redirects.';

    public function handle(PayPalWalletServices $paypal): int
    {
        $minutes = max(1, (int) ($this->option('minutes')
            ?? config('paypal.reconciliation_age_minutes', 10)));
        $limit = max(1, min(1000, (int) $this->option('limit')));

        $payments = Payment::query()
            ->where('type', 'wallet_deposit')
            ->whereIn('status', [Payment::STATUS_PENDING, Payment::STATUS_APPROVED])
            ->where('wallet_credited', false)
            ->whereNotNull('paypal_order_id')
            ->where('created_at', '<=', now()->subMinutes($minutes))
            ->oldest('id')
            ->limit($limit)
            ->get();

        Log::info('reconciliation_started', ['payments' => $payments->count()]);
        $completed = 0;
        $failed = 0;

        foreach ($payments as $payment) {
            try {
                if ($paypal->reconcile($payment) === 'completed') {
                    $completed++;
                }
            } catch (Throwable $e) {
                $failed++;
                Log::error('reconciliation_payment_failed', [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('reconciliation_completed', [
            'inspected' => $payments->count(),
            'completed' => $completed,
            'failed' => $failed,
        ]);
        $this->info("Inspected {$payments->count()}, completed {$completed}, failed {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
