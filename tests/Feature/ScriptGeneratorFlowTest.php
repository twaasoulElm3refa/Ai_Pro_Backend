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

class ScriptGeneratorFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        putenv('API_KEY=testing-api-key');
        $_ENV['API_KEY'] = 'testing-api-key';
        $_SERVER['API_KEY'] = 'testing-api-key';
    }

    public function test_postman_payload_infers_script_state_and_persists_two_results(): void
    {
        [$user, $conversation, $wallet] = $this->makeContext(3000);
        Sanctum::actingAs($user);

        $requestId = (string) Str::uuid();
        $resultState = $this->completeState();
        $writer = Mockery::mock(AiArabicWriterService::class);
        $writer->shouldReceive('generateReplyWithUsage')
            ->once()
            ->withArgs(function (array $payload, string $endpoint): bool {
                $state = $payload['state'];

                return $endpoint === 'tasks/script-generator/chat'
                    && $payload['sub_tool_id'] === 7
                    && $payload['tool'] === 'ai_script_generator'
                    && $payload['model_key'] === 'script_generator'
                    && $state['topic'] === 'أهمية الذكاء الاصطناعي في التسويق'
                    && $state['script_type'] === 'Video Script'
                    && $state['platform'] === 'General Video'
                    && $state['language'] === 'Arabic'
                    && $state['tone'] === 'Engaging'
                    && $state['audience'] === 'General Audience'
                    && $state['duration'] === '60 seconds'
                    && $state['format'] === 'Hook + Body + CTA'
                    && $state['include_scene_notes'] === false
                    && $state['results_count'] === 2
                    && ! array_key_exists('task_options', $payload)
                    && str_contains($payload['system_prompt'], 'strong hook')
                    && str_contains($payload['system_prompt'], 'include_scene_notes');
            })
            ->andReturn([
                'reply' => 'تم توليد السكريبت بنجاح.',
                'type' => 'result',
                'provider' => 'openrouter',
                'model_key' => 'script_generator',
                'request_id' => $requestId,
                'state' => $resultState,
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
                    'tool' => 'ai_script_generator',
                    'provider' => 'openrouter',
                    'model_key' => 'script_generator',
                    'request_id' => $requestId,
                    'message' => 'تم توليد السكريبت بنجاح.',
                    'state' => $resultState,
                    'results' => [
                        [
                            'id' => 1,
                            'text' => 'السكريبت النهائي الأول',
                            'title' => null,
                            'subject' => null,
                            'meta' => [],
                        ],
                        [
                            'id' => 2,
                            'text' => 'السكريبت النهائي الثاني',
                            'title' => null,
                            'subject' => null,
                            'meta' => [],
                        ],
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

        $response = $this->apiPostJson('/api/v1/message/send', $this->payload($conversation));

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.success', true)
            ->assertJsonPath('data.type', 'result')
            ->assertJsonPath('data.tool', 'ai_script_generator')
            ->assertJsonPath('data.model_key', 'script_generator')
            ->assertJsonPath('data.results.0.text', 'السكريبت النهائي الأول')
            ->assertJsonPath('data.results.1.text', 'السكريبت النهائي الثاني')
            ->assertJsonPath('data.usage.total_tokens', 30)
            ->assertJsonPath('data.cost.currency', 'USD')
            ->assertJsonPath('data.tokens_deducted', 30)
            ->assertJsonCount(2, 'data.results');

        $assistant = Message::where('conversation_id', $conversation->id)
            ->where('role', 'assistant')
            ->firstOrFail();

        $this->assertSame(
            "السكريبت النهائي الأول\n\nالسكريبت النهائي الثاني",
            $assistant->content
        );
        $this->assertSame('result', $assistant->metadata['type']);
        $this->assertSame('ai_script_generator', $assistant->metadata['tool']);
        $this->assertSame(7, $assistant->metadata['sub_tool_id']);
        $this->assertSame('script_generator', $assistant->metadata['model_key']);
        $this->assertSame($requestId, $assistant->metadata['request_id']);
        $this->assertCount(2, $assistant->metadata['results']);

        $wallet->refresh();
        $this->assertSame(2970, (int) $wallet->balance);

        $costLog = CostLogger::where('conversation_id', $conversation->id)->firstOrFail();
        $this->assertSame(7, (int) $costLog->sub_tool_id);
        $this->assertSame(30, (int) $costLog->total_tokens);
        $this->assertSame('script_generator', $costLog->model_key);
    }

    public function test_incomplete_script_state_returns_question_without_charging_wallet(): void
    {
        [$user, $conversation, $wallet] = $this->makeContext(100);
        Sanctum::actingAs($user);

        $questionState = $this->emptyState();
        $questionState['topic'] = 'التسويق';
        $questionState['script_type'] = 'Video Script';
        $questionState['language'] = 'Arabic';
        $questionState['tone'] = 'Engaging';
        $questionState['audience'] = 'General Audience';
        $questionState['format'] = 'Hook + Body + CTA';
        $questionState['include_scene_notes'] = false;
        $questionState['extra_options'] = ['Strong opening hook', 'Clear ending'];

        $writer = Mockery::mock(AiArabicWriterService::class);
        $writer->shouldReceive('generateReplyWithUsage')
            ->once()
            ->withArgs(function (array $payload): bool {
                return $payload['state']['topic'] === 'التسويق'
                    && $payload['state']['platform'] === null
                    && $payload['state']['duration'] === null;
            })
            ->andReturn([
                'reply' => 'من فضلك حدد المنصة ومدة السكريبت.',
                'type' => 'question',
                'state' => $questionState,
                'raw' => [
                    'success' => true,
                    'type' => 'question',
                    'tool' => 'ai_script_generator',
                    'provider' => 'openrouter',
                    'model_key' => 'script_generator',
                    'message' => 'من فضلك حدد المنصة ومدة السكريبت.',
                    'state' => $questionState,
                    'results' => [],
                    'count' => 0,
                ],
            ]);
        $this->app->instance(AiArabicWriterService::class, $writer);

        $payload = $this->payload($conversation);
        $payload['user_message'] = 'اكتب سكريبت عن التسويق';

        $response = $this->apiPostJson('/api/v1/message/send', $payload);

        $response->assertOk()
            ->assertJsonPath('data.success', true)
            ->assertJsonPath('data.type', 'question')
            ->assertJsonPath('data.state.topic', 'التسويق')
            ->assertJsonPath('data.state.platform', null)
            ->assertJsonPath('data.state.duration', null)
            ->assertJsonPath('data.tokens_deducted', 0)
            ->assertJsonPath('data.usage', null)
            ->assertJsonPath('data.cost', null)
            ->assertJsonCount(0, 'data.results');

        $assistant = Message::where('conversation_id', $conversation->id)
            ->where('role', 'assistant')
            ->firstOrFail();

        $this->assertSame('question', $assistant->metadata['type']);
        $this->assertSame('من فضلك حدد المنصة ومدة السكريبت.', $assistant->content);

        $wallet->refresh();
        $this->assertSame(100, (int) $wallet->balance);
        $this->assertSame(0, CostLogger::where('conversation_id', $conversation->id)->count());
    }

    public function test_empty_result_is_not_charged_or_treated_as_a_ready_script(): void
    {
        [$user, $conversation, $wallet] = $this->makeContext(100);
        Sanctum::actingAs($user);

        $writer = Mockery::mock(AiArabicWriterService::class);
        $writer->shouldReceive('generateReplyWithUsage')
            ->once()
            ->andReturn([
                'reply' => 'No script was generated.',
                'type' => 'result',
                'state' => $this->completeState(),
                'usage' => [
                    'input_tokens' => 20,
                    'output_tokens' => 0,
                    'total_tokens' => 20,
                ],
                'raw' => [
                    'success' => true,
                    'type' => 'result',
                    'message' => 'No script was generated.',
                    'state' => $this->completeState(),
                    'results' => [],
                    'count' => 0,
                ],
            ]);
        $this->app->instance(AiArabicWriterService::class, $writer);

        $response = $this->apiPostJson('/api/v1/message/send', $this->payload($conversation));

        $response->assertOk()
            ->assertJsonPath('data.success', false)
            ->assertJsonPath('data.type', 'error')
            ->assertJsonPath('data.tokens_deducted', 0)
            ->assertJsonCount(0, 'data.results');

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
            'id' => 7,
            'main_tool_id' => $mainTool->id,
            'name' => 'Script Generator',
            'slug' => 'script-generator',
            'endpoint' => 'tasks/script-generator/chat',
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
            'sub_tool_id' => 7,
            'conversation_uuid' => $conversation->uuid,
            'user_message' => 'اكتب سكريبت فيديو قصير مدته 60 ثانية عن أهمية الذكاء الاصطناعي في التسويق',
            'state' => $this->emptyState(),
            'debug' => false,
        ];
    }

    protected function emptyState(): array
    {
        return [
            'topic' => null,
            'script_type' => null,
            'platform' => null,
            'language' => null,
            'tone' => null,
            'audience' => null,
            'duration' => null,
            'format' => null,
            'include_scene_notes' => null,
            'results_count' => 2,
            'extra_options' => [],
            'last_output' => null,
        ];
    }

    protected function completeState(): array
    {
        return [
            'topic' => 'أهمية الذكاء الاصطناعي في التسويق',
            'script_type' => 'Video Script',
            'platform' => 'General Video',
            'language' => 'Arabic',
            'tone' => 'Engaging',
            'audience' => 'General Audience',
            'duration' => '60 seconds',
            'format' => 'Hook + Body + CTA',
            'include_scene_notes' => false,
            'results_count' => 2,
            'extra_options' => ['Strong opening hook', 'Clear ending'],
            'last_output' => 'السكريبت النهائي الثاني',
        ];
    }

    protected function apiPostJson(string $uri, array $payload)
    {
        return $this->withHeaders([
            'X-API-KEY' => 'testing-api-key',
        ])->postJson($uri, $payload);
    }
}
