<?php

namespace Tests\Feature;

use App\Models\MoyasarWebhookEvent;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class MoyasarWalletWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('moyasar.mode', 'test');
        config()->set('moyasar.test.secret_key', 'sk_test_unit');
        config()->set('moyasar.api_url', 'https://api.moyasar.com/v1');
        config()->set('moyasar.currency', 'SAR');
        config()->set('moyasar.merchant_id', 'MERCHANT-123');
        config()->set('moyasar.webhook_secret', 'webhook-secret');
        config()->set('services.moyasar.webhook_secret', 'webhook-secret');
        config()->set('moyasar.points_per_sar', 1_000_000);
        config()->set('moyasar.get_retries', 0);
        Queue::fake();
    }

    public function test_verified_webhook_is_processed_once_and_secret_is_not_persisted(): void
    {
        $payment = $this->payment();
        [$remotePayment, $invoice] = $this->paidResources($payment);
        Http::fake([
            'https://api.moyasar.com/v1/payments/*' => Http::response($remotePayment),
            'https://api.moyasar.com/v1/invoices/*' => Http::response($invoice),
        ]);
        $payload = [
            'id' => 'evt_paid_123',
            'type' => 'payment_paid',
            'secret_token' => 'webhook-secret',
            'live' => false,
            'data' => $remotePayment,
        ];

        $this->postJson('/api/v1/moyasar/webhook', $payload)
            ->assertOk()
            ->assertExactJson(['status' => 'ok']);
        $this->postJson('/api/v1/moyasar/webhook', $payload)
            ->assertOk()
            ->assertExactJson(['status' => 'duplicate']);

        $event = MoyasarWebhookEvent::sole();
        $this->assertSame(MoyasarWebhookEvent::STATUS_PROCESSED, $event->status);
        $this->assertSame($payment->id, $event->local_payment_id);
        $this->assertArrayNotHasKey('secret_token', $event->payload);
        $this->assertTrue($payment->fresh()->wallet_credited);
        Http::assertSentCount(2);
    }

    public function test_invalid_webhook_secret_is_rejected_before_storage_or_api_call(): void
    {
        Http::fake();

        $this->postJson('/api/v1/moyasar/webhook', [
            'type' => 'payment_paid',
            'secret_token' => 'wrong',
            'live' => false,
            'data' => ['id' => 'payment_test_123', 'status' => 'paid'],
        ])->assertUnauthorized()->assertJson(['error' => 'invalid_signature']);

        $this->assertSame(0, MoyasarWebhookEvent::count());
        Http::assertNothingSent();
    }

    public function test_missing_webhook_secret_configuration_fails_closed(): void
    {
        config()->set('moyasar.webhook_secret', '');
        config()->set('services.moyasar.webhook_secret', '');

        $this->postJson('/api/v1/moyasar/webhook', [
            'type' => 'payment_paid',
            'secret_token' => 'anything',
            'live' => false,
            'data' => ['id' => 'payment_test_123', 'status' => 'paid'],
        ])->assertStatus(500)->assertJson(['error' => 'webhook_not_configured']);

        $this->assertSame(0, MoyasarWebhookEvent::count());
    }

    public function test_test_mode_rejects_live_webhook_event(): void
    {
        Http::fake();

        $this->postJson('/api/v1/moyasar/webhook', [
            'id' => 'evt_live_mismatch',
            'type' => 'payment_paid',
            'secret_token' => 'webhook-secret',
            'live' => true,
            'data' => ['id' => 'payment_test_123', 'status' => 'paid'],
        ])->assertUnprocessable()->assertJson(['error' => 'mode_mismatch']);

        $this->assertSame(0, MoyasarWebhookEvent::count());
        Http::assertNothingSent();
    }

    public function test_unsupported_webhook_event_is_rejected_before_storage_or_api_call(): void
    {
        Http::fake();

        $this->postJson('/api/v1/moyasar/webhook', [
            'id' => 'evt_unknown',
            'type' => 'customer_created',
            'secret_token' => 'webhook-secret',
            'live' => false,
            'data' => ['id' => 'payment_test_123', 'status' => 'paid'],
        ])->assertUnprocessable()->assertJson(['error' => 'unsupported_event']);

        $this->assertSame(0, MoyasarWebhookEvent::count());
        Http::assertNothingSent();
    }

    private function payment(): Payment
    {
        return Payment::create([
            'user_id' => User::factory()->create()->id,
            'payment_method' => 'moyasar',
            'type' => 'wallet_deposit',
            'status' => Payment::STATUS_PENDING,
            'payment_status' => 'initiated',
            'currency' => 'SAR',
            'amount' => '10.00',
            'moyasar_invoice_id' => 'invoice_test_webhook',
            'description' => 'Wallet Deposit',
            'idempotency_key' => hash('sha256', Str::uuid()),
            'mail_sent' => false,
            'wallet_credited' => false,
        ]);
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
            'id' => 'payment_test_webhook',
            'status' => 'paid',
            'amount' => 1000,
            'currency' => 'SAR',
            'invoice_id' => $payment->moyasar_invoice_id,
            'metadata' => $metadata,
            'updated_at' => '2026-07-25T10:00:00Z',
        ];
        $invoice = [
            'id' => $payment->moyasar_invoice_id,
            'status' => 'paid',
            'amount' => 1000,
            'currency' => 'SAR',
            'metadata' => $metadata,
            'payments' => [$remotePayment],
        ];

        return [$remotePayment, $invoice];
    }
}
