<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\MainTools;
use App\Models\Message;
use App\Models\SubTools;
use App\Models\User;
use App\Models\Wallet;
use App\Jobs\GenerateAssistantReplyJob;
use Database\Seeders\PromptEnhancerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PromptEnhancerToolTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        putenv('API_KEY=testing-api-key');
        $_ENV['API_KEY'] = 'testing-api-key';
        $_SERVER['API_KEY'] = 'testing-api-key';

        config()->set('services.aiarabic.url', 'https://api.aiarabic.test');
        config()->set('services.aiarabic.inject_qdrant_context', false);
        config()->set('services.qdrant.url', null);
    }

    public function test_prompt_enhancer_runs_dynamically_and_cleans_its_result(): void
    {
        $rawResult = "Add a clear call\x1eto\x1eaction and define the target audience.";
        $cleanResult = 'Add a clear call-to-action and define the target audience.';

        Http::fake([
            'https://api.aiarabic.test/enhance/prompt' => Http::response([
                'success' => true,
                'type' => 'result',
                'tool' => 'ai_prompt_enhancer',
                'provider' => 'openrouter',
                'model_key' => 'prompt_enhancer',
                'message' => 'تم تحسين البرومبت بنجاح.',
                'state' => [
                    'original_prompt' => 'write an article about AI',
                ],
                'results' => [[
                    'id' => 1,
                    'text' => $rawResult,
                    'title' => null,
                    'subject' => null,
                    'meta' => [],
                ]],
                'count' => 1,
                'request_id' => 'enhancer-request-id',
                'usage' => [
                    'input_tokens' => 8,
                    'output_tokens' => 10,
                    'total_tokens' => 18,
                ],
                'cost' => [
                    'input_cost' => 0,
                    'output_cost' => 0,
                    'web_search_cost' => 0,
                    'total_cost' => 0,
                    'currency' => 'USD',
                ],
            ]),
            '*' => Http::response(['status' => 'ok']),
        ]);

        [$user, $conversation] = $this->makePromptEnhancerContext();
        Sanctum::actingAs($user);

        $response = $this->withHeaders([
            'X-API-KEY' => 'testing-api-key',
        ])->postJson('/api/v1/message/send', [
            'user_id' => $user->id,
            'sub_tool_id' => 10,
            'conversation_uuid' => $conversation->uuid,
            'user_message' => 'Improve this prompt: write an article about AI',
            'state' => $this->emptyState(),
            'debug' => false,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.conversation_id', $conversation->id)
            ->assertJsonPath('data.assistant', null);

        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();
            $state = $payload['state'] ?? [];

            return $request->url() === 'https://api.aiarabic.test/enhance/prompt'
                && ($payload['sub_tool_id'] ?? null) === 10
                && ($payload['tool'] ?? null) === 'ai_prompt_enhancer'
                && ($payload['model_key'] ?? null) === 'prompt_enhancer'
                && ($payload['provider'] ?? null) === 'openrouter'
                && str_contains((string) ($payload['system_prompt'] ?? ''), 'expert AI prompt enhancer')
                && ($state['original_prompt'] ?? null) === 'write an article about AI'
                && ($state['target_ai_tool'] ?? null) === 'Any AI model'
                && ($state['language'] ?? null) === 'Auto Detect'
                && ($state['enhancement_goal'] ?? null) === 'Make it clearer and more effective'
                && ($state['tone'] ?? null) === 'Clear and practical'
                && ($state['output_format'] ?? null) === 'Improved prompt only'
                && ($state['detail_level'] ?? null) === 'Medium'
                && ($state['preserve_intent'] ?? null) === true
                && ($state['results_count'] ?? null) === 1
                && ($state['extra_options'] ?? null) === [
                    'Improve structure',
                    'Add useful constraints',
                ]
                && ($payload['debug'] ?? null) === false;
        });

        $assistant = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('role', 'assistant')
            ->firstOrFail();

        $this->assertSame($cleanResult, $assistant->content);
        $this->assertSame($cleanResult, $assistant->metadata['results'][0]['text']);
        $this->assertSame($cleanResult, $assistant->metadata['state']['last_output']);
        $this->assertSame('write an article about AI', $assistant->metadata['state']['original_prompt']);
        $this->assertSame('ai_prompt_enhancer', $assistant->metadata['tool']);
        $this->assertSame('prompt_enhancer', $assistant->metadata['model_key']);
        $this->assertSame('openrouter', $assistant->metadata['provider']);
        $this->assertSame($user->id, $assistant->metadata['user_id']);
        $this->assertSame(10, $assistant->metadata['sub_tool_id']);
        $this->assertSame($conversation->uuid, $assistant->metadata['conversation_uuid']);
        $this->assertNull($assistant->metadata['debug']);
        $this->assertDoesNotMatchRegularExpression(
            '/(?:subToolId|sub_tool_id)[^\r\n]*(?:===|==|=>)\s*10\b/',
            file_get_contents(base_path('app/Http/Controllers/api/home/MessageController.php'))
        );

        $conversationResponse = $this->withHeaders([
            'X-API-KEY' => 'testing-api-key',
        ])->getJson("/api/v1/conversation/{$conversation->uuid}");

        $conversationResponse->assertOk();
        $assistantIndex = collect($conversationResponse->json('data.message'))
            ->search(fn (array $message): bool => ($message['role'] ?? null) === 'assistant');

        $this->assertNotFalse($assistantIndex);
        $conversationResponse
            ->assertJsonPath("data.message.{$assistantIndex}.type", 'result')
            ->assertJsonPath("data.message.{$assistantIndex}.tool", 'ai_prompt_enhancer')
            ->assertJsonPath("data.message.{$assistantIndex}.provider", 'openrouter')
            ->assertJsonPath("data.message.{$assistantIndex}.model_key", 'prompt_enhancer')
            ->assertJsonPath("data.message.{$assistantIndex}.sub_tool_id", 10)
            ->assertJsonPath("data.message.{$assistantIndex}.state.last_output", $cleanResult)
            ->assertJsonPath("data.message.{$assistantIndex}.results.0.text", $cleanResult)
            ->assertJsonPath("data.message.{$assistantIndex}.usage.total_tokens", 18)
            ->assertJsonPath("data.message.{$assistantIndex}.cost.total_cost", 0);

        $streamResponse = $this->get(
            "/api/v1/conversation/{$conversation->uuid}/stream?after_id={$response->json('data.message_id')}"
        );

        $streamResponse->assertOk();

        preg_match_all('/^data: (.+)$/m', $streamResponse->streamedContent(), $streamEvents);
        $doneEvent = collect($streamEvents[1] ?? [])
            ->map(fn (string $event): ?array => json_decode($event, true))
            ->first(fn (?array $event): bool => ($event['type'] ?? null) === 'done');

        $this->assertIsArray($doneEvent);
        $this->assertSame($cleanResult, data_get($doneEvent, 'response.results.0.text'));
        $this->assertSame($cleanResult, data_get($doneEvent, 'response.state.last_output'));
        $this->assertSame('openrouter', data_get($doneEvent, 'response.provider'));
        $this->assertSame(18, data_get($doneEvent, 'response.usage.total_tokens'));
        $this->assertSame(0, data_get($doneEvent, 'response.cost.total_cost'));
    }

    public function test_prompt_enhancer_debug_metadata_contains_payload_state_and_raw_response(): void
    {
        Http::fake([
            'https://api.aiarabic.test/enhance/prompt' => Http::response([
                'success' => true,
                'type' => 'result',
                'tool' => 'ai_prompt_enhancer',
                'model_key' => 'prompt_enhancer',
                'results' => [['id' => 1, 'text' => 'Improved prompt']],
                'state' => [],
            ]),
        ]);

        [$user, $conversation] = $this->makePromptEnhancerContext();
        Sanctum::actingAs($user);

        $this->withHeaders(['X-API-KEY' => 'testing-api-key'])
            ->postJson('/api/v1/message/send', [
                'sub_tool_id' => 10,
                'conversation_uuid' => $conversation->uuid,
                'user_message' => 'Improve this prompt: test prompt',
                'state' => [
                    'original_prompt' => null,
                    'extra_options' => [],
                ],
                'debug' => true,
            ])
            ->assertOk();

        $assistant = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('role', 'assistant')
            ->firstOrFail();

        $this->assertSame('test prompt', $assistant->metadata['debug']['payload']['state']['original_prompt']);
        $this->assertSame('test prompt', $assistant->metadata['debug']['state']['original_prompt']);
        $this->assertSame('Improved prompt', $assistant->metadata['debug']['raw_response']['results'][0]['text']);
        $this->assertArrayHasKey('usage', $assistant->metadata['debug']);
        $this->assertArrayHasKey('cost', $assistant->metadata['debug']);
    }

    public function test_structured_prompt_enhancer_response_is_preserved_when_worker_cannot_see_dynamic_config(): void
    {
        $enhancedPrompt = 'Write a comprehensive article about JWT authentication.';

        Http::fake([
            'https://api.aiarabic.test/enhance/prompt' => Http::response([
                'success' => true,
                'type' => 'result',
                'tool' => 'ai_prompt_enhancer',
                'provider' => 'openrouter',
                'model_key' => 'prompt_enhancer',
                'message' => 'تم تحسين البرومبت بنجاح.',
                'state' => [
                    'last_output' => $enhancedPrompt,
                ],
                'results' => [[
                    'id' => 1,
                    'text' => $enhancedPrompt,
                    'title' => null,
                    'subject' => null,
                    'meta' => [],
                ]],
                'count' => 1,
                'usage' => [
                    'input_tokens' => 10,
                    'output_tokens' => 20,
                    'total_tokens' => 30,
                ],
                'cost' => [
                    'total_cost' => 0.001,
                    'currency' => 'USD',
                ],
            ]),
        ]);

        [$user, $conversation] = $this->makePromptEnhancerContext();
        $conversation->subTool()->update(['config' => null]);
        Sanctum::actingAs($user);

        $sendResponse = $this->withHeaders([
            'X-API-KEY' => 'testing-api-key',
        ])->postJson('/api/v1/message/send', [
            'sub_tool_id' => 10,
            'conversation_uuid' => $conversation->uuid,
            'user_message' => 'Improve this prompt: write about JWT auth',
            'state' => $this->emptyState(),
            'debug' => false,
        ]);

        $sendResponse->assertOk();

        $assistant = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('role', 'assistant')
            ->firstOrFail();

        $this->assertSame($enhancedPrompt, $assistant->content);
        $this->assertSame('ai_prompt_enhancer', $assistant->metadata['tool']);
        $this->assertSame('openrouter', $assistant->metadata['provider']);
        $this->assertSame('prompt_enhancer', $assistant->metadata['model_key']);
        $this->assertSame($enhancedPrompt, $assistant->metadata['results'][0]['text']);
        $this->assertSame($enhancedPrompt, $assistant->metadata['state']['last_output']);

        $streamResponse = $this->get(
            "/api/v1/conversation/{$conversation->uuid}/stream?after_id={$sendResponse->json('data.message_id')}"
        );

        preg_match_all('/^data: (.+)$/m', $streamResponse->streamedContent(), $streamEvents);
        $doneEvent = collect($streamEvents[1] ?? [])
            ->map(fn (string $event): ?array => json_decode($event, true))
            ->first(fn (?array $event): bool => ($event['type'] ?? null) === 'done');

        $this->assertSame($enhancedPrompt, data_get($doneEvent, 'message.content'));
        $this->assertSame('ai_prompt_enhancer', data_get($doneEvent, 'response.tool'));
        $this->assertSame('openrouter', data_get($doneEvent, 'response.provider'));
        $this->assertSame('prompt_enhancer', data_get($doneEvent, 'response.model_key'));
        $this->assertSame($enhancedPrompt, data_get($doneEvent, 'response.results.0.text'));
        $this->assertSame($enhancedPrompt, data_get($doneEvent, 'response.state.last_output'));
        $this->assertSame(30, data_get($doneEvent, 'response.usage.total_tokens'));
        $this->assertSame(0.001, data_get($doneEvent, 'response.cost.total_cost'));
    }

    public function test_prompt_enhancer_persists_a_structured_provider_error(): void
    {
        Http::fake([
            'https://api.aiarabic.test/enhance/prompt' => Http::response([
                'success' => false,
                'type' => 'error',
                'tool' => 'ai_prompt_enhancer',
                'provider' => 'openrouter',
                'model_key' => 'prompt_enhancer',
                'message' => 'Failed to enhance prompt.',
                'error' => 'OpenRouter unavailable',
            ]),
        ]);

        [$user, $conversation] = $this->makePromptEnhancerContext();
        Sanctum::actingAs($user);

        $this->withHeaders(['X-API-KEY' => 'testing-api-key'])
            ->postJson('/api/v1/message/send', [
                'sub_tool_id' => 10,
                'conversation_uuid' => $conversation->uuid,
                'user_message' => 'Improve this prompt: test prompt',
                'state' => $this->emptyState(),
                'debug' => false,
            ])
            ->assertOk();

        $assistant = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('role', 'assistant')
            ->firstOrFail();

        $this->assertTrue((bool) $assistant->is_error);
        $this->assertSame('Failed to enhance prompt.', $assistant->content);
        $this->assertFalse($assistant->metadata['success']);
        $this->assertSame('error', $assistant->metadata['type']);
        $this->assertSame('OpenRouter unavailable', $assistant->metadata['error']);
    }

    public function test_prompt_enhancer_persists_transport_failure_after_job_exhaustion(): void
    {
        [$user, $conversation] = $this->makePromptEnhancerContext();
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'Improve this prompt: test prompt',
            'metadata' => ['state' => $this->emptyState()],
        ]);

        $job = new GenerateAssistantReplyJob(
            $message->id,
            null,
            $this->emptyState(),
            false
        );
        $job->failed(new \RuntimeException('OpenRouter transport failure'));

        $assistant = Message::query()
            ->where('reply_to_message_id', $message->id)
            ->firstOrFail();

        $this->assertTrue((bool) $assistant->is_error);
        $this->assertSame('Failed to enhance prompt.', $assistant->content);
        $this->assertSame('ai_prompt_enhancer', $assistant->metadata['tool']);
        $this->assertSame('OpenRouter transport failure', $assistant->metadata['error']);
        $this->assertSame($user->id, $assistant->metadata['user_id']);
    }

    public function test_prompt_enhancer_is_wired_to_the_prompt_tools_chat_interface(): void
    {
        $showPage = file_get_contents(resource_path('js/views/home/show.vue'));
        $chatPage = file_get_contents(resource_path('js/views/home/chat2.vue'));
        $helper = file_get_contents(resource_path('js/utils/promptEnhancerResults.js'));

        $this->assertStringContainsString(
            'const PROMPT_CHAT_SUB_TOOL_IDS = [9, 10, 11, 12];',
            $showPage
        );
        $this->assertStringContainsString(
            'PROMPT_CHAT_SUB_TOOL_IDS.includes(Number(subtool.id))',
            $showPage
        );
        $this->assertStringContainsString(
            'const PROMPT_ENHANCER_SUB_TOOL_ID = 10;',
            $chatPage
        );
        $this->assertStringContainsString(
            'sub_tool_id: activeSubToolId.value',
            $chatPage
        );
        $this->assertStringContainsString(
            'const directResponse = normalizeAssistantResponse(response);',
            $chatPage
        );
        $this->assertStringContainsString(
            'navigator.clipboard.writeText(text)',
            $chatPage
        );
        $this->assertStringContainsString(
            'export const PROMPT_ENHANCER_SUB_TOOL_ID = 10;',
            $helper
        );
    }

    private function makePromptEnhancerContext(): array
    {
        $user = User::factory()->create();
        $mainTool = MainTools::create([
            'name' => 'Prompt Tools',
            'slug' => 'prompt-tools-'.Str::random(6),
        ]);
        SubTools::create([
            'id' => 9,
            'main_tool_id' => $mainTool->id,
            'name' => 'Prompt Generator',
            'slug' => 'ai-prompt-generator',
        ]);
        $this->seed(PromptEnhancerSeeder::class);

        $conversation = Conversation::create([
            'user_id' => $user->id,
            'sub_tool_id' => 10,
            'uuid' => (string) Str::uuid(),
        ]);
        Wallet::create([
            'user_id' => $user->id,
            'uuid' => (string) Str::uuid(),
            'balance' => 1000,
        ]);

        return [$user, $conversation];
    }

    private function emptyState(): array
    {
        return [
            'original_prompt' => null,
            'target_ai_tool' => null,
            'language' => null,
            'enhancement_goal' => null,
            'tone' => null,
            'output_format' => null,
            'detail_level' => null,
            'preserve_intent' => null,
            'results_count' => null,
            'extra_options' => [],
            'last_output' => null,
        ];
    }
}
