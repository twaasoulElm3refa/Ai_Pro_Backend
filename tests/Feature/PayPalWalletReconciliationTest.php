<?php

namespace Tests\Feature;

use App\Jobs\SendDepositSuccessMailJob;
use App\Models\Payment;
use App\Models\User;
use App\Models\Wallet;
use App\Services\PayPalClientFactory;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Mockery;
use Srmklive\PayPal\Services\PayPal as PayPalClient;
use Tests\TestCase;

class PayPalWalletReconciliationTest extends TestCase
{
    use DatabaseMigrations;

    public function test_reconciliation_completes_an_old_payment_without_browser_redirect(): void
    {
        config()->set('paypal.currency', 'USD');
        config()->set('paypal.merchant_id', 'MERCHANT-123');
        config()->set('wallet.points_per_usd', 1_000_000);
        Queue::fake();

        $payment = Payment::create([
            'user_id' => User::factory()->create()->id,
            'payment_method' => 'paypal',
            'type' => 'wallet_deposit',
            'status' => Payment::STATUS_APPROVED,
            'currency' => 'USD',
            'amount' => '5.25',
            'paypal_order_id' => 'PAYPAL-RECONCILE-1',
            'description' => 'Wallet Deposit',
            'idempotency_key' => hash('sha256', Str::uuid()),
            'mail_sent' => false,
            'wallet_credited' => false,
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ]);

        $provider = Mockery::mock(PayPalClient::class);
        $provider->shouldReceive('showOrderDetails')
            ->once()
            ->with('PAYPAL-RECONCILE-1')
            ->andReturn($this->completedOrder($payment));
        $factory = Mockery::mock(PayPalClientFactory::class);
        $factory->shouldReceive('make')->once()->andReturn($provider);
        $this->app->instance(PayPalClientFactory::class, $factory);

        $this->artisan('payments:reconcile-paypal-wallet', ['--minutes' => 10])
            ->expectsOutput('Inspected 1, completed 1, failed 0.')
            ->assertSuccessful();

        $this->assertTrue($payment->fresh()->wallet_credited);
        $this->assertSame('CAPTURE-RECONCILE-1', $payment->fresh()->transaction_id);
        $this->assertSame(5_250_000, Wallet::where('user_id', $payment->user_id)->value('balance'));
        Queue::assertPushed(SendDepositSuccessMailJob::class, 1);
    }

    private function completedOrder(Payment $payment): array
    {
        return [
            'id' => $payment->paypal_order_id,
            'status' => 'COMPLETED',
            'purchase_units' => [[
                'reference_id' => (string) $payment->id,
                'custom_id' => 'wallet_topup:'.$payment->id,
                'payee' => ['merchant_id' => 'MERCHANT-123'],
                'payments' => ['captures' => [[
                    'id' => 'CAPTURE-RECONCILE-1',
                    'status' => 'COMPLETED',
                    'amount' => ['value' => '5.25', 'currency_code' => 'USD'],
                    'custom_id' => 'wallet_topup:'.$payment->id,
                    'payee' => ['merchant_id' => 'MERCHANT-123'],
                    'update_time' => '2026-07-19T10:00:00Z',
                ]]],
            ]],
        ];
    }
}
