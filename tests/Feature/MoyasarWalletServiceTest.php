<?php

namespace Tests\Feature;

use App\Jobs\SendDepositFailedMailJob;
use App\Jobs\SendDepositSuccessMailJob;
use App\Models\Payment;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\MoyasarWalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class MoyasarWalletServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('moyasar.mode', 'test');
        config()->set('moyasar.test.secret_key', 'sk_test_unit');
        config()->set('moyasar.test.publishable_key', 'pk_test_unit');
        config()->set('moyasar.api_url', 'https://api.moyasar.com/v1');
        config()->set('moyasar.currency', 'SAR');
        config()->set('moyasar.merchant_id', 'MERCHANT-123');
        config()->set('moyasar.webhook_secret', 'webhook-secret');
        config()->set('moyasar.points_per_sar', 1_000_000);
        config()->set('moyasar.get_retries', 0);
        Queue::fake();
    }

    public function test_create_payment_reuses_local_payment_and_hosted_invoice(): void
    {
        $invoice = null;
        Http::fake(function (Request $request) use (&$invoice) {
            if ($request->method() === 'GET' && str_contains($request->url(), '/invoices?')) {
                return Http::response(['invoices' => []]);
            }

            if ($request->method() === 'POST' && str_ends_with($request->url(), '/invoices')) {
                $data = $request->data();
                $invoice = [
                    'id' => 'invoice_test_123',
                    'status' => 'initiated',
                    'amount' => $data['amount'],
                    'currency' => $data['currency'],
                    'url' => 'https://checkout.moyasar.com/invoices/invoice_test_123',
                    'metadata' => $data['metadata'],
                    'payments' => [],
                ];

                return Http::response($invoice, 201);
            }

            if ($request->method() === 'GET' && str_ends_with($request->url(), '/invoices/invoice_test_123')) {
                return Http::response($invoice);
            }

            return Http::response([], 404);
        });

        $user = User::factory()->create();
        $key = (string) Str::uuid();
        $payload = [
            'user_id' => $user->id,
            'amount' => '12.50',
            'description' => 'Wallet recharge',
            'idempotency_key' => $key,
            'locale' => 'ar',
        ];

        $first = app(MoyasarWalletService::class)->createPayment($payload);
        $second = app(MoyasarWalletService::class)->createPayment($payload);

        $this->assertSame($first['order']->id, $second['order']->id);
        $this->assertSame('invoice_test_123', $second['order']->moyasar_invoice_id);
        $this->assertSame(
            'https://checkout.moyasar.com/invoices/invoice_test_123',
            $second['payment_url']
        );
        $this->assertSame(1, Payment::where('user_id', $user->id)->count());
        Http::assertSentCount(3);
        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && str_ends_with($request->url(), '/invoices')
            && $request['amount'] === 1250
            && $request['metadata']['local_payment_id'] === (string) $first['order']->id);
    }

    public function test_verified_paid_webhook_credits_wallet_exactly_once(): void
    {
        $payment = $this->payment();
        [$remotePayment, $invoice] = $this->paidResources($payment);
        $this->fakeCanonicalResources($remotePayment, $invoice);
        $service = app(MoyasarWalletService::class);

        $first = $service->handleWebhook([
            'type' => 'payment_paid',
            'secret_token' => 'webhook-secret',
            'data' => $remotePayment,
        ]);
        $second = $service->handleWebhook([
            'type' => 'payment_paid',
            'secret_token' => 'webhook-secret',
            'data' => $remotePayment,
        ]);

        $this->assertSame(Payment::STATUS_COMPLETED, $first->status);
        $this->assertSame(Payment::STATUS_COMPLETED, $second->status);
        $this->assertTrue($payment->fresh()->wallet_credited);
        $this->assertSame('payment_test_123', $payment->fresh()->transaction_id);
        $this->assertSame(12_500_000, Wallet::where('user_id', $payment->user_id)->value('balance'));
        $this->assertSame(1, WalletTransaction::where('payment_id', $payment->id)->count());
        Queue::assertPushed(SendDepositSuccessMailJob::class, 1);
    }

    public function test_canonical_amount_mismatch_never_credits_wallet(): void
    {
        $payment = $this->payment();
        [$remotePayment, $invoice] = $this->paidResources($payment);
        $remotePayment['amount'] = 1300;
        $this->fakeCanonicalResources($remotePayment, $invoice);

        $this->expectException(RuntimeException::class);

        try {
            app(MoyasarWalletService::class)->handleWebhook([
                'type' => 'payment_paid',
                'secret_token' => 'webhook-secret',
                'data' => $remotePayment,
            ]);
        } finally {
            $this->assertFalse($payment->fresh()->wallet_credited);
            $this->assertDatabaseMissing('wallet_transactions', ['payment_id' => $payment->id]);
        }
    }

    public function test_failed_canonical_payment_marks_local_payment_failed_without_credit(): void
    {
        $payment = $this->payment();
        [$remotePayment, $invoice] = $this->paidResources($payment);
        $remotePayment['status'] = 'failed';
        $invoice['status'] = 'initiated';
        $invoice['payments'][0]['status'] = 'failed';
        $this->fakeCanonicalResources($remotePayment, $invoice);

        app(MoyasarWalletService::class)->handleWebhook([
            'type' => 'payment_failed',
            'secret_token' => 'webhook-secret',
            'data' => $remotePayment,
        ]);

        $this->assertSame(Payment::STATUS_FAILED, $payment->fresh()->status);
        $this->assertFalse($payment->fresh()->wallet_credited);
        $this->assertDatabaseMissing('wallet_transactions', ['payment_id' => $payment->id]);
        Queue::assertPushed(SendDepositFailedMailJob::class, 1);
    }

    public function test_webhook_secret_uses_fail_closed_constant_time_check(): void
    {
        $service = app(MoyasarWalletService::class);

        $this->assertTrue($service->verifyWebhookSignature(['secret_token' => 'webhook-secret']));
        $this->assertFalse($service->verifyWebhookSignature(['secret_token' => 'wrong']));
        $this->assertFalse($service->verifyWebhookSignature([]));

        config()->set('moyasar.webhook_secret', '');
        $this->expectException(RuntimeException::class);
        $service->verifyWebhookSignature(['secret_token' => 'anything']);
    }

    private function payment(array $overrides = []): Payment
    {
        return Payment::create(array_merge([
            'user_id' => User::factory()->create()->id,
            'payment_method' => 'moyasar',
            'type' => 'wallet_deposit',
            'status' => Payment::STATUS_PENDING,
            'payment_status' => 'initiated',
            'currency' => 'SAR',
            'amount' => '12.50',
            'moyasar_invoice_id' => 'invoice_test_123',
            'description' => 'Wallet Deposit',
            'idempotency_key' => hash('sha256', Str::uuid()),
            'mail_sent' => false,
            'wallet_credited' => false,
        ], $overrides));
    }

    private function paidResources(Payment $payment): array
    {
        $metadata = [
            'local_payment_id' => (string) $payment->id,
            'payment_type' => 'wallet_deposit',
            'merchant_id' => 'MERCHANT-123',
            'idempotency_key' => $payment->idempotency_key,
        ];
        $remotePayment = [
            'id' => 'payment_test_123',
            'status' => 'paid',
            'amount' => 1250,
            'currency' => 'SAR',
            'invoice_id' => $payment->moyasar_invoice_id,
            'metadata' => $metadata,
            'updated_at' => '2026-07-25T10:00:00Z',
            'source' => ['type' => 'creditcard', 'token' => 'must-not-be-stored'],
        ];
        $invoice = [
            'id' => $payment->moyasar_invoice_id,
            'status' => 'paid',
            'amount' => 1250,
            'currency' => 'SAR',
            'url' => 'https://checkout.moyasar.com/invoices/'.$payment->moyasar_invoice_id,
            'metadata' => $metadata,
            'payments' => [$remotePayment],
        ];

        return [$remotePayment, $invoice];
    }

    private function fakeCanonicalResources(array $remotePayment, array $invoice): void
    {
        Http::fake([
            'https://api.moyasar.com/v1/payments/*' => Http::response($remotePayment),
            'https://api.moyasar.com/v1/invoices/*' => Http::response($invoice),
        ]);
    }
}
