<?php

namespace Tests\Feature;

use App\Models\MainFreeAiModels;
use App\Models\ModelsConverstaions;
use App\Models\Payment;
use App\Models\User;
use App\Models\Wallet;
use App\Services\WalletDepositCreditService;
use App\Services\PayPalClientFactory;
use App\Services\PayPalWalletServices;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class FreeAiChatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        putenv('API_KEY=testing-api-key');
        $_ENV['API_KEY'] = 'testing-api-key';
        $_SERVER['API_KEY'] = 'testing-api-key';
        config()->set('services.aiarabic.base_url', 'https://api.aiarabic.com');
        config()->set('services.aiarabic.internal_api_key', 'test-internal-key');
        config()->set('model_catalogs.sources.general_chat', [
            'endpoint' => 'https://catalog.example.test/models',
            'requires_internal_key' => false,
        ]);
        config()->set('model_catalogs.sources.general_code', [
            'endpoint' => 'https://catalog.example.test/code-models',
            'requires_internal_key' => false,
        ]);
    }

    public function test_chat_sends_exact_provider_payload_and_charges_usage_once(): void
    {
        $this->fakeProvider(2, 4, 1);
        [$user, $conversation, $wallet] = $this->conversation(5);
        Sanctum::actingAs($user);
        $url = $this->url($conversation);
        $requestId = (string) Str::uuid();
        $body = ['user_message' => 'Hi', 'request_id' => $requestId];

        $this->api()->get('/api/v1/users/wallet')->assertJsonPath('data.balance', 5);
        $this->api()->postJson($url, $body)
            ->assertOk()
            ->assertJsonPath('data.assistant_message.content', 'AI RESPONSE')
            ->assertJsonPath('data.wallet.balance', 0)
            ->assertJsonPath('data.wallet.payback_balance', 2)
            ->assertJsonMissingPath('data.metadata');

        Http::assertSent(fn ($request) => $request->url() === 'https://api.aiarabic.com/tasks/general-chat'
            && $request->hasHeader('x-internal-api-key', 'test-internal-key')
            && $request->data() === [
                'user_id' => $user->id,
                'model_id' => $conversation->model_id,
                'selected_model_id' => 1,
                'conversation_uuid' => $conversation->uuid,
                'user_message' => 'Hi',
                'state' => ['parameters' => ['quality_mode' => 'balanced']],
                'debug' => true,
            ]);
        $this->assertDatabaseHas('models_cost_loggers', [
            'request_id' => $requestId,
            'input_tokens' => 2,
            'output_tokens' => 4,
            'reasoning_tokens' => 1,
            'total_tokens' => 7,
        ]);
        $this->assertDatabaseHas('wallet_transactions', [
            'type' => 'debit',
            'points' => 7,
            'balance_before' => 5,
            'balance_after' => 0,
            'payback_after' => 2,
        ]);
        $this->assertSame(2, (int) $wallet->fresh()->payback_balance);
        $this->api()->get('/api/v1/users/wallet')->assertJsonPath('data.balance', 0);
        $this->api()->get($url)->assertJsonPath('data.items.0.role', 'user')
            ->assertJsonPath('data.items.1.role', 'assistant')
            ->assertJsonMissingPath('data.items.1.metadata');

        $this->api()->postJson($url, $body)->assertOk();
        $this->assertDatabaseCount('models_messages', 2);
        $this->assertDatabaseCount('models_cost_loggers', 1);
        Http::assertSentCount(2);
    }

    public function test_insufficient_balance_and_cross_user_access_never_call_provider(): void
    {
        $this->fakeProvider(2, 4, 1);
        [$user, $conversation] = $this->conversation(1);
        $other = User::factory()->create();
        $url = $this->url($conversation);
        $body = ['user_message' => 'Hi', 'request_id' => (string) Str::uuid()];

        Sanctum::actingAs($other);
        $this->api()->postJson($url, $body)->assertNotFound();
        Sanctum::actingAs($user);
        $this->api()->postJson($url, $body)->assertStatus(402);
        $this->assertDatabaseCount('models_messages', 0);
        Http::assertNotSent(fn ($request) => $request->url() === 'https://api.aiarabic.com/tasks/general-chat');
    }

    public function test_invalid_provider_usage_does_not_charge_wallet(): void
    {
        $this->fakeProvider(null, 4, 1);
        [$user, $conversation, $wallet] = $this->conversation(10);
        Sanctum::actingAs($user);

        $this->api()->postJson($this->url($conversation), [
            'user_message' => 'Hi',
            'request_id' => (string) Str::uuid(),
        ])->assertStatus(502);

        $this->assertSame(10, (int) $wallet->fresh()->balance);
        $this->assertDatabaseCount('models_messages', 1);
        $this->assertDatabaseCount('models_cost_loggers', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_next_deposit_settles_payback_before_increasing_balance(): void
    {
        [, , $wallet] = $this->conversation(0);
        $wallet->update(['payback_balance' => 2]);

        $credit = app(WalletDepositCreditService::class)->apply($wallet, 5);

        $this->assertSame(3, (int) $wallet->fresh()->balance);
        $this->assertSame(0, (int) $wallet->fresh()->payback_balance);
        $this->assertSame(2, $credit['payback_before']);
        $this->assertSame(0, $credit['payback_after']);
        $this->assertSame(3, $credit['balance_after']);
    }

    public function test_provider_rate_limit_keeps_user_message_without_charging(): void
    {
        $this->fakeProvider(2, 4, 1, 429, ['Retry-After' => '12']);
        [$user, $conversation, $wallet] = $this->conversation(10);
        Sanctum::actingAs($user);

        $this->api()->postJson($this->url($conversation), [
            'user_message' => 'Hi',
            'request_id' => (string) Str::uuid(),
        ])->assertStatus(429)->assertHeader('Retry-After', '12');

        $this->assertSame(10, (int) $wallet->fresh()->balance);
        $this->assertDatabaseCount('models_messages', 1);
        $this->assertDatabaseCount('models_cost_loggers', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_code_generation_uses_exact_payload_and_existing_wallet_and_logs(): void
    {
        $this->fakeCodeProvider();
        [$user, $conversation, $wallet] = $this->codeConversation(5);
        Sanctum::actingAs($user);
        $url = "/api/v1/free-ai-models/programming-technology/conversations/{$conversation->uuid}/messages";
        $body = [
            'user_message' => 'Build an API',
            'request_id' => (string) Str::uuid(),
            'programming_language' => 'TypeScript / Express.js',
        ];

        $this->api()->get('/api/v1/users/wallet')->assertJsonPath('data.balance', 5);
        $this->api()->postJson($url, $body)
            ->assertOk()
            ->assertJsonPath('data.assistant_message.content', "```ts\nconst app = express();\n```")
            ->assertJsonPath('data.wallet.balance', 0)
            ->assertJsonPath('data.wallet.payback_balance', 1786)
            ->assertJsonMissingPath('data.metadata');

        Http::assertSent(fn ($request) => $request->url() === 'https://api.aiarabic.com/tasks/general-code'
            && $request->hasHeader('x-internal-api-key', 'test-internal-key')
            && $request->data() === [
                'user_id' => $user->id,
                'model_id' => $conversation->model_id,
                'selected_model_id' => 3,
                'conversation_uuid' => $conversation->uuid,
                'user_message' => 'Build an API',
                'state' => ['parameters' => [
                    'task_mode' => 'generate',
                    'programming_language' => 'TypeScript / Express.js',
                    'include_explanation' => true,
                    'include_tests' => true,
                ]],
            ]);
        $this->assertSame('general_code', $conversation->fresh()->tool_key);
        $this->assertSame(1786, (int) $wallet->fresh()->payback_balance);
        $this->assertDatabaseHas('models_cost_loggers', [
            'request_id' => $body['request_id'],
            'input_tokens' => 238,
            'output_tokens' => 1553,
            'reasoning_tokens' => 0,
            'total_tokens' => 1791,
        ]);
        $this->assertDatabaseHas('wallet_transactions', [
            'points' => 1791,
            'balance_after' => 0,
            'payback_after' => 1786,
        ]);
        $userMessage = $conversation->messages()->where('role', 'user')->firstOrFail();
        $this->assertSame('TypeScript / Express.js', $userMessage->metadata['programming_language']);
        $this->assertSame('completed', $userMessage->metadata['status']);
        $this->assertSame(true, $userMessage->metadata['include_tests']);
        $this->assertSame('general_code', $conversation->costLoggers()->firstOrFail()->metadata['tool_type']);
        $this->api()->get('/api/v1/users/wallet')->assertJsonPath('data.balance', 0);

        $this->api()->postJson($url, $body)->assertOk();
        $this->assertDatabaseCount('models_messages', 2);
        $this->assertDatabaseCount('models_cost_loggers', 1);
        $this->assertCount(1, Http::recorded(fn ($request) => $request->url() === 'https://api.aiarabic.com/tasks/general-code'));
    }

    public function test_code_requires_an_option_and_sufficient_balance_before_provider_call(): void
    {
        $this->fakeCodeProvider();
        [$user, $conversation] = $this->codeConversation(1);
        Sanctum::actingAs($user);
        $url = "/api/v1/free-ai-models/programming-technology/conversations/{$conversation->uuid}/messages";
        $body = ['user_message' => 'Build an API', 'request_id' => (string) Str::uuid()];

        $this->api()->postJson($url, $body)->assertStatus(422);
        $this->api()->postJson($url, [...$body, 'programming_language' => 'Express.js'])->assertStatus(402);
        $this->assertDatabaseCount('models_messages', 0);
        Http::assertNotSent(fn ($request) => $request->url() === 'https://api.aiarabic.com/tasks/general-code');
    }

    public function test_code_rejects_a_model_with_another_tool_key(): void
    {
        $this->fakeCodeProvider('general_chat');
        [$user, $conversation] = $this->codeConversation(100);
        Sanctum::actingAs($user);

        $this->api()->postJson(
            "/api/v1/free-ai-models/programming-technology/conversations/{$conversation->uuid}/messages",
            [
                'user_message' => 'Build an API',
                'request_id' => (string) Str::uuid(),
                'programming_language' => 'Express.js',
            ]
        )->assertStatus(422);

        $this->assertDatabaseCount('models_messages', 0);
        Http::assertNotSent(fn ($request) => $request->url() === 'https://api.aiarabic.com/tasks/general-code');
    }

    public function test_chat_send_limit_does_not_count_conversation_reads(): void
    {
        config()->set('free_ai_chat.send_rate_per_minute', 2);
        $this->fakeProvider(1, 1, 0);
        [$user, $conversation] = $this->conversation(20);
        Sanctum::actingAs($user);
        $url = $this->url($conversation);

        for ($attempt = 0; $attempt < 12; $attempt++) {
            $this->api()->get($url)->assertOk();
        }

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->api()->postJson($url, [
                'user_message' => "Message {$attempt}",
                'request_id' => (string) Str::uuid(),
            ])->assertOk();
        }

        $this->api()->postJson($url, [
            'user_message' => 'One too many',
            'request_id' => (string) Str::uuid(),
        ])->assertStatus(429)->assertHeader('Retry-After');

        $this->assertDatabaseCount('models_messages', 4);
        $this->assertCount(2, Http::recorded(fn ($request) => $request->url() === 'https://api.aiarabic.com/tasks/general-chat'));
    }

    public function test_paypal_deposit_applies_existing_payback_and_records_gross_credit(): void
    {
        config()->set('paypal.merchant_id', 'MERCHANT-123');
        config()->set('paypal.currency', 'USD');
        config()->set('wallet.points_per_usd', 1_000_000);
        Queue::fake();
        [$user, , $wallet] = $this->conversation(0);
        $wallet->update(['payback_balance' => 2]);
        $payment = Payment::create([
            'user_id' => $user->id,
            'payment_method' => 'paypal',
            'type' => 'wallet_deposit',
            'status' => Payment::STATUS_PENDING,
            'currency' => 'USD',
            'amount' => '12.34',
            'paypal_order_id' => 'PAYPAL-ORDER-1',
            'description' => 'Wallet Deposit',
            'idempotency_key' => hash('sha256', (string) Str::uuid()),
            'mail_sent' => false,
            'wallet_credited' => false,
        ]);
        $factory = Mockery::mock(PayPalClientFactory::class);
        $factory->shouldNotReceive('make');

        (new PayPalWalletServices($factory))->finalizeCompletedWalletCapture([
            'id' => 'CAPTURE-123',
            'status' => 'COMPLETED',
            'amount' => ['value' => '12.34', 'currency_code' => 'USD'],
            'custom_id' => 'wallet_topup:'.$payment->id,
            'payee' => ['merchant_id' => 'MERCHANT-123'],
            'supplementary_data' => ['related_ids' => ['order_id' => $payment->paypal_order_id]],
            'update_time' => '2026-07-19T10:00:00Z',
        ]);

        $this->assertSame(12_339_998, (int) $wallet->fresh()->balance);
        $this->assertSame(0, (int) $wallet->fresh()->payback_balance);
        $this->assertDatabaseHas('wallet_transactions', [
            'payment_id' => $payment->id,
            'points' => 12_340_000,
            'balance_after' => 12_339_998,
            'payback_before' => 2,
            'payback_after' => 0,
        ]);
    }

    private function conversation(int $balance): array
    {
        $user = User::factory()->create();
        $model = MainFreeAiModels::create([
            'name' => 'Chat Writing',
            'slug' => 'chat-writing',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $conversation = ModelsConverstaions::create([
            'user_id' => $user->id,
            'model_id' => $model->id,
            'uuid' => (string) Str::uuid(),
            'selected_model_source' => 'general_chat',
            'selected_model_id' => 1,
            'provider_model_id' => 'openrouter/free',
            'selected_model_name' => 'Free Smart Router',
        ]);
        $wallet = Wallet::create([
            'user_id' => $user->id,
            'uuid' => (string) Str::uuid(),
            'balance' => $balance,
            'payback_balance' => 0,
            'is_active' => true,
        ]);

        return [$user, $conversation, $wallet];
    }

    private function codeConversation(int $balance): array
    {
        $user = User::factory()->create();
        $model = MainFreeAiModels::create([
            'name' => 'Programming & Technology',
            'slug' => 'programming-technology',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $conversation = ModelsConverstaions::create([
            'user_id' => $user->id,
            'model_id' => $model->id,
            'uuid' => (string) Str::uuid(),
            'selected_model_source' => 'general_code',
            'selected_model_id' => 3,
            'provider_model_id' => 'qwen/qwen3-coder-next',
            'selected_model_name' => 'Qwen3 Coder Next',
        ]);
        $wallet = Wallet::create([
            'user_id' => $user->id,
            'uuid' => (string) Str::uuid(),
            'balance' => $balance,
            'payback_balance' => 0,
            'is_active' => true,
        ]);

        return [$user, $conversation, $wallet];
    }

    private function fakeCodeProvider(string $catalogToolKey = 'general_code'): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'catalog.example.test/code-models' => Http::response([
                'tool' => 'general_code',
                'items' => [[
                    'id' => 3,
                    'provider' => 'openrouter',
                    'provider_model_id' => 'qwen/qwen3-coder-next',
                    'name' => 'Qwen3 Coder Next',
                    'tool_key' => $catalogToolKey,
                    'operation' => 'text_generation',
                    'is_available' => true,
                ]],
            ]),
            'api.aiarabic.com/tasks/general-code' => Http::response([
                'success' => true,
                'type' => 'result',
                'tool' => 'general_code',
                'provider' => 'openrouter',
                'model' => 'qwen/qwen3-coder-next',
                'content' => "```ts\nconst app = express();\n```",
                'usage' => ['total_cost' => 0],
                'metadata' => ['provider_usage' => [
                    'prompt_tokens' => 238,
                    'completion_tokens' => 1553,
                    'completion_tokens_details' => ['reasoning_tokens' => 0],
                ]],
                'router_metadata' => ['private' => true],
                'debug' => ['private' => true],
            ]),
        ]);
    }

    private function fakeProvider(?int $input, int $output, int $reasoning, int $status = 200, array $headers = []): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'catalog.example.test/*' => Http::response([
                'tool' => 'general_chat',
                'items' => [[
                    'id' => 1,
                    'provider' => 'openrouter',
                    'provider_model_id' => 'openrouter/free',
                    'name' => 'Free Smart Router',
                    'tool_key' => 'general_chat',
                    'operation' => 'text_generation',
                    'is_available' => true,
                ]],
            ]),
            'api.aiarabic.com/tasks/general-chat' => Http::response([
                'success' => $status === 200,
                'type' => 'result',
                'content' => 'AI RESPONSE',
                'usage' => ['total_cost' => 0],
                'metadata' => ['provider_usage' => [
                    'prompt_tokens' => $input,
                    'completion_tokens' => $output,
                    'completion_tokens_details' => ['reasoning_tokens' => $reasoning],
                ]],
            ], $status, $headers),
        ]);
    }

    private function url(ModelsConverstaions $conversation): string
    {
        return "/api/v1/free-ai-models/chat-writing/conversations/{$conversation->uuid}/messages";
    }

    private function api()
    {
        return $this->withHeaders([
            'Accept' => 'application/json',
            'X-API-KEY' => 'testing-api-key',
        ]);
    }
}
