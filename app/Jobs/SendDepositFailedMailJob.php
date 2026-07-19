<?php

namespace App\Jobs;

use App\Mail\DepositFailMail;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendDepositFailedMailJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $uniqueFor = 7200;
    public array $backoff = [60, 300, 900, 3600];

    public function __construct(public readonly int $paymentId) {}

    public function uniqueId(): string
    {
        return 'wallet-deposit-failed:'.$this->paymentId;
    }

    public function handle(Mailer $mailer): void
    {
        $payment = Payment::with('user')->findOrFail($this->paymentId);
        if ($payment->status !== Payment::STATUS_FAILED || $payment->mail_sent) {
            return;
        }

        try {
            (new DepositFailMail($payment->amount, $payment->user->name))
                ->to($payment->user->email)
                ->send($mailer);
            $payment->update(['mail_sent' => true]);
        } catch (Throwable $e) {
            Log::error('email_failed', [
                'payment_id' => $payment->id,
                'kind' => 'deposit_failed',
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
