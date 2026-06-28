<?php

namespace Tests\Feature;

use App\Exceptions\AiServiceException;
use App\Models\Conversation;
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

class Chat3SeoToolFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        putenv('API_KEY=testing-api-key');
        $_ENV['API_KEY'] = 'testing-api-key';
        $_SERVER['API_KEY'] = 'testing-api-key';
    }

    public function test_keyword_generator_returns_normalized_text_only_results(): void
    {
        [$user, $conversation] = $this->makeContext(13);
        Sanctum::actingAs($user);

        $this->mockWriterResponse('{"results":[{"id":1,"title":"اقتراح كلمة مفتاحية","subject":"عام","text":"منتج كروي","meta":{"type":"short_tail","intent":"informational","cluster":"عام"}}]}');

        $response = $this->apiPostJson('/api/v1/message/send', [
            'user_id' => $user->id,
            'sub_tool_id' => 13,
            'conversation_uuid' => $conversation->uuid,
            'user_message' => 'Generate keywords for football product',
            'content' => 'Generate keywords for football product',
            'tool' => 'ai_keyword_generator',
            'tool_key' => 'ai_keyword_generator',
            'model_key' => 'keyword_generator',
            'state' => [
                'topic' => 'منتج كروي',
                'results_count' => 1,
                'include_long_tail' => false,
                'include_clusters' => true,
            ],
            'debug' => false,
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $response->assertOk()
            ->assertJsonPath('data.success', true)
            ->assertJsonPath('data.sub_tool_id', 13)
            ->assertJsonPath('data.tool_key', 'ai_keyword_generator')
            ->assertJsonPath('data.model_key', 'keyword_generator')
            ->assertJsonPath('data.normalized_results.0.text', 'منتج كروي')
            ->assertJsonPath('data.normalized_results.0.meta.type', 'short_tail')
            ->assertJsonPath('data.message', '');

        $this->assertSame('منتج كروي', Message::where('conversation_id', $conversation->id)->where('role', 'assistant')->firstOrFail()->content);
        $this->assertArrayNotHasKey('title', $response->json('data.normalized_results.0'));
        $this->assertArrayNotHasKey('subject', $response->json('data.normalized_results.0'));
    }

    public function test_meta_description_generator_respects_results_count_and_text_schema(): void
    {
        [$user, $conversation] = $this->makeContext(14);
        Sanctum::actingAs($user);

        $writer = Mockery::mock(AiArabicWriterService::class);
        $writer->shouldReceive('generateReplyWithUsage')
            ->once()
            ->withArgs(fn (array $payload, string $endpoint): bool =>
                $payload['sub_tool_id'] === 14
                && $payload['tool_key'] === 'ai_meta_description_generator'
                && $payload['model_key'] === 'meta_description_generator'
                && $payload['state']['results_count'] === 2
                && $endpoint === 'tasks/meta-description-generator/chat'
            )
            ->andReturn([
                'reply' => '{"results":[{"id":1,"text":"وصف تعريفي أول جاهز هنا","meta":{"characters":24,"max_characters":160}},{"id":2,"text":"وصف تعريفي ثاني جاهز هنا","meta":{"characters":25,"max_characters":160}}]}',
                'usage' => ['total_tokens' => 0],
                'model_key' => 'meta_description_generator',
            ]);
        $this->app->instance(AiArabicWriterService::class, $writer);

        $response = $this->apiPostJson('/api/v1/message/send', [
            'sub_tool_id' => 14,
            'conversation_uuid' => $conversation->uuid,
            'user_message' => 'Write two meta descriptions.',
            'content' => 'Landing page content',
            'tool_key' => 'ai_meta_description_generator',
            'model_key' => 'meta_description_generator',
            'state' => [
                'content' => 'Landing page content',
                'primary_keyword' => 'AI tools',
                'results_count' => 2,
                'max_characters' => 160,
            ],
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $response->assertOk()
            ->assertJsonPath('data.count', 2)
            ->assertJsonPath('data.normalized_results.0.text', 'وصف تعريفي أول جاهز هنا')
            ->assertJsonPath('data.normalized_results.1.text', 'وصف تعريفي ثاني جاهز هنا')
            ->assertJsonPath('data.state.results_count', 2);
    }

    public function test_keyword_generator_main_chat_payload_is_normalized_from_user_message(): void
    {
        [$user, $conversation] = $this->makeContext(13);
        Sanctum::actingAs($user);

        $message = 'Generate 3 keyword ideas about Egypt vs Iran match';
        $writer = Mockery::mock(AiArabicWriterService::class);
        $writer->shouldReceive('generateReplyWithUsage')
            ->once()
            ->withArgs(fn (array $payload, string $endpoint): bool =>
                $endpoint === 'tasks/keyword-generator/chat'
                && $payload['sub_tool_id'] === 13
                && $payload['tool_key'] === 'ai_keyword_generator'
                && $payload['model_key'] === 'keyword_generator'
                && $payload['content'] === $message
                && $payload['user_message'] === $message
                && $payload['state']['topic'] === $message
                && $payload['state']['results_count'] === 3
            )
            ->andReturn([
                'reply' => '{"results":[{"id":1,"text":"egypt iran match"},{"id":2,"text":"egypt football keywords"},{"id":3,"text":"iran match analysis"}]}',
                'usage' => ['total_tokens' => 0],
                'model_key' => 'keyword_generator',
            ]);
        $this->app->instance(AiArabicWriterService::class, $writer);

        $response = $this->apiPostJson('/api/v1/message/send', [
            'sub_tool_id' => 13,
            'conversation_uuid' => $conversation->uuid,
            'user_message' => $message,
            'tool' => 'ai_keyword_generator',
            'tool_key' => 'ai_keyword_generator',
            'model_key' => 'keyword_generator',
            'state' => [
                'results_count' => null,
                'extra_options' => null,
            ],
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $response->assertOk()
            ->assertJsonPath('data.count', 3)
            ->assertJsonPath('data.state.topic', $message)
            ->assertJsonPath('data.state.results_count', 3)
            ->assertJsonPath('data.request_payload.content', $message)
            ->assertJsonPath('data.request_payload.user_message', $message)
            ->assertJsonPath('data.request_payload.state.topic', $message)
            ->assertJsonPath('data.request_payload.state.results_count', 3);
    }

    public function test_keyword_generator_rate_limit_returns_clear_json_error(): void
    {
        [$user, $conversation] = $this->makeContext(13);
        Sanctum::actingAs($user);

        $message = 'Generate keywords about football';
        $friendlyMessage = 'الموديل مشغول مؤقتًا، حاول مرة أخرى بعد قليل.';
        $writer = Mockery::mock(AiArabicWriterService::class);
        $writer->shouldReceive('generateReplyWithUsage')
            ->once()
            ->andThrow(new AiServiceException($friendlyMessage, [
                'status' => 429,
                'friendly_message' => $friendlyMessage,
                'response_body' => '{"error":"rate limited"}',
            ], 429));
        $this->app->instance(AiArabicWriterService::class, $writer);

        $this->apiPostJson('/api/v1/message/send', [
            'sub_tool_id' => 13,
            'conversation_uuid' => $conversation->uuid,
            'user_message' => $message,
            'tool_key' => 'ai_keyword_generator',
            'model_key' => 'keyword_generator',
            'state' => [],
            'idempotency_key' => (string) Str::uuid(),
        ])->assertStatus(429)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', $friendlyMessage);
    }

    public function test_content_analyzer_returns_readable_text_inside_result_text(): void
    {
        [$user, $conversation] = $this->makeContext(15);
        Sanctum::actingAs($user);

        $this->mockWriterResponse('تحليل المحتوى هنا بشكل منسق وواضح');

        $response = $this->apiPostJson('/api/v1/message/send', [
            'sub_tool_id' => 15,
            'conversation_uuid' => $conversation->uuid,
            'user_message' => 'Analyze this content.',
            'content' => 'AI tools help marketers write faster.',
            'tool_key' => 'ai_content_analyzer',
            'model_key' => 'content_analyzer',
            'state' => [
                'content' => 'AI tools help marketers write faster.',
                'checks' => ['SEO', 'Readability'],
                'include_recommendations' => true,
            ],
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $response->assertOk()
            ->assertJsonPath('data.sub_tool_id', 15)
            ->assertJsonPath('data.normalized_results.0.text', 'تحليل المحتوى هنا بشكل منسق وواضح');
    }

    public function test_content_optimizer_returns_optimized_text_inside_result_text(): void
    {
        [$user, $conversation] = $this->makeContext(16);
        Sanctum::actingAs($user);

        $this->mockWriterResponse(['results' => [['id' => 1, 'text' => 'النص المحسن هنا', 'meta' => []]]]);

        $response = $this->apiPostJson('/api/v1/message/send', [
            'sub_tool_id' => 16,
            'conversation_uuid' => $conversation->uuid,
            'user_message' => 'Optimize this content.',
            'content' => 'Old content',
            'tool_key' => 'ai_content_optimizer',
            'model_key' => 'content_optimizer',
            'state' => [
                'content' => 'Old content',
                'primary_keyword' => 'AI writing tools',
                'secondary_keywords' => ['SEO tools'],
                'preserve_meaning' => true,
            ],
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $response->assertOk()
            ->assertJsonPath('data.sub_tool_id', 16)
            ->assertJsonPath('data.normalized_results.0.text', 'النص المحسن هنا');
    }

    public function test_request_without_auth_returns_401(): void
    {
        [, $conversation] = $this->makeContext(13);

        $this->apiPostJson('/api/v1/message/send', [
            'sub_tool_id' => 13,
            'conversation_uuid' => $conversation->uuid,
            'user_message' => 'Generate keywords',
            'tool_key' => 'ai_keyword_generator',
            'model_key' => 'keyword_generator',
        ])->assertUnauthorized();
    }

    public function test_request_to_another_users_conversation_returns_403(): void
    {
        [, $conversation] = $this->makeContext(13);
        $otherUser = User::factory()->create();
        Sanctum::actingAs($otherUser);

        $this->apiPostJson('/api/v1/message/send', [
            'sub_tool_id' => 13,
            'conversation_uuid' => $conversation->uuid,
            'user_message' => 'Generate keywords',
            'tool_key' => 'ai_keyword_generator',
            'model_key' => 'keyword_generator',
        ])->assertForbidden();
    }

    public function test_unsupported_seo_tool_returns_validation_error(): void
    {
        [$user, $conversation] = $this->makeContext(13);
        Sanctum::actingAs($user);

        $this->apiPostJson('/api/v1/message/send', [
            'sub_tool_id' => 99,
            'conversation_uuid' => $conversation->uuid,
            'user_message' => 'Generate keywords',
            'tool_key' => 'ai_keyword_generator',
            'model_key' => 'keyword_generator',
        ])->assertStatus(422)
            ->assertJsonPath('errors.sub_tool_id.0', 'Unsupported SEO tool.');
    }

    public function test_state_and_normalized_results_are_persisted_and_loaded_with_conversation(): void
    {
        [$user, $conversation] = $this->makeContext(13);
        Sanctum::actingAs($user);
        $this->mockWriterResponse('{"results":[{"id":1,"text":"seo keyword","meta":{"intent":"informational"}}]}');

        $this->apiPostJson('/api/v1/message/send', [
            'sub_tool_id' => 13,
            'conversation_uuid' => $conversation->uuid,
            'user_message' => 'Generate keywords',
            'tool_key' => 'ai_keyword_generator',
            'model_key' => 'keyword_generator',
            'state' => ['topic' => 'seo keyword', 'results_count' => 1],
            'idempotency_key' => (string) Str::uuid(),
        ])->assertOk();

        $assistant = Message::where('conversation_id', $conversation->id)->where('role', 'assistant')->firstOrFail();
        $this->assertSame('seo keyword', $assistant->metadata['state']['topic']);
        $this->assertSame('seo keyword', $assistant->metadata['normalized_results'][0]['text']);

        $this->withHeaders(['X-API-KEY' => 'testing-api-key'])
            ->getJson("/api/v1/conversation/{$conversation->uuid}")
            ->assertOk()
            ->assertJsonPath('data.message.1.metadata.normalized_results.0.text', 'seo keyword')
            ->assertJsonPath('data.message.1.metadata.state.topic', 'seo keyword');
    }

    private function mockWriterResponse(string|array $reply): void
    {
        $writer = Mockery::mock(AiArabicWriterService::class);
        $writer->shouldReceive('generateReplyWithUsage')
            ->once()
            ->andReturn(is_array($reply) ? [
                'reply' => '',
                'results' => $reply['results'] ?? [],
                'usage' => ['total_tokens' => 0],
            ] : [
                'reply' => $reply,
                'usage' => ['total_tokens' => 0],
            ]);

        $this->app->instance(AiArabicWriterService::class, $writer);
    }

    private function makeContext(int $subToolId): array
    {
        $user = User::factory()->create();
        Wallet::create([
            'user_id' => $user->id,
            'uuid' => (string) Str::uuid(),
            'balance' => 1000,
            'ip_address' => '127.0.0.1',
        ]);

        $mainTool = MainTools::create([
            'name' => 'SEO Tools '.Str::random(8),
            'slug' => 'seo-tools-'.Str::random(8),
        ]);

        $subTool = SubTools::create([
            'id' => $subToolId,
            'main_tool_id' => $mainTool->id,
            'name' => 'Chat3 Tool '.$subToolId.' '.Str::random(8),
            'slug' => 'chat3-tool-'.$subToolId.'-'.Str::random(8),
            'endpoint' => match ($subToolId) {
                13 => 'tasks/keyword-generator/chat',
                14 => 'tasks/meta-description-generator/chat',
                15 => 'tasks/content-analyzer/chat',
                16 => 'tasks/content-optimizer/chat',
                default => 'tasks/seo/chat',
            },
        ]);

        $conversation = Conversation::create([
            'user_id' => $user->id,
            'sub_tool_id' => $subTool->id,
            'uuid' => (string) Str::uuid(),
        ]);

        return [$user, $conversation];
    }

    private function apiPostJson(string $uri, array $payload)
    {
        return $this->withHeaders([
            'X-API-KEY' => 'testing-api-key',
        ])->postJson($uri, $payload);
    }
}
