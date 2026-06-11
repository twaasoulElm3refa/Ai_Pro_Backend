<?php

namespace Tests\Feature;

use App\Jobs\GenerateAssistantReplyJob;
use App\Models\Conversation;
use App\Models\MainTools;
use App\Models\Message;
use App\Models\SubTools;
use App\Models\User;
use App\Models\Wallet;
use App\Services\AI\AIPayloadBuilder;
use App\Services\AI\DynamicToolPayloadBuilder;
use App\Services\AiArabicWriterService;
use App\Services\ConversationMessageCacheService;
use App\Services\QdrantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class DynamicToolFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        putenv('API_KEY=testing-api-key');
        $_ENV['API_KEY'] = 'testing-api-key';
        $_SERVER['API_KEY'] = 'testing-api-key';
        config()->set('services.aiarabic.inject_qdrant_context', false);
    }

    public function test_tool_nine_request_accepts_and_persists_its_dynamic_state(): void
    {
        Queue::fake();
        [$user, $conversation] = $this->makeContext(9, $this->promptGeneratorConfig());
        Sanctum::actingAs($user);
        $state = $this->promptGeneratorState();

        $response = $this->apiPostJson('/api/v1/message/send', [
            'sub_tool_id' => 9,
            'conversation_uuid' => $conversation->uuid,
            'user_message' => 'Generate professional SEO prompts about AI tools.',
            'state' => $state,
            'debug' => false,
        ]);

        $response->assertOk()->assertJsonPath('data.conversation_id', $conversation->id);

        $message = Message::where('conversation_id', $conversation->id)
            ->where('role', 'user')
            ->firstOrFail();

        $this->assertSame($state, $message->metadata['state']);
        $this->assertSame(3, $message->metadata['state']['results_count']);
        $this->assertSame([], $message->metadata['state']['extra_options']);
        Queue::assertPushed(
            GenerateAssistantReplyJob::class,
            fn (GenerateAssistantReplyJob $job): bool => $job->state === $state
                && $job->debug === false
        );
    }

    public function test_dynamic_payload_builder_uses_config_state_endpoint_model_and_tool(): void
    {
        [, $conversation] = $this->makeContext(9, $this->promptGeneratorConfig(), 'tasks/database-fallback/chat');
        $state = $this->promptGeneratorState();
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Generate professional SEO prompts about AI tools.',
            'idempotency_key' => (string) Str::uuid(),
            'metadata' => ['state' => $state],
        ]);

        $built = app(DynamicToolPayloadBuilder::class)->build(
            $conversation,
            $message,
            $state,
            false
        );
        $payload = $built['payload'];

        $this->assertSame('tasks/prompt-generator/chat', $built['endpoint']);
        $this->assertSame('ai_prompt_generator', $payload['tool']);
        $this->assertSame('prompt_generator', $payload['model_key']);
        $this->assertSame('openrouter', $payload['provider']);
        $this->assertSame($state, $payload['state']);
        $this->assertSame(3, $payload['state']['results_count']);
        $this->assertSame([], $payload['state']['extra_options']);
        $this->assertSame($message->content, $payload['content']);
        $this->assertSame($state['task'], $payload['requested_task']);
        $this->assertSame('results', $payload['response_format']);
    }

    public function test_job_sends_dynamic_payload_to_configured_endpoint_without_real_http(): void
    {
        [, $conversation] = $this->makeContext(9, $this->promptGeneratorConfig());
        $state = $this->promptGeneratorState();
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Generate professional SEO prompts about AI tools.',
            'idempotency_key' => (string) Str::uuid(),
            'metadata' => ['state' => $state],
        ]);

        $writer = Mockery::mock(AiArabicWriterService::class);
        $writer->shouldReceive('generateReplyWithUsage')
            ->once()
            ->withArgs(function (array $payload, string $endpoint) use ($state): bool {
                return $endpoint === 'tasks/prompt-generator/chat'
                    && $payload['sub_tool_id'] === 9
                    && $payload['tool'] === 'ai_prompt_generator'
                    && $payload['model_key'] === 'prompt_generator'
                    && $payload['state'] === $state
                    && $payload['state']['results_count'] === 3
                    && $payload['state']['extra_options'] === [];
            })
            ->andReturn([
                'reply' => 'Generated prompt result',
                'usage' => [
                    'input_tokens' => 2,
                    'output_tokens' => 1,
                    'total_tokens' => 3,
                ],
                'model_key' => 'prompt_generator',
            ]);

        $qdrant = Mockery::mock(QdrantService::class);
        $qdrant->shouldReceive('collectionName')->andReturn("conversation_{$conversation->id}");
        $qdrant->shouldReceive('insertMessage')->twice();

        $job = new GenerateAssistantReplyJob($message->id, null, $state, false);
        $job->handle(
            $writer,
            app(AIPayloadBuilder::class),
            app(ConversationMessageCacheService::class),
            $qdrant
        );

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Generated prompt result',
        ]);
    }

    public function test_dynamic_builder_falls_back_to_sub_tools_endpoint(): void
    {
        $config = $this->promptGeneratorConfig();
        unset($config['endpoint']);
        [, $conversation] = $this->makeContext(12, $config, 'tasks/database-endpoint/chat');
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Use the database endpoint.',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $built = app(DynamicToolPayloadBuilder::class)->build(
            $conversation,
            $message,
            ['results_count' => 3, 'extra_options' => []]
        );

        $this->assertSame('tasks/database-endpoint/chat', $built['endpoint']);
    }

    public function test_dynamic_validation_fails_when_required_state_field_is_missing(): void
    {
        Queue::fake();
        [$user, $conversation] = $this->makeContext(10, [
            'endpoint' => 'tasks/required-state/chat',
            'state_schema' => [
                'task' => [
                    'type' => 'string',
                    'required' => true,
                ],
            ],
        ]);
        Sanctum::actingAs($user);

        $this->apiPostJson('/api/v1/message/send', [
            'sub_tool_id' => 10,
            'conversation_uuid' => $conversation->uuid,
            'user_message' => 'Run the configured tool.',
            'state' => [],
        ])->assertStatus(422)->assertJsonValidationErrors(['state.task']);
    }

    public function test_dynamic_validation_accepts_required_nullable_fields(): void
    {
        Queue::fake();
        [$user, $conversation] = $this->makeContext(11, [
            'tool_key' => 'nullable_tool',
            'endpoint' => 'tasks/nullable/chat',
            'state_schema' => [
                'task' => [
                    'type' => 'string',
                    'required' => true,
                    'nullable' => true,
                ],
            ],
        ]);
        Sanctum::actingAs($user);

        $this->apiPostJson('/api/v1/message/send', [
            'sub_tool_id' => 11,
            'conversation_uuid' => $conversation->uuid,
            'user_message' => 'Run a newly configured tool.',
            'state' => ['task' => null],
        ])->assertOk();

        Queue::assertPushed(GenerateAssistantReplyJob::class, 1);
    }

    public function test_legacy_paraphraser_handler_still_runs(): void
    {
        [$user, $conversation] = $this->makeContext(
            3,
            [],
            'tasks/paraphraser/chat'
        );
        Sanctum::actingAs($user);

        $writer = Mockery::mock(AiArabicWriterService::class);
        $writer->shouldReceive('generateReplyWithUsage')
            ->once()
            ->withArgs(fn (array $payload, string $endpoint): bool => $endpoint === 'tasks/paraphraser/chat'
                && $payload['sub_tool_id'] === 3)
            ->andReturn([
                'reply' => 'Rewritten text',
                'raw' => [
                    'success' => true,
                    'message' => 'Done.',
                    'results' => [
                        ['id' => 1, 'text' => 'Rewritten text'],
                    ],
                ],
            ]);
        $this->app->instance(AiArabicWriterService::class, $writer);

        $this->apiPostJson('/api/v1/message/send', [
            'sub_tool_id' => 3,
            'conversation_uuid' => $conversation->uuid,
            'user_message' => 'Rewrite this text.',
            'state' => [
                'content' => 'Original text',
                'results_count' => 1,
                'extra_options' => [],
            ],
        ])->assertOk()->assertJsonPath('data.sub_tool_id', 3);
    }

    private function makeContext(int $subToolId, array $config, ?string $endpoint = null): array
    {
        $user = User::factory()->create();
        $mainTool = MainTools::create([
            'name' => "Main Tool {$subToolId}",
            'slug' => "main-tool-{$subToolId}-".Str::random(6),
        ]);
        $subTool = SubTools::create([
            'id' => $subToolId,
            'main_tool_id' => $mainTool->id,
            'name' => "Sub Tool {$subToolId}",
            'slug' => "sub-tool-{$subToolId}-".Str::random(6),
            'endpoint' => $endpoint,
            'config' => $config === [] ? null : $config,
        ]);
        $conversation = Conversation::create([
            'user_id' => $user->id,
            'sub_tool_id' => $subTool->id,
            'uuid' => (string) Str::uuid(),
        ]);
        Wallet::create([
            'user_id' => $user->id,
            'uuid' => (string) Str::uuid(),
            'balance' => 1000,
        ]);

        return [$user, $conversation, $subTool];
    }

    private function promptGeneratorConfig(): array
    {
        return [
            'tool_key' => 'ai_prompt_generator',
            'model_key' => 'prompt_generator',
            'endpoint' => 'tasks/prompt-generator/chat',
            'provider' => 'openrouter',
            'system_prompt' => 'Generate production-ready prompts.',
            'response_format' => 'results',
            'default_state' => [
                'task' => null,
                'target_ai_tool' => null,
                'output_type' => null,
                'language' => null,
                'tone' => null,
                'audience' => null,
                'prompt_style' => null,
                'detail_level' => null,
                'include_constraints' => null,
                'results_count' => 1,
                'extra_options' => ['Include examples'],
                'last_output' => null,
            ],
            'state_schema' => [
                'task' => ['nullable', 'string', 'max:5000'],
                'target_ai_tool' => ['nullable', 'string', 'max:100'],
                'output_type' => ['nullable', 'string', 'max:100'],
                'language' => ['nullable', 'string', 'max:50'],
                'tone' => ['nullable', 'string', 'max:50'],
                'audience' => ['nullable', 'string', 'max:250'],
                'prompt_style' => ['nullable', 'string', 'max:100'],
                'detail_level' => ['nullable', 'string', 'max:100'],
                'include_constraints' => ['nullable', 'boolean'],
                'results_count' => ['required', 'integer', 'min:1', 'max:10'],
                'extra_options' => [
                    'type' => 'array',
                    'nullable' => true,
                    'items' => ['string', 'max:150'],
                ],
                'last_output' => ['nullable', 'string', 'max:10000'],
            ],
            'payload_map' => [
                'content' => 'user_message',
                'requested_task' => 'state.task',
            ],
        ];
    }

    private function promptGeneratorState(): array
    {
        return [
            'task' => 'Write an SEO article about artificial intelligence tools for marketers',
            'target_ai_tool' => 'ChatGPT',
            'output_type' => 'Prompt',
            'language' => 'English',
            'tone' => 'Professional',
            'audience' => 'Marketers',
            'prompt_style' => 'Long',
            'detail_level' => 'Detailed',
            'include_constraints' => true,
            'results_count' => 3,
            'extra_options' => [],
            'last_output' => null,
        ];
    }

    private function apiPostJson(string $uri, array $payload)
    {
        return $this->withHeaders([
            'X-API-KEY' => 'testing-api-key',
        ])->postJson($uri, $payload);
    }
}
