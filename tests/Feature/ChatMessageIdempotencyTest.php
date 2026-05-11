<?php

namespace Tests\Feature;

use App\Jobs\GenerateAssistantReplyJob;
use App\Models\Conversation;
use App\Models\MainTools;
use App\Models\Message;
use App\Models\SubTools;
use App\Models\User;
use App\Services\AI\AIPayloadBuilder;
use App\Services\AiArabicWriterService;
use App\Services\ConversationMessageCacheService;
use App\Services\QdrantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class ChatMessageIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        putenv('API_KEY=testing-api-key');
        $_ENV['API_KEY'] = 'testing-api-key';
        $_SERVER['API_KEY'] = 'testing-api-key';
    }

    public function test_sending_message_once_stores_once(): void
    {
        Queue::fake();
        [$user, $conversation] = $this->makeUserAndConversation();
        Sanctum::actingAs($user);

        $payload = $this->sendPayload($conversation->id);
        $response = $this->apiPostJson('/api/v1/message/send', $payload);

        $response->assertOk();
        $this->assertSame(1, Message::where('conversation_id', $conversation->id)->where('role', 'user')->count());
        Queue::assertPushed(GenerateAssistantReplyJob::class, 1);
    }

    public function test_fast_double_click_with_same_idempotency_key_stores_once(): void
    {
        Queue::fake();
        [$user, $conversation] = $this->makeUserAndConversation();
        Sanctum::actingAs($user);

        $payload = $this->sendPayload($conversation->id);
        $first = $this->apiPostJson('/api/v1/message/send', $payload);
        $second = $this->apiPostJson('/api/v1/message/send', $payload);

        $first->assertOk();
        $second->assertOk();
        $this->assertSame($first->json('data.message_id'), $second->json('data.message_id'));
        $this->assertSame(1, Message::where('conversation_id', $conversation->id)->where('role', 'user')->count());
    }

    public function test_refresh_retry_with_same_idempotency_key_does_not_duplicate(): void
    {
        Queue::fake();
        [$user, $conversation] = $this->makeUserAndConversation();
        Sanctum::actingAs($user);

        $payload = $this->sendPayload($conversation->id);
        $this->apiPostJson('/api/v1/message/send', $payload)->assertOk();

        // Simulates refresh/login state reset while frontend retries the same pending message.
        Sanctum::actingAs($user);
        $retry = $this->apiPostJson('/api/v1/message/send', $payload);

        $retry->assertOk();
        $this->assertSame(1, Message::where('conversation_id', $conversation->id)->where('role', 'user')->count());
    }

    public function test_network_retry_with_same_idempotency_key_does_not_duplicate(): void
    {
        Queue::fake();
        [$user, $conversation] = $this->makeUserAndConversation();
        Sanctum::actingAs($user);

        $payload = $this->sendPayload($conversation->id);
        $this->apiPostJson('/api/v1/message/send', $payload)->assertOk();
        $this->apiPostJson('/api/v1/message/send', $payload)->assertOk();
        $this->apiPostJson('/api/v1/message/send', $payload)->assertOk();

        $this->assertSame(1, Message::where('conversation_id', $conversation->id)->where('role', 'user')->count());
    }

    public function test_sse_reconnect_reads_existing_reply_without_creating_new_rows(): void
    {
        [$user, $conversation] = $this->makeUserAndConversation();

        $userMessage = Message::create([
            'conversation_id' => $conversation->id,
            'content' => 'Hello AI',
            'role' => 'user',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'content' => 'Hello from assistant',
            'role' => 'assistant',
            'reply_to_message_id' => $userMessage->id,
            'is_error' => false,
        ]);

        $token = $user->createToken('stream-test')->plainTextToken;
        $streamUrl = "/api/v1/conversation/{$conversation->uuid}/stream?after_id={$userMessage->id}&token={$token}";

        $this->get($streamUrl)->assertOk();
        $this->get($streamUrl)->assertOk();

        $this->assertSame(
            1,
            Message::where('conversation_id', $conversation->id)
                ->where('role', 'assistant')
                ->where('reply_to_message_id', $userMessage->id)
                ->count()
        );
    }

    public function test_queue_job_reexecution_creates_one_assistant_reply_only(): void
    {
        config()->set('services.aiarabic.inject_qdrant_context', false);

        [$user, $conversation] = $this->makeUserAndConversation();

        $userMessage = Message::create([
            'conversation_id' => $conversation->id,
            'content' => 'Need a response once',
            'role' => 'user',
            'idempotency_key' => (string) Str::uuid(),
        ]);

        $writer = Mockery::mock(AiArabicWriterService::class);
        $writer->shouldReceive('generateReplyWithUsage')->once()->andReturn([
            'reply' => 'Assistant answer',
            'usage' => [
                'input_tokens' => 100,
                'output_tokens' => 50,
                'total_tokens' => 150,
            ],
            'cost' => [
                'input_cost' => 0.000125,
                'output_cost' => 0.0005,
                'web_search_cost' => 0,
                'total_cost' => 0.000625,
                'currency' => 'USD',
            ],
            'request_id' => (string) Str::uuid(),
            'model_key' => 'writer_pro',
        ]);

        $payloadBuilder = Mockery::mock(AIPayloadBuilder::class);
        $payloadBuilder->shouldReceive('build')->once()->andReturn(['dummy' => true]);
        $payloadBuilder->shouldReceive('withTaskOptions')->once()->andReturn(['dummy' => true]);

        $qdrant = Mockery::mock(QdrantService::class);
        $qdrant->shouldReceive('collectionName')->andReturn("conversation_{$conversation->id}");
        $qdrant->shouldReceive('insertMessage')->twice();

        $job = new GenerateAssistantReplyJob($userMessage->id);
        $job->handle(
            $writer,
            $payloadBuilder,
            app(ConversationMessageCacheService::class),
            $qdrant
        );

        // Simulates queue retry / duplicate worker execution.
        $job->handle(
            $writer,
            $payloadBuilder,
            app(ConversationMessageCacheService::class),
            $qdrant
        );

        $this->assertSame(
            1,
            Message::where('conversation_id', $conversation->id)
                ->where('role', 'assistant')
                ->where('reply_to_message_id', $userMessage->id)
                ->count()
        );
    }

    protected function makeUserAndConversation(): array
    {
        $user = User::factory()->create();

        $mainTool = MainTools::create([
            'name' => 'Main Tool ' . Str::random(8),
            'slug' => 'main-' . Str::random(10),
        ]);

        $subTool = SubTools::create([
            'main_tool_id' => $mainTool->id,
            'name' => 'Sub Tool ' . Str::random(8),
            'slug' => 'sub-' . Str::random(10),
        ]);

        $conversation = Conversation::create([
            'user_id' => $user->id,
            'sub_tool_id' => $subTool->id,
            'uuid' => (string) Str::uuid(),
        ]);

        return [$user, $conversation];
    }

    protected function sendPayload(int $conversationId): array
    {
        return [
            'content' => 'Test message',
            'conversation_id' => $conversationId,
            'role' => 'user',
            'idempotency_key' => (string) Str::uuid(),
        ];
    }

    protected function apiPostJson(string $uri, array $payload)
    {
        return $this->withHeaders([
            'X-API-KEY' => 'testing-api-key',
        ])->postJson($uri, $payload);
    }
}
