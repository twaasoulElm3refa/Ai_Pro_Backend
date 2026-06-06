<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\CostLogger;
use App\Models\MainTools;
use App\Models\Message;
use App\Models\SubTools;
use App\Models\User;
use App\Models\Wallet;
use App\Services\AiArabicWriterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class SocialPostGeneratorFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        putenv('API_KEY=testing-api-key');
        $_ENV['API_KEY'] = 'testing-api-key';
        $_SERVER['API_KEY'] = 'testing-api-key';
    }

    public function test_minimal_first_message_payload_returns_and_persists_a_question(): void
    {
        [$user, $conversation, $wallet] = $this->makeContext(100);
        Sanctum::actingAs($user);

        $writer = Mockery::mock(AiArabicWriterService::class);
        $writer->shouldNotReceive('generateReplyWithUsage');
        $this->app->instance(AiArabicWriterService::class, $writer);

        $response = $this->apiPostJson('/api/v1/message/send', $this->payload($conversation));

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.success', true)
            ->assertJsonPath('data.type', 'question')
            ->assertJsonPath('data.tool', 'ai_social_post_generator')
            ->assertJsonPath('data.model_key', 'social_post_generator')
            ->assertJsonPath('data.state.content', 'Write a professional LinkedIn post about a new AI tool.')
            ->assertJsonPath('data.state.platform', 'LinkedIn')
            ->assertJsonPath('data.state.language', 'English')
            ->assertJsonPath('data.state.hashtag_count', null)
            ->assertJsonPath('data.usage', null)
            ->assertJsonPath('data.cost', null)
            ->assertJsonPath('data.tokens_deducted', 0)
            ->assertJsonCount(0, 'data.results');

        $assistant = Message::where('conversation_id', $conversation->id)
            ->where('role', 'assistant')
            ->firstOrFail();

        $this->assertSame('question', $assistant->metadata['type']);
        $this->assertSame(5, $assistant->metadata['sub_tool_id']);
        $this->assertSame('ai_social_post_generator', $assistant->metadata['tool']);
        $this->assertNull($assistant->metadata['state']['hashtag_count']);
        $this->assertSame(0, CostLogger::where('conversation_id', $conversation->id)->count());
        $wallet->refresh();
        $this->assertSame(100, (int) $wallet->balance);
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Write a professional LinkedIn post about a new AI tool.',
        ]);
    }

    public function test_complete_state_persists_results_cost_and_wallet_deduction(): void
    {
        [$user, $conversation, $wallet] = $this->makeContext(100);
        Sanctum::actingAs($user);

        $state = $this->completeState();
        $requestId = (string) Str::uuid();
        $writer = Mockery::mock(AiArabicWriterService::class);
        $writer->shouldReceive('generateReplyWithUsage')
            ->once()
            ->withArgs(function (array $payload, string $endpoint) use ($state): bool {
                return $endpoint === 'tasks/social-post-generator/chat'
                    && $payload['sub_tool_id'] === 5
                    && $payload['tool'] === 'ai_social_post_generator'
                    && $payload['model_key'] === 'social_post_generator'
                    && $payload['state'] === $state;
            })
            ->andReturn([
                'reply' => 'Social posts generated.',
                'type' => 'result',
                'tool' => 'ai_social_post_generator',
                'provider' => 'openrouter',
                'model_key' => 'social_post_generator',
                'request_id' => $requestId,
                'state' => $state,
                'usage' => [
                    'input_tokens' => 20,
                    'output_tokens' => 10,
                    'total_tokens' => 30,
                ],
                'cost' => [
                    'input_cost' => 0.00002,
                    'output_cost' => 0.00001,
                    'web_search_cost' => 0,
                    'total_cost' => 0.00003,
                    'currency' => 'USD',
                ],
                'raw' => [
                    'success' => true,
                    'type' => 'result',
                    'tool' => 'ai_social_post_generator',
                    'provider' => 'openrouter',
                    'model_key' => 'social_post_generator',
                    'request_id' => $requestId,
                    'message' => 'Social posts generated.',
                    'state' => $state,
                    'results' => [
                        ['id' => 1, 'text' => 'First post', 'meta' => []],
                        ['id' => 2, 'text' => 'Second post', 'meta' => []],
                    ],
                    'count' => 2,
                    'usage' => [
                        'input_tokens' => 20,
                        'output_tokens' => 10,
                        'total_tokens' => 30,
                    ],
                    'cost' => [
                        'input_cost' => 0.00002,
                        'output_cost' => 0.00001,
                        'web_search_cost' => 0,
                        'total_cost' => 0.00003,
                        'currency' => 'USD',
                    ],
                ],
            ]);
        $this->app->instance(AiArabicWriterService::class, $writer);

        $payload = $this->payload($conversation);
        $payload['state'] = $state;

        $response = $this->apiPostJson('/api/v1/message/send', $payload);

        $response->assertOk()
            ->assertJsonPath('data.type', 'result')
            ->assertJsonPath('data.provider', 'openrouter')
            ->assertJsonPath('data.model_key', 'social_post_generator')
            ->assertJsonPath('data.results.0.text', 'First post')
            ->assertJsonPath('data.results.1.text', 'Second post')
            ->assertJsonPath('data.usage.total_tokens', 30)
            ->assertJsonPath('data.cost.currency', 'USD');

        $assistant = Message::where('conversation_id', $conversation->id)
            ->where('role', 'assistant')
            ->firstOrFail();

        $this->assertSame("First post\n\nSecond post", $assistant->content);
        $this->assertSame('result', $assistant->metadata['type']);
        $this->assertCount(2, $assistant->metadata['results']);
        $this->assertSame($requestId, $assistant->metadata['request_id']);

        $wallet->refresh();
        $this->assertSame(70, (int) $wallet->balance);

        $costLog = CostLogger::where('conversation_id', $conversation->id)->firstOrFail();
        $this->assertSame(5, (int) $costLog->sub_tool_id);
        $this->assertSame(30, (int) $costLog->total_tokens);
        $this->assertSame('social_post_generator', $costLog->model_key);
    }

    public function test_provider_failure_persists_an_error_assistant_message(): void
    {
        [$user, $conversation, $wallet] = $this->makeContext(100);
        Sanctum::actingAs($user);

        $writer = Mockery::mock(AiArabicWriterService::class);
        $writer->shouldReceive('generateReplyWithUsage')
            ->once()
            ->andThrow(new \RuntimeException('Provider unavailable'));
        $this->app->instance(AiArabicWriterService::class, $writer);

        $payload = $this->payload($conversation);
        $payload['state'] = $this->completeState();
        $payload['debug'] = true;

        $response = $this->apiPostJson('/api/v1/message/send', $payload);

        $response->assertOk()
            ->assertJsonPath('data.success', false)
            ->assertJsonPath('data.type', 'error')
            ->assertJsonPath('data.tool', 'ai_social_post_generator')
            ->assertJsonPath('data.model_key', 'social_post_generator')
            ->assertJsonPath('data.message', 'Provider unavailable')
            ->assertJsonPath('data.debug.error', 'Provider unavailable')
            ->assertJsonPath('data.tokens_deducted', 0)
            ->assertJsonPath('data.usage', null)
            ->assertJsonPath('data.cost', null);

        $assistant = Message::where('conversation_id', $conversation->id)
            ->where('role', 'assistant')
            ->firstOrFail();

        $this->assertTrue($assistant->is_error);
        $this->assertSame('error', $assistant->metadata['type']);
        $this->assertSame('openrouter', $assistant->metadata['provider']);
        $this->assertSame(0, CostLogger::where('conversation_id', $conversation->id)->count());
        $wallet->refresh();
        $this->assertSame(100, (int) $wallet->balance);
    }

    protected function makeContext(int $walletBalance = 0): array
    {
        $user = User::factory()->create();
        $mainTool = MainTools::create([
            'name' => 'Content Tools',
            'slug' => 'content-tools-'.Str::random(8),
        ]);
        $subTool = SubTools::create([
            'id' => 5,
            'main_tool_id' => $mainTool->id,
            'name' => 'AI Social Post Generator',
            'slug' => 'ai-social-post-generator',
            'endpoint' => 'tasks/social-post-generator/chat',
        ]);
        $conversation = Conversation::create([
            'user_id' => $user->id,
            'sub_tool_id' => $subTool->id,
            'uuid' => (string) Str::uuid(),
        ]);
        $wallet = Wallet::create([
            'user_id' => $user->id,
            'uuid' => (string) Str::uuid(),
            'balance' => $walletBalance,
        ]);

        return [$user, $conversation, $wallet];
    }

    protected function payload(Conversation $conversation): array
    {
        return [
            'user_id' => $conversation->user_id,
            'sub_tool_id' => 5,
            'conversation_uuid' => $conversation->uuid,
            'user_message' => 'Write a professional LinkedIn post about a new AI tool.',
            'state' => [
                'content' => null,
                'platform' => null,
                'language' => null,
                'tone' => null,
                'audience' => null,
                'goal' => null,
                'length' => null,
                'hashtag_count' => null,
                'include_emojis' => null,
                'results_count' => null,
                'extra_options' => [],
                'last_output' => null,
            ],
            'debug' => false,
        ];
    }

    protected function completeState(): array
    {
        return [
            'content' => 'Launch a new AI content tool',
            'platform' => 'LinkedIn',
            'language' => 'English',
            'tone' => 'Professional',
            'audience' => 'General Audience',
            'goal' => 'Engagement',
            'length' => 'Medium',
            'hashtag_count' => 3,
            'include_emojis' => true,
            'results_count' => 2,
            'extra_options' => ['Make it ready to publish'],
            'last_output' => null,
        ];
    }

    protected function apiPostJson(string $uri, array $payload)
    {
        return $this->withHeaders([
            'X-API-KEY' => 'testing-api-key',
        ])->postJson($uri, $payload);
    }
}
