<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MoyasarWalletEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        putenv('API_KEY=testing-api-key');
        $_ENV['API_KEY'] = 'testing-api-key';
        $_SERVER['API_KEY'] = 'testing-api-key';
    }

    public function test_create_requires_authentication_and_valid_payload(): void
    {
        $payload = [
            'amount' => '10.001',
            'idempotency_key' => 'not-a-uuid',
            'locale' => 'en',
        ];

        $this->apiPost('/api/v1/moyasar/pay', $payload)->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->apiPost('/api/v1/moyasar/pay', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount', 'idempotency_key']);
    }

    public function test_status_returns_only_owned_moyasar_payment_fields(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $payment = $this->payment($owner);

        Sanctum::actingAs($owner);
        $this->withHeaders(['X-API-KEY' => 'testing-api-key'])
            ->getJson('/api/v1/wallet/moyasar-status/'.$payment->id)
            ->assertOk()
            ->assertExactJson([
                'order_id' => $payment->id,
                'status' => 'pending',
                'amount' => '10.00',
                'currency' => 'SAR',
                'paid_at' => null,
            ])
            ->assertJsonMissingPath('gateway_response')
            ->assertJsonMissingPath('moyasar_invoice_id');

        Sanctum::actingAs($attacker);
        $this->withHeaders(['X-API-KEY' => 'testing-api-key'])
            ->getJson('/api/v1/wallet/moyasar-status/'.$payment->id)
            ->assertNotFound()
            ->assertExactJson(['status' => 'not_found']);
    }

    private function apiPost(string $uri, array $payload)
    {
        return $this->withHeaders(['X-API-KEY' => 'testing-api-key'])->postJson($uri, $payload);
    }

    private function payment(User $user): Payment
    {
        return Payment::create([
            'user_id' => $user->id,
            'payment_method' => 'moyasar',
            'type' => 'wallet_deposit',
            'status' => Payment::STATUS_PENDING,
            'payment_status' => 'initiated',
            'currency' => 'SAR',
            'amount' => '10.00',
            'moyasar_invoice_id' => 'invoice_endpoint_123',
            'description' => 'Wallet Deposit',
            'idempotency_key' => hash('sha256', Str::uuid()),
            'wallet_credited' => false,
        ]);
    }
}
