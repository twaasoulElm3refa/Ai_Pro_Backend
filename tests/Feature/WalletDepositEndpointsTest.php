<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WalletDepositEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        putenv('API_KEY=testing-api-key');
        $_ENV['API_KEY'] = 'testing-api-key';
        $_SERVER['API_KEY'] = 'testing-api-key';
    }

    public function test_create_requires_authentication_and_valid_uuid(): void
    {
        $this->apiPost('/api/v1/deposit/pay', [
            'amount' => '10.00',
            'idempotency_key' => 'not-a-uuid',
            'locale' => 'en',
        ])->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->apiPost('/api/v1/deposit/pay', [
            'amount' => '10.001',
            'idempotency_key' => 'not-a-uuid',
            'locale' => 'en',
        ])->assertUnprocessable()->assertJsonValidationErrors(['amount', 'idempotency_key']);
    }

    public function test_order_status_returns_only_owned_non_sensitive_fields(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $payment = $this->payment($user);

        $response = $this->withHeaders(['X-API-KEY' => 'testing-api-key'])
            ->getJson('/api/v1/wallet/order-status/'.$payment->id);

        $response->assertOk()->assertExactJson([
            'order_id' => $payment->id,
            'status' => 'pending',
            'amount' => '10.00',
            'currency' => 'USD',
            'paid_at' => null,
        ]);
        $response->assertJsonMissingPath('gateway_response');
        $response->assertJsonMissingPath('paypal_order_id');
    }

    public function test_order_status_prevents_idor(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $payment = $this->payment($owner);
        Sanctum::actingAs($attacker);

        $this->withHeaders(['X-API-KEY' => 'testing-api-key'])
            ->getJson('/api/v1/wallet/order-status/'.$payment->id)
            ->assertNotFound()
            ->assertExactJson(['status' => 'not_found']);
    }

    public function test_success_redirect_only_marks_approved_and_never_credits(): void
    {
        $user = User::factory()->create();
        $payment = $this->payment($user);

        $this->get('/api/v1/wallet/success?token='.$payment->paypal_order_id.'&lang=ar')
            ->assertRedirect('/ar/Deposit/waiting?order_id='.$payment->id);

        $payment->refresh();
        $this->assertSame(Payment::STATUS_APPROVED, $payment->status);
        $this->assertFalse($payment->wallet_credited);
        $this->assertDatabaseMissing('wallet_transactions', ['payment_id' => $payment->id]);
    }

    public function test_webhook_completion_before_redirect_remains_completed_after_redirect(): void
    {
        $user = User::factory()->create();
        $payment = $this->payment($user, [
            'status' => Payment::STATUS_COMPLETED,
            'wallet_credited' => true,
            'transaction_id' => 'CAPTURE-DONE',
            'paid_at' => now(),
        ]);

        $this->get('/api/v1/wallet/success?token='.$payment->paypal_order_id.'&lang=en')
            ->assertRedirect('/en/Deposit/waiting?order_id='.$payment->id);

        $this->assertSame(Payment::STATUS_COMPLETED, $payment->fresh()->status);
        $this->assertTrue($payment->fresh()->wallet_credited);
    }

    public function test_cancel_redirects_to_cancel_page(): void
    {
        $this->get('/api/v1/wallet/cancel?lang=ar')
            ->assertRedirect('/ar/deposit/cancel');
    }

    private function apiPost(string $uri, array $payload)
    {
        return $this->withHeaders(['X-API-KEY' => 'testing-api-key'])->postJson($uri, $payload);
    }

    private function payment(User $user, array $overrides = []): Payment
    {
        return Payment::create(array_merge([
            'user_id' => $user->id,
            'payment_method' => 'paypal',
            'type' => 'wallet_deposit',
            'status' => Payment::STATUS_PENDING,
            'currency' => 'USD',
            'amount' => '10.00',
            'paypal_order_id' => 'PAYPAL-'.Str::random(10),
            'description' => 'Wallet Deposit',
            'idempotency_key' => hash('sha256', Str::uuid()),
            'gateway_response' => ['safe' => 'stored as json'],
        ], $overrides));
    }
}
