<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Services\MoyasarWalletService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReconcileMoyasarWalletPayments extends Command
{
    protected $signature = 'payments:reconcile-moyasar-wallet
        {--minutes= : Minimum payment age in minutes}
        {--limit=100 : Maximum payments to inspect}';

    protected $description = 'Reconcile old Moyasar wallet deposits using the server-side Moyasar API.';

    public function handle(MoyasarWalletService $moyasar): int
    {
        $minutes = max(1, (int) ($this->option('minutes')
            ?? config('moyasar.reconciliation_age_minutes', 10)));
        $maxAgeHours = max(1, (int) config('moyasar.reconciliation_max_age_hours', 72));
        $limit = max(1, min(1000, (int) $this->option('limit')));

        $payments = Payment::query()
            ->where('payment_method', 'moyasar')
            ->where('type', 'wallet_deposit')
            ->whereIn('status', [Payment::STATUS_PENDING, Payment::STATUS_APPROVED])
            ->where('wallet_credited', false)
            ->whereNotNull('moyasar_invoice_id')
            ->whereBetween('created_at', [
                now()->subHours($maxAgeHours),
                now()->subMinutes($minutes),
            ])
            ->oldest('id')
            ->limit($limit)
            ->get();

        Log::info('moyasar_reconciliation_started', ['payments' => $payments->count()]);
        $completed = 0;
        $failed = 0;

        foreach ($payments as $payment) {
            try {
                if ($moyasar->reconcile($payment) === 'completed') {
                    $completed++;
                }
            } catch (Throwable $e) {
                $failed++;
                Log::error('moyasar_reconciliation_payment_failed', [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('moyasar_reconciliation_completed', [
            'inspected' => $payments->count(),
            'completed' => $completed,
            'failed' => $failed,
        ]);
        $this->info("Inspected {$payments->count()}, completed {$completed}, failed {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
