<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
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
        config()->set('moyasar.mode', 'test');
        config()->set('moyasar.test.secret_key', 'sk_test_unit');
        config()->set('moyasar.api_url', 'https://api.moyasar.com/v1');
        config()->set('moyasar.currency', 'SAR');
        config()->set('moyasar.merchant_id', 'MERCHANT-123');
        config()->set('moyasar.points_per_sar', 1_000_000);
        config()->set('moyasar.get_retries', 0);
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

    public function test_create_moyasar_payment_returns_hosted_redirect_contract(): void
    {
        Http::fake(function (Request $request) {
            if ($request->method() === 'GET' && str_contains($request->url(), '/invoices?')) {
                return Http::response(['invoices' => []]);
            }

            if ($request->method() === 'POST' && str_ends_with($request->url(), '/invoices')) {
                $data = $request->data();

                return Http::response([
                    'id' => 'invoice_endpoint_create',
                    'status' => 'initiated',
                    'amount' => $data['amount'],
                    'currency' => $data['currency'],
                    'url' => 'https://checkout.moyasar.com/invoices/invoice_endpoint_create',
                    'metadata' => $data['metadata'],
                    'payments' => [],
                ], 201);
            }

            return Http::response([], 404);
        });

        Sanctum::actingAs(User::factory()->create());
        $key = (string) Str::uuid();

        $response = $this->apiPost('/api/v1/moyasar/pay', [
            'amount' => '10.00',
            'description' => 'Wallet Deposit',
            'idempotency_key' => $key,
            'locale' => 'ar',
        ])->assertOk();

        $response
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.redirect_url', 'https://checkout.moyasar.com/invoices/invoice_endpoint_create')
            ->assertJsonPath('data.status', 'initiated')
            ->assertJsonPath('data.currency', 'SAR')
            ->assertJsonPath('data.amount_minor', 1000)
            ->assertJsonPath('data.points', 10_000_000);

        $this->assertSame(1, Payment::where('payment_method', 'moyasar')->count());
        $this->assertDatabaseHas('payments', [
            'payment_method' => 'moyasar',
            'amount_minor' => 1000,
            'expected_points' => 10_000_000,
        ]);
        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && str_ends_with($request->url(), '/invoices')
            && str_contains((string) $request['success_url'], '/Deposit/waiting')
            && str_contains((string) $request['success_url'], 'provider=moyasar')
            && str_contains((string) $request['success_url'], 'deposit_id='));
    }

    public function test_card_payment_creation_succeeds_without_moyasar_merchant_id(): void
    {
        config()->set('moyasar.merchant_id', '');
        config()->set('services.moyasar.merchant_id', null);

        Http::fake(function (Request $request) {
            if ($request->method() === 'GET' && str_contains($request->url(), '/invoices?')) {
                return Http::response(['invoices' => []]);
            }

            if ($request->method() === 'POST' && str_ends_with($request->url(), '/invoices')) {
                $data = $request->data();

                return Http::response([
                    'id' => 'invoice_no_merchant_id',
                    'status' => 'initiated',
                    'amount' => $data['amount'],
                    'currency' => $data['currency'],
                    'url' => 'https://checkout.moyasar.com/invoices/invoice_no_merchant_id',
                    'metadata' => $data['metadata'],
                    'payments' => [],
                ], 201);
            }

            return Http::response([], 404);
        });

        Sanctum::actingAs(User::factory()->create());

        $this->apiPost('/api/v1/moyasar/pay', [
            'amount' => '25.00',
            'idempotency_key' => (string) Str::uuid(),
            'locale' => 'en',
        ])
            ->assertOk()
            ->assertJsonPath('data.redirect_url', 'https://checkout.moyasar.com/invoices/invoice_no_merchant_id');

        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && str_ends_with($request->url(), '/invoices')
            && ! array_key_exists('merchant_id', $request->data())
            && ! array_key_exists('merchant_id', $request['metadata']));
    }

    public function test_missing_current_mode_secret_key_returns_clear_error(): void
    {
        config()->set('moyasar.mode', 'test');
        config()->set('moyasar.test.secret_key', '');
        config()->set('moyasar.live.secret_key', 'sk_live_should_not_be_required_in_test');
        Http::fake();

        Sanctum::actingAs(User::factory()->create());

        $this->apiPost('/api/v1/moyasar/pay', [
            'amount' => '10.00',
            'idempotency_key' => (string) Str::uuid(),
            'locale' => 'en',
        ])
            ->assertStatus(503)
            ->assertJsonPath('message', 'Moyasar test secret key is not configured.');

        Http::assertNothingSent();
    }

    public function test_live_mode_does_not_require_test_keys_for_card_payment_creation(): void
    {
        config()->set('moyasar.mode', 'live');
        config()->set('moyasar.test.secret_key', '');
        config()->set('moyasar.live.secret_key', 'sk_live_unit');
        config()->set('moyasar.live.publishable_key', '');
        config()->set('moyasar.merchant_id', '');
        config()->set('services.moyasar.merchant_id', null);

        Http::fake(function (Request $request) {
            if ($request->method() === 'GET' && str_contains($request->url(), '/invoices?')) {
                return Http::response(['invoices' => []]);
            }

            if ($request->method() === 'POST' && str_ends_with($request->url(), '/invoices')) {
                $data = $request->data();

                return Http::response([
                    'id' => 'invoice_live_mode',
                    'status' => 'initiated',
                    'amount' => $data['amount'],
                    'currency' => $data['currency'],
                    'url' => 'https://checkout.moyasar.com/invoices/invoice_live_mode',
                    'metadata' => $data['metadata'],
                    'payments' => [],
                ], 201);
            }

            return Http::response([], 404);
        });

        Sanctum::actingAs(User::factory()->create());

        $this->apiPost('/api/v1/moyasar/pay', [
            'amount' => '10.00',
            'idempotency_key' => (string) Str::uuid(),
            'locale' => 'en',
        ])
            ->assertOk()
            ->assertJsonPath('data.redirect_url', 'https://checkout.moyasar.com/invoices/invoice_live_mode');
    }

    public function test_repeating_same_idempotency_key_does_not_create_two_payments(): void
    {
        Http::fake(function (Request $request) {
            if ($request->method() === 'GET' && str_contains($request->url(), '/invoices?')) {
                return Http::response(['invoices' => []]);
            }

            if ($request->method() === 'POST' && str_ends_with($request->url(), '/invoices')) {
                $data = $request->data();

                return Http::response([
                    'id' => 'invoice_endpoint_idempotent',
                    'status' => 'initiated',
                    'amount' => $data['amount'],
                    'currency' => $data['currency'],
                    'url' => 'https://checkout.moyasar.com/invoices/invoice_endpoint_idempotent',
                    'metadata' => $data['metadata'],
                    'payments' => [],
                ], 201);
            }

            if ($request->method() === 'GET' && str_ends_with($request->url(), '/invoices/invoice_endpoint_idempotent')) {
                return Http::response([
                    'id' => 'invoice_endpoint_idempotent',
                    'status' => 'initiated',
                    'amount' => 1000,
                    'currency' => 'SAR',
                    'url' => 'https://checkout.moyasar.com/invoices/invoice_endpoint_idempotent',
                    'metadata' => Payment::first()->gateway_response['metadata'] ?? [],
                    'payments' => [],
                ]);
            }

            return Http::response([], 404);
        });

        Sanctum::actingAs(User::factory()->create());
        $payload = [
            'amount' => '10.00',
            'idempotency_key' => (string) Str::uuid(),
            'locale' => 'en',
        ];

        $first = $this->apiPost('/api/v1/moyasar/pay', $payload)->assertOk()->json('data.deposit_id');
        $second = $this->apiPost('/api/v1/moyasar/pay', $payload)->assertOk()->json('data.deposit_id');

        $this->assertSame($first, $second);
        $this->assertSame(1, Payment::where('payment_method', 'moyasar')->count());
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
            ->assertJson([
                'deposit_id' => $payment->id,
                'order_id' => $payment->id,
                'status' => 'initiated',
                'amount' => '10.00',
                'currency' => 'SAR',
                'points' => null,
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
