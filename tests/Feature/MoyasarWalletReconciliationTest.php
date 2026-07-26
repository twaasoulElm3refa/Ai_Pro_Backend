<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class MoyasarWalletReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconciliation_completes_paid_invoice_without_browser_return(): void
    {
        config()->set('moyasar.mode', 'test');
        config()->set('moyasar.test.secret_key', 'sk_test_unit');
        config()->set('moyasar.api_url', 'https://api.moyasar.com/v1');
        config()->set('moyasar.currency', 'SAR');
        config()->set('moyasar.merchant_id', 'MERCHANT-123');
        config()->set('moyasar.points_per_sar', 1_000_000);
        config()->set('moyasar.get_retries', 0);
        Queue::fake();

        $payment = Payment::create([
            'user_id' => User::factory()->create()->id,
            'payment_method' => 'moyasar',
            'type' => 'wallet_deposit',
            'status' => Payment::STATUS_PENDING,
            'payment_status' => 'initiated',
            'currency' => 'SAR',
            'amount' => '5.25',
            'moyasar_invoice_id' => 'invoice_reconcile_123',
            'description' => 'Wallet Deposit',
            'idempotency_key' => hash('sha256', Str::uuid()),
            'mail_sent' => false,
            'wallet_credited' => false,
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ]);
        $metadata = [
            'local_payment_id' => (string) $payment->id,
            'payment_type' => 'wallet_deposit',
            'merchant_id' => 'MERCHANT-123',
            'idempotency_key' => $payment->idempotency_key,
        ];
        $remotePayment = [
            'id' => 'payment_reconcile_123',
            'status' => 'paid',
            'amount' => 525,
            'currency' => 'SAR',
            'invoice_id' => $payment->moyasar_invoice_id,
            'metadata' => $metadata,
            'updated_at' => '2026-07-25T10:00:00Z',
        ];
        $invoice = [
            'id' => $payment->moyasar_invoice_id,
            'status' => 'paid',
            'amount' => 525,
            'currency' => 'SAR',
            'metadata' => $metadata,
            'payments' => [$remotePayment],
        ];
        Http::fake([
            'https://api.moyasar.com/v1/invoices/*' => Http::response($invoice),
            'https://api.moyasar.com/v1/payments/*' => Http::response($remotePayment),
        ]);

        $this->artisan('payments:reconcile-moyasar-wallet', ['--minutes' => 10])
            ->expectsOutput('Inspected 1, completed 1, failed 0.')
            ->assertSuccessful();

        $this->assertTrue($payment->fresh()->wallet_credited);
        $this->assertSame(Payment::STATUS_COMPLETED, $payment->fresh()->status);
        $this->assertSame('payment_reconcile_123', $payment->fresh()->transaction_id);
        $this->assertSame(5_250_000, Wallet::where('user_id', $payment->user_id)->value('balance'));
    }
}
