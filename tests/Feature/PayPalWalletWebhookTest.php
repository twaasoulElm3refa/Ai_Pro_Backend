<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\PaypalWebhookEvent;
use App\Models\User;
use App\Services\PayPalClientFactory;
use App\Services\PayPalWalletServices;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Srmklive\PayPal\Services\PayPal as PayPalClient;
use Tests\TestCase;

class PayPalWalletWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('paypal.verify_webhook', true);
        config()->set('paypal.webhook_id', 'WEBHOOK-CONFIGURED');
    }

    public function test_verified_webhook_is_processed_once_and_duplicate_returns_200(): void
    {
        $payment = $this->payment();
        $event = $this->completedEvent($payment);
        $provider = Mockery::mock(PayPalClient::class);
        $provider->shouldReceive('verifyWebHook')->twice()->andReturn(['verification_status' => 'SUCCESS']);
        $factory = Mockery::mock(PayPalClientFactory::class);
        $factory->shouldReceive('make')->twice()->andReturn($provider);
        $this->app->instance(PayPalClientFactory::class, $factory);
        $wallet = Mockery::mock(PayPalWalletServices::class);
        $wallet->shouldReceive('handleWebhook')->once()->with($event);
        $this->app->instance(PayPalWalletServices::class, $wallet);

        $this->withHeaders($this->signatureHeaders())->postJson('/api/v1/paypal/webhook', $event)
            ->assertOk()->assertJson(['status' => 'ok']);
        $this->withHeaders($this->signatureHeaders())->postJson('/api/v1/paypal/webhook', $event)
            ->assertOk()->assertJson(['status' => 'duplicate']);

        $this->assertSame(1, PaypalWebhookEvent::where('event_id', $event['id'])->count());
        $this->assertSame(PaypalWebhookEvent::STATUS_PROCESSED, PaypalWebhookEvent::first()->status);
    }

    public function test_invalid_signature_is_rejected_without_record_or_processing(): void
    {
        $payment = $this->payment();
        $provider = Mockery::mock(PayPalClient::class);
        $provider->shouldReceive('verifyWebHook')->once()->andReturn(['verification_status' => 'FAILURE']);
        $factory = Mockery::mock(PayPalClientFactory::class);
        $factory->shouldReceive('make')->once()->andReturn($provider);
        $this->app->instance(PayPalClientFactory::class, $factory);
        $wallet = Mockery::mock(PayPalWalletServices::class);
        $wallet->shouldNotReceive('handleWebhook');
        $this->app->instance(PayPalWalletServices::class, $wallet);

        $this->withHeaders($this->signatureHeaders())
            ->postJson('/api/v1/paypal/webhook', $this->completedEvent($payment))
            ->assertBadRequest()->assertJson(['error' => 'invalid_signature']);
        $this->assertSame(0, PaypalWebhookEvent::count());
    }

    public function test_missing_signature_headers_are_rejected_in_sandbox_and_live_modes(): void
    {
        foreach (['sandbox', 'live'] as $mode) {
            config()->set('paypal.mode', $mode);
            $this->postJson('/api/v1/paypal/webhook', $this->completedEvent($this->payment([
                'idempotency_key' => hash('sha256', $mode),
                'paypal_order_id' => 'ORDER-'.$mode,
            ])))->assertBadRequest()->assertJson(['error' => 'missing_signature_headers']);
        }
    }

    public function test_local_explicit_verification_flag_can_bypass_but_default_cannot(): void
    {
        config()->set('paypal.verify_webhook', false);
        $payment = $this->payment();
        $event = $this->completedEvent($payment);
        $wallet = Mockery::mock(PayPalWalletServices::class);
        $wallet->shouldReceive('handleWebhook')->once();
        $this->app->instance(PayPalWalletServices::class, $wallet);

        $this->postJson('/api/v1/paypal/webhook', $event)->assertOk();
    }

    public function test_verification_cannot_be_disabled_outside_local_or_testing(): void
    {
        config()->set('paypal.verify_webhook', false);
        $this->app['env'] = 'production';

        $this->postJson('/api/v1/paypal/webhook', $this->completedEvent($this->payment()))
            ->assertStatus(500)
            ->assertJson(['error' => 'unsafe_webhook_configuration']);

        $this->assertSame(0, PaypalWebhookEvent::count());
    }

    public function test_missing_webhook_id_fails_closed(): void
    {
        config()->set('paypal.webhook_id');

        $this->withHeaders($this->signatureHeaders())
            ->postJson('/api/v1/paypal/webhook', $this->completedEvent($this->payment()))
            ->assertStatus(500)
            ->assertJson(['error' => 'webhook_not_configured']);

        $this->assertSame(0, PaypalWebhookEvent::count());
    }

    public function test_payment_not_found_records_failure_and_returns_retryable_status(): void
    {
        config()->set('paypal.verify_webhook', false);
        $event = [
            'id' => 'EVENT-MISSING',
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => [
                'id' => 'CAPTURE-MISSING',
                'supplementary_data' => ['related_ids' => ['order_id' => 'ORDER-MISSING']],
            ],
        ];

        $this->postJson('/api/v1/paypal/webhook', $event)->assertStatus(503);
        $this->assertDatabaseHas('paypal_webhook_events', [
            'event_id' => 'EVENT-MISSING',
            'status' => PaypalWebhookEvent::STATUS_FAILED,
        ]);
    }

    private function payment(array $overrides = []): Payment
    {
        return Payment::create(array_merge([
            'user_id' => User::factory()->create()->id,
            'payment_method' => 'paypal',
            'type' => 'wallet_deposit',
            'status' => Payment::STATUS_PENDING,
            'currency' => 'USD',
            'amount' => '10.00',
            'paypal_order_id' => 'ORDER-'.Str::random(8),
            'idempotency_key' => hash('sha256', Str::uuid()),
        ], $overrides));
    }

    private function completedEvent(Payment $payment): array
    {
        return [
            'id' => 'EVENT-'.Str::random(10),
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => [
                'id' => 'CAPTURE-'.Str::random(8),
                'status' => 'COMPLETED',
                'supplementary_data' => ['related_ids' => ['order_id' => $payment->paypal_order_id]],
            ],
        ];
    }

    private function signatureHeaders(): array
    {
        return [
            'PAYPAL-TRANSMISSION-ID' => 'transmission-id',
            'PAYPAL-TRANSMISSION-TIME' => '2026-07-19T10:00:00Z',
            'PAYPAL-CERT-URL' => 'https://api.paypal.com/cert.pem',
            'PAYPAL-AUTH-ALGO' => 'SHA256withRSA',
            'PAYPAL-TRANSMISSION-SIG' => 'signature',
        ];
    }
}
