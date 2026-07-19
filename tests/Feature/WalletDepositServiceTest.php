<?php

namespace Tests\Feature;

use App\Jobs\SendDepositSuccessMailJob;
use App\Models\Payment;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\PayPalClientFactory;
use App\Services\PayPalWalletServices;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Srmklive\PayPal\Services\PayPal as PayPalClient;
use Tests\TestCase;

class WalletDepositServiceTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('paypal.merchant_id', 'MERCHANT-123');
        config()->set('paypal.currency', 'USD');
        config()->set('wallet.points_per_usd', 1_000_000);
        Queue::fake();
    }

    public function test_completed_capture_credits_wallet_once_with_real_capture_id(): void
    {
        $payment = $this->payment();
        $service = $this->service();

        $completed = $service->finalizeCompletedWalletCapture($this->capture($payment));

        $this->assertSame(Payment::STATUS_COMPLETED, $completed->status);
        $this->assertTrue($completed->wallet_credited);
        $this->assertSame('CAPTURE-123', $completed->transaction_id);
        $this->assertSame(12_340_000, Wallet::where('user_id', $payment->user_id)->value('balance'));
        $this->assertDatabaseHas('wallet_transactions', [
            'payment_id' => $payment->id,
            'points' => 12_340_000,
            'balance_before' => 0,
            'balance_after' => 12_340_000,
        ]);
        Queue::assertPushed(SendDepositSuccessMailJob::class, 1);
    }

    public function test_duplicate_capture_does_not_credit_or_create_ledger_twice(): void
    {
        $payment = $this->payment();
        $service = $this->service();
        $payload = $this->capture($payment);

        $service->finalizeCompletedWalletCapture($payload);
        $service->finalizeCompletedWalletCapture($payload);

        $this->assertSame(12_340_000, Wallet::where('user_id', $payment->user_id)->value('balance'));
        $this->assertSame(1, WalletTransaction::where('payment_id', $payment->id)->count());
    }

    public function test_amount_currency_merchant_and_custom_id_mismatches_never_credit(): void
    {
        foreach (['amount', 'currency', 'merchant', 'custom'] as $case) {
            $payment = $this->payment(['idempotency_key' => hash('sha256', Str::uuid())]);
            $payload = $this->capture($payment, captureId: 'CAPTURE-'.$case);
            match ($case) {
                'amount' => $payload['amount']['value'] = '99.00',
                'currency' => $payload['amount']['currency_code'] = 'EUR',
                'merchant' => $payload['payee']['merchant_id'] = 'OTHER-MERCHANT',
                'custom' => $payload['custom_id'] = 'wallet_topup:999999',
            };

            try {
                $this->service()->finalizeCompletedWalletCapture($payload);
                $this->fail("{$case} mismatch was accepted.");
            } catch (RuntimeException) {
                $this->assertFalse($payment->fresh()->wallet_credited, $case);
                $this->assertDatabaseMissing('wallet_transactions', ['payment_id' => $payment->id]);
            }
        }
    }

    public function test_reference_id_mismatch_in_order_capture_response_never_credits(): void
    {
        $payment = $this->payment();
        $response = $this->orderCaptureResponse($payment);
        $response['purchase_units'][0]['reference_id'] = '99999';

        $this->expectException(RuntimeException::class);
        try {
            $this->service()->finalizeCompletedWalletCapture($response);
        } finally {
            $this->assertFalse($payment->fresh()->wallet_credited);
        }
    }

    public function test_capture_id_already_used_by_another_payment_is_rejected(): void
    {
        $other = $this->payment([
            'idempotency_key' => hash('sha256', 'other'),
            'paypal_order_id' => 'PAYPAL-OTHER',
            'transaction_id' => 'CAPTURE-123',
            'status' => Payment::STATUS_COMPLETED,
            'wallet_credited' => true,
        ]);
        $payment = $this->payment();

        $this->expectException(RuntimeException::class);
        $this->service()->finalizeCompletedWalletCapture($this->capture($payment));
        $this->assertTrue($other->fresh()->wallet_credited);
    }

    public function test_wallet_transaction_failure_rolls_back_wallet_and_payment(): void
    {
        $payment = $this->payment();
        WalletTransaction::creating(function () {
            throw new RuntimeException('ledger unavailable');
        });

        try {
            $this->service()->finalizeCompletedWalletCapture($this->capture($payment));
            $this->fail('Ledger failure was not propagated.');
        } catch (RuntimeException $e) {
            $this->assertSame('ledger unavailable', $e->getMessage());
            $this->assertFalse($payment->fresh()->wallet_credited);
            $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
            $this->assertDatabaseMissing('wallets', ['user_id' => $payment->user_id]);
        } finally {
            WalletTransaction::flushEventListeners();
        }
    }

    public function test_order_capture_response_extracts_nested_capture_id(): void
    {
        $payment = $this->payment();
        $completed = $this->service()->finalizeCompletedWalletCapture($this->orderCaptureResponse($payment));

        $this->assertSame('CAPTURE-123', $completed->transaction_id);
        $this->assertNotSame($payment->paypal_order_id, $completed->transaction_id);
    }

    public function test_declined_capture_marks_failed_without_credit(): void
    {
        $payment = $this->payment();
        $payload = $this->capture($payment, status: 'DECLINED');

        $this->service()->handleWebhook([
            'event_type' => 'PAYMENT.CAPTURE.DECLINED',
            'resource' => $payload,
        ]);

        $this->assertSame(Payment::STATUS_FAILED, $payment->fresh()->status);
        $this->assertFalse($payment->fresh()->wallet_credited);
        $this->assertDatabaseMissing('wallet_transactions', ['payment_id' => $payment->id]);
    }

    public function test_new_payment_and_pending_retry_reuse_same_paypal_order(): void
    {
        $provider = Mockery::mock(PayPalClient::class);
        $provider->shouldReceive('withIdempotencyKey')->once()->withArgs(fn ($key) => str_starts_with($key, 'wallet-create-'))->andReturnSelf();
        $provider->shouldReceive('createOrder')->once()->andReturn([
            'id' => 'PAYPAL-ORDER-1',
            'status' => 'CREATED',
            'links' => [['rel' => 'approve', 'href' => 'https://paypal.test/approve']],
        ]);
        $provider->shouldReceive('showOrderDetails')->once()->with('PAYPAL-ORDER-1')->andReturn([
            'id' => 'PAYPAL-ORDER-1',
            'status' => 'CREATED',
            'links' => [['rel' => 'approve', 'href' => 'https://paypal.test/approve']],
        ]);
        $service = $this->service($provider);
        $data = [
            'user_id' => User::factory()->create()->id,
            'amount' => '10.00',
            'idempotency_key' => (string) Str::uuid(),
            'locale' => 'en',
        ];

        $first = $service->pay($data);
        $second = $service->pay($data);

        $this->assertSame($first['order']->id, $second['order']->id);
        $this->assertSame('PAYPAL-ORDER-1', $second['order']->paypal_order_id);
        $this->assertSame('Wallet Deposit', $second['order']->description);
        $this->assertSame(1, Payment::where('user_id', $data['user_id'])->count());
    }

    public function test_approved_payment_is_reused_without_new_paypal_call(): void
    {
        $user = User::factory()->create();
        $key = (string) Str::uuid();
        $hash = hash('sha256', $user->id.'|'.$key);
        $payment = $this->payment([
            'user_id' => $user->id,
            'idempotency_key' => $hash,
            'status' => Payment::STATUS_APPROVED,
        ]);

        $result = $this->service()->pay([
            'user_id' => $user->id,
            'amount' => '12.34',
            'description' => 'Deposit',
            'idempotency_key' => $key,
            'locale' => 'en',
        ]);

        $this->assertSame($payment->id, $result['order']->id);
        $this->assertNull($result['approval_url']);
    }

    public function test_idempotency_key_with_different_amount_is_rejected(): void
    {
        $user = User::factory()->create();
        $key = (string) Str::uuid();
        $this->payment([
            'user_id' => $user->id,
            'idempotency_key' => hash('sha256', $user->id.'|'.$key),
        ]);

        $this->expectException(DomainException::class);
        $this->service()->pay([
            'user_id' => $user->id,
            'amount' => '13.34',
            'description' => 'Changed',
            'idempotency_key' => $key,
            'locale' => 'en',
        ]);
    }

    public function test_checkout_order_approved_captures_and_finalizes_once(): void
    {
        $payment = $this->payment();
        $approved = $this->approvedOrder($payment);
        $provider = Mockery::mock(PayPalClient::class);
        $provider->shouldReceive('withIdempotencyKey')->once()->with('wallet-capture-'.$payment->id)->andReturnSelf();
        $provider->shouldReceive('capturePaymentOrder')->once()->with($payment->paypal_order_id)
            ->andReturn($this->orderCaptureResponse($payment));

        $this->service($provider)->handleWebhook([
            'event_type' => 'CHECKOUT.ORDER.APPROVED',
            'resource' => $approved,
        ]);

        $this->assertTrue($payment->fresh()->wallet_credited);
        $this->assertSame('CAPTURE-123', $payment->fresh()->transaction_id);
    }

    public function test_semantic_duplicate_approved_event_does_not_capture_a_completed_payment_again(): void
    {
        $payment = $this->payment();
        $service = $this->service();
        $service->finalizeCompletedWalletCapture($this->capture($payment));

        $service->handleWebhook([
            'event_type' => 'CHECKOUT.ORDER.APPROVED',
            'resource' => $this->approvedOrder($payment),
        ]);

        $this->assertTrue($payment->fresh()->wallet_credited);
        $this->assertSame(1, WalletTransaction::where('payment_id', $payment->id)->count());
    }

    public function test_missing_merchant_configuration_fails_closed(): void
    {
        config()->set('paypal.merchant_id');
        $payment = $this->payment();

        $this->expectException(RuntimeException::class);
        try {
            $this->service()->finalizeCompletedWalletCapture($this->capture($payment));
        } finally {
            $this->assertFalse($payment->fresh()->wallet_credited);
        }
    }

    private function service(?PayPalClient $provider = null): PayPalWalletServices
    {
        $factory = Mockery::mock(PayPalClientFactory::class);
        if ($provider) {
            $factory->shouldReceive('make')->andReturn($provider);
        } else {
            $factory->shouldNotReceive('make');
        }

        return new PayPalWalletServices($factory);
    }

    private function payment(array $overrides = []): Payment
    {
        $userId = $overrides['user_id'] ?? User::factory()->create()->id;

        return Payment::create(array_merge([
            'user_id' => $userId,
            'payment_method' => 'paypal',
            'type' => 'wallet_deposit',
            'status' => Payment::STATUS_PENDING,
            'currency' => 'USD',
            'amount' => '12.34',
            'paypal_order_id' => 'PAYPAL-ORDER-'.Str::random(8),
            'description' => 'Wallet Deposit',
            'idempotency_key' => hash('sha256', Str::uuid()),
            'mail_sent' => false,
            'wallet_credited' => false,
        ], $overrides));
    }

    private function capture(Payment $payment, string $status = 'COMPLETED', string $captureId = 'CAPTURE-123'): array
    {
        return [
            'id' => $captureId,
            'status' => $status,
            'amount' => ['value' => '12.34', 'currency_code' => 'USD'],
            'custom_id' => 'wallet_topup:'.$payment->id,
            'payee' => ['merchant_id' => 'MERCHANT-123'],
            'supplementary_data' => ['related_ids' => ['order_id' => $payment->paypal_order_id]],
            'update_time' => '2026-07-19T10:00:00Z',
        ];
    }

    private function approvedOrder(Payment $payment): array
    {
        return [
            'id' => $payment->paypal_order_id,
            'status' => 'APPROVED',
            'purchase_units' => [[
                'reference_id' => (string) $payment->id,
                'custom_id' => 'wallet_topup:'.$payment->id,
                'amount' => ['value' => '12.34', 'currency_code' => 'USD'],
                'payee' => ['merchant_id' => 'MERCHANT-123'],
            ]],
        ];
    }

    private function orderCaptureResponse(Payment $payment): array
    {
        return [
            'id' => $payment->paypal_order_id,
            'status' => 'COMPLETED',
            'purchase_units' => [[
                'reference_id' => (string) $payment->id,
                'custom_id' => 'wallet_topup:'.$payment->id,
                'payee' => ['merchant_id' => 'MERCHANT-123'],
                'payments' => ['captures' => [[
                    'id' => 'CAPTURE-123',
                    'status' => 'COMPLETED',
                    'amount' => ['value' => '12.34', 'currency_code' => 'USD'],
                    'custom_id' => 'wallet_topup:'.$payment->id,
                    'payee' => ['merchant_id' => 'MERCHANT-123'],
                    'update_time' => '2026-07-19T10:00:00Z',
                ]]],
            ]],
        ];
    }
}
