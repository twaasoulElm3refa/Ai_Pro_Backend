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

class EmailWriterFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        putenv('API_KEY=testing-api-key');
        $_ENV['API_KEY'] = 'testing-api-key';
        $_SERVER['API_KEY'] = 'testing-api-key';
    }

    public function test_postman_shaped_payload_returns_email_and_charges_successful_usage(): void
    {
        [$user, $conversation, $wallet] = $this->makeContext(3000);
        Sanctum::actingAs($user);

        $state = $this->completeState();
        $requestId = (string) Str::uuid();
        $writer = Mockery::mock(AiArabicWriterService::class);
        $writer->shouldReceive('generateReplyWithUsage')
            ->once()
            ->withArgs(function (array $payload, string $endpoint): bool {
                return $endpoint === 'tasks/email-writer/chat'
                    && $payload['sub_tool_id'] === 6
                    && $payload['tool'] === 'ai_email_writer'
                    && $payload['model_key'] === 'email_writer'
                    && $payload['state'] === $this->emptyState();
            })
            ->andReturn([
                'reply' => 'تمت كتابة الإيميل بنجاح.',
                'type' => 'result',
                'provider' => 'openrouter',
                'request_id' => $requestId,
                'state' => $state,
                'usage' => [
                    'input_tokens' => 1245,
                    'output_tokens' => 1162,
                    'total_tokens' => 2407,
                ],
                'cost' => [
                    'input_cost' => 0.0001245,
                    'output_cost' => 0.000581,
                    'web_search_cost' => 0,
                    'total_cost' => 0.0007055,
                    'currency' => 'USD',
                ],
                'raw' => [
                    'success' => true,
                    'type' => 'result',
                    'tool' => 'ai_email_writer',
                    'provider' => 'openrouter',
                    'model_key' => 'email_writer',
                    'request_id' => $requestId,
                    'message' => 'تمت كتابة الإيميل بنجاح.',
                    'state' => $state,
                    'results' => [[
                        'id' => 1,
                        'text' => "Subject: تهنئة بقبول طلبك\n\nمرحبًا، تم قبول طلبك بنجاح.",
                        'title' => null,
                        'subject' => 'تهنئة بقبول طلبك',
                        'meta' => [],
                    ]],
                    'count' => 1,
                    'usage' => [
                        'input_tokens' => 1245,
                        'output_tokens' => 1162,
                        'total_tokens' => 2407,
                    ],
                    'cost' => [
                        'input_cost' => 0.0001245,
                        'output_cost' => 0.000581,
                        'web_search_cost' => 0,
                        'total_cost' => 0.0007055,
                        'currency' => 'USD',
                    ],
                ],
            ]);
        $this->app->instance(AiArabicWriterService::class, $writer);

        $response = $this->apiPostJson('/api/v1/message/send', $this->payload($conversation));

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.success', true)
            ->assertJsonPath('data.type', 'result')
            ->assertJsonPath('data.tool', 'ai_email_writer')
            ->assertJsonPath('data.model_key', 'email_writer')
            ->assertJsonPath('data.results.0.subject', 'تهنئة بقبول طلبك')
            ->assertJsonPath('data.usage.total_tokens', 2407)
            ->assertJsonPath('data.tokens_deducted', 2407);

        $assistant = Message::where('conversation_id', $conversation->id)
            ->where('role', 'assistant')
            ->firstOrFail();

        $this->assertSame("Subject: تهنئة بقبول طلبك\n\nمرحبًا، تم قبول طلبك بنجاح.", $assistant->content);
        $this->assertSame('تهنئة بقبول طلبك', $assistant->metadata['results'][0]['subject']);
        $this->assertSame($requestId, $assistant->metadata['request_id']);

        $wallet->refresh();
        $this->assertSame(593, (int) $wallet->balance);

        $costLog = CostLogger::where('conversation_id', $conversation->id)->firstOrFail();
        $this->assertSame(6, (int) $costLog->sub_tool_id);
        $this->assertSame(2407, (int) $costLog->total_tokens);
        $this->assertSame('email_writer', $costLog->model_key);
    }

    public function test_question_response_does_not_charge_wallet(): void
    {
        [$user, $conversation, $wallet] = $this->makeContext(100);
        Sanctum::actingAs($user);

        $questionState = $this->emptyState();
        $questionState['purpose'] = 'طلب معلومات';

        $writer = Mockery::mock(AiArabicWriterService::class);
        $writer->shouldReceive('generateReplyWithUsage')
            ->once()
            ->andReturn([
                'reply' => 'من فضلك حدد المستلم ونبرة الإيميل.',
                'type' => 'question',
                'state' => $questionState,
                'raw' => [
                    'success' => true,
                    'type' => 'question',
                    'tool' => 'ai_email_writer',
                    'provider' => 'openrouter',
                    'model_key' => 'email_writer',
                    'message' => 'من فضلك حدد المستلم ونبرة الإيميل.',
                    'state' => $questionState,
                    'results' => [],
                    'count' => 0,
                ],
            ]);
        $this->app->instance(AiArabicWriterService::class, $writer);

        $response = $this->apiPostJson('/api/v1/message/send', $this->payload($conversation));

        $response->assertOk()
            ->assertJsonPath('data.success', true)
            ->assertJsonPath('data.type', 'question')
            ->assertJsonPath('data.state.purpose', 'طلب معلومات')
            ->assertJsonPath('data.tokens_deducted', 0)
            ->assertJsonCount(0, 'data.results');

        $wallet->refresh();
        $this->assertSame(100, (int) $wallet->balance);
        $this->assertSame(0, CostLogger::where('conversation_id', $conversation->id)->count());
    }

    public function test_provider_failure_is_logged_and_persisted_without_charge(): void
    {
        [$user, $conversation, $wallet] = $this->makeContext(100);
        Sanctum::actingAs($user);

        $writer = Mockery::mock(AiArabicWriterService::class);
        $writer->shouldReceive('generateReplyWithUsage')
            ->once()
            ->andThrow(new \RuntimeException('Email provider unavailable'));
        $this->app->instance(AiArabicWriterService::class, $writer);

        $payload = $this->payload($conversation);
        $payload['debug'] = true;

        $response = $this->apiPostJson('/api/v1/message/send', $payload);

        $response->assertOk()
            ->assertJsonPath('data.success', false)
            ->assertJsonPath('data.type', 'error')
            ->assertJsonPath('data.message', 'Email provider unavailable')
            ->assertJsonPath('data.debug.error', 'Email provider unavailable')
            ->assertJsonPath('data.tokens_deducted', 0);

        $assistant = Message::where('conversation_id', $conversation->id)
            ->where('role', 'assistant')
            ->firstOrFail();

        $this->assertTrue($assistant->is_error);
        $this->assertSame('error', $assistant->metadata['type']);
        $this->assertSame('Email provider unavailable', $assistant->metadata['debug_error']);

        $wallet->refresh();
        $this->assertSame(100, (int) $wallet->balance);
        $this->assertSame(0, CostLogger::where('conversation_id', $conversation->id)->count());
    }

    protected function makeContext(int $walletBalance): array
    {
        $user = User::factory()->create();
        $mainTool = MainTools::create([
            'name' => 'Content Tools',
            'slug' => 'content-tools-'.Str::random(8),
        ]);
        $subTool = SubTools::create([
            'id' => 6,
            'main_tool_id' => $mainTool->id,
            'name' => 'AI Email Writer',
            'slug' => 'ai-email-writer',
            'endpoint' => 'tasks/email-writer/chat',
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
            'sub_tool_id' => 6,
            'conversation_uuid' => $conversation->uuid,
            'user_message' => 'ايميل عن مثلا تم قبول طلبك علي موقع code canyon',
            'state' => $this->emptyState(),
            'debug' => false,
        ];
    }

    protected function emptyState(): array
    {
        return [
            'purpose' => null,
            'email_type' => null,
            'recipient' => null,
            'sender_name' => null,
            'language' => null,
            'tone' => null,
            'length' => null,
            'subject_line' => null,
            'call_to_action' => null,
            'include_subject' => null,
            'extra_options' => [],
            'last_output' => null,
        ];
    }

    protected function completeState(): array
    {
        return [
            'purpose' => 'تم قبول طلبك على موقع CodeCanyon',
            'email_type' => 'General Email',
            'recipient' => 'General Recipient',
            'sender_name' => null,
            'language' => 'Arabic',
            'tone' => 'Professional',
            'length' => 'Medium',
            'subject_line' => null,
            'call_to_action' => null,
            'include_subject' => true,
            'extra_options' => ['Clear structure', 'Ready to send'],
            'last_output' => "Subject: تهنئة بقبول طلبك\n\nمرحبًا، تم قبول طلبك بنجاح.",
        ];
    }

    protected function apiPostJson(string $uri, array $payload)
    {
        return $this->withHeaders([
            'X-API-KEY' => 'testing-api-key',
        ])->postJson($uri, $payload);
    }
}
