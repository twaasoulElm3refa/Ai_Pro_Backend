<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\MainTools;
use App\Models\Message;
use App\Models\SubTools;
use App\Models\User;
use App\Models\Wallet;
use Database\Seeders\HookGeneratorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HookGeneratorToolTest extends TestCase
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

    public function test_hook_generator_runs_dynamically_and_preserves_ten_results_in_api_and_sse(): void
    {
        $hooks = collect(range(1, 10))
            ->map(fn (int $id): array => [
                'id' => $id,
                'text' => "LinkedIn hook {$id}",
                'title' => null,
                'subject' => null,
                'meta' => [],
            ])
            ->all();

        Http::fake([
            'https://api.aiarabic.test/generate/hooks' => Http::response([
                'success' => true,
                'type' => 'result',
                'tool' => 'ai_hook_generator',
                'provider' => 'openrouter',
                'model_key' => 'hook_generator',
                'message' => 'Hooks generated successfully.',
                'state' => [
                    'topic' => 'AI changing content marketing',
                    'platform' => 'LinkedIn',
                    'content_type' => 'Social post or video',
                    'language' => 'Arabic',
                    'tone' => 'Engaging',
                    'audience' => 'General Audience',
                    'hook_style' => 'Scroll-stopping',
                    'length' => 'Short',
                    'results_count' => 10,
                    'extra_options' => [
                        'Make every hook distinct',
                        'Avoid misleading clickbait',
                    ],
                    'last_output' => null,
                ],
                'results' => $hooks,
                'count' => 10,
                'request_id' => 'hook-request-id',
                'usage' => [
                    'input_tokens' => 100,
                    'output_tokens' => 300,
                    'total_tokens' => 400,
                ],
                'cost' => [
                    'input_cost' => 0.0001,
                    'output_cost' => 0.0003,
                    'web_search_cost' => 0,
                    'total_cost' => 0.0004,
                    'currency' => 'USD',
                ],
            ]),
            '*' => Http::response(['status' => 'ok']),
        ]);

        [$user, $conversation] = $this->makeContext();
        Sanctum::actingAs($user);

        $response = $this->withHeaders([
            'X-API-KEY' => 'testing-api-key',
        ])->postJson('/api/v1/message/send', [
            'user_id' => $user->id,
            'sub_tool_id' => 12,
            'conversation_uuid' => $conversation->uuid,
            'user_message' => 'Generate 10 strong hooks for a LinkedIn post about AI changing content marketing in arabic',
            'state' => [
                'topic' => null,
                'platform' => null,
                'content_type' => null,
                'language' => null,
                'tone' => null,
                'audience' => null,
                'hook_style' => null,
                'length' => null,
                'results_count' => null,
                'extra_options' => [],
                'last_output' => null,
            ],
            'debug' => false,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.conversation_id', $conversation->id);

        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();

            return $request->url() === 'https://api.aiarabic.test/generate/hooks'
                && ($payload['sub_tool_id'] ?? null) === 12
                && ($payload['tool'] ?? null) === 'ai_hook_generator'
                && ($payload['model_key'] ?? null) === 'hook_generator'
                && ($payload['provider'] ?? null) === 'openrouter'
                && ($payload['response_format'] ?? null) === 'results'
                && ($payload['normalize_results'] ?? null) === true
                && str_contains((string) ($payload['system_prompt'] ?? ''), 'valid JSON only')
                && array_key_exists('results_count', $payload['state'] ?? [])
                && ($payload['state']['extra_options'] ?? null) === [];
        });

        $assistant = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('role', 'assistant')
            ->firstOrFail();

        $this->assertSame('ai_hook_generator', $assistant->metadata['tool']);
        $this->assertSame('openrouter', $assistant->metadata['provider']);
        $this->assertSame('hook_generator', $assistant->metadata['model_key']);
        $this->assertCount(10, $assistant->metadata['results']);
        $this->assertSame($hooks[0], $assistant->metadata['results'][0]);
        $this->assertSame($hooks[0]['text'], $assistant->metadata['state']['last_output']);
        $this->assertSame(10, $assistant->metadata['count']);
        $this->assertSame('hook-request-id', $assistant->metadata['request_id']);
        $this->assertStringNotContainsString('Hooks generated successfully.', $assistant->content);

        $streamResponse = $this->get(
            "/api/v1/conversation/{$conversation->uuid}/stream?after_id={$response->json('data.message_id')}"
        );

        preg_match_all('/^data: (.+)$/m', $streamResponse->streamedContent(), $events);
        $doneEvent = collect($events[1] ?? [])
            ->map(fn (string $event): ?array => json_decode($event, true))
            ->first(fn (?array $event): bool => ($event['type'] ?? null) === 'done');

        $this->assertIsArray($doneEvent);
        $this->assertSame('ai_hook_generator', data_get($doneEvent, 'response.tool'));
        $this->assertSame('openrouter', data_get($doneEvent, 'response.provider'));
        $this->assertSame('hook_generator', data_get($doneEvent, 'response.model_key'));
        $this->assertSame('hook-request-id', data_get($doneEvent, 'response.request_id'));
        $this->assertSame($user->id, data_get($doneEvent, 'response.user_id'));
        $this->assertSame(12, data_get($doneEvent, 'response.sub_tool_id'));
        $this->assertSame($conversation->uuid, data_get($doneEvent, 'response.conversation_uuid'));
        $this->assertCount(10, data_get($doneEvent, 'response.results'));
        $this->assertSame($hooks[0]['text'], data_get($doneEvent, 'response.results.0.text'));
        $this->assertSame($hooks[0]['text'], data_get($doneEvent, 'response.state.last_output'));
        $this->assertSame(10, data_get($doneEvent, 'response.count'));
        $this->assertSame(400, data_get($doneEvent, 'response.usage.total_tokens'));
        $this->assertSame(0.0004, data_get($doneEvent, 'response.cost.total_cost'));

        $this->assertDoesNotMatchRegularExpression(
            '/(?:subToolId|sub_tool_id)[^\r\n]*(?:===|==|=>)\s*12\b/',
            file_get_contents(base_path('app/Http/Controllers/api/home/MessageController.php'))
        );
    }

    public function test_hook_generator_config_validates_state_and_frontend_uses_chat2(): void
    {
        [$user, $conversation] = $this->makeContext();
        Sanctum::actingAs($user);

        $this->withHeaders([
            'X-API-KEY' => 'testing-api-key',
        ])->postJson('/api/v1/message/send', [
            'sub_tool_id' => 12,
            'conversation_uuid' => $conversation->uuid,
            'user_message' => 'Generate hooks',
            'state' => [
                'results_count' => 21,
                'extra_options' => ['Valid option'],
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('state.results_count');

        $showPage = file_get_contents(resource_path('js/views/home/show.vue'));
        $chatPage = file_get_contents(resource_path('js/views/home/chat2.vue'));
        $helper = file_get_contents(resource_path('js/utils/hookGeneratorResults.js'));

        $this->assertStringContainsString('const PROMPT_CHAT_SUB_TOOL_IDS = [9, 10, 11, 12];', $showPage);
        $this->assertStringContainsString('const HOOK_GENERATOR_SUB_TOOL_ID = 12;', $chatPage);
        $this->assertStringContainsString('createHookGeneratorState()', $chatPage);
        $this->assertStringContainsString('extractHookGeneratorResults(source, fallbackText)', $chatPage);
        $this->assertStringContainsString('export const HOOK_GENERATOR_SUB_TOOL_ID = 12;', $helper);
    }

    public function test_hook_generator_parses_json_returned_inside_openrouter_message_content(): void
    {
        Http::fake([
            'https://api.aiarabic.test/generate/hooks' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'state' => [
                                'topic' => 'Authentication',
                                'results_count' => 2,
                                'last_output' => '',
                            ],
                            'results' => [
                                ['id' => 1, 'text' => 'Your auth flow may be your biggest security risk.'],
                                ['id' => 2, 'text' => 'JWT is simple until one missing check breaks everything.'],
                            ],
                        ], JSON_UNESCAPED_UNICODE),
                    ],
                ]],
            ]),
            '*' => Http::response(['status' => 'ok']),
        ]);

        [$user, $conversation] = $this->makeContext();
        Sanctum::actingAs($user);

        $this->withHeaders([
            'X-API-KEY' => 'testing-api-key',
        ])->postJson('/api/v1/message/send', [
            'sub_tool_id' => 12,
            'conversation_uuid' => $conversation->uuid,
            'user_message' => 'Generate two hooks about authentication.',
            'state' => [
                'results_count' => 2,
                'extra_options' => [],
            ],
        ])->assertOk();

        $assistant = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('role', 'assistant')
            ->firstOrFail();

        $this->assertCount(2, $assistant->metadata['results']);
        $this->assertSame(
            'Your auth flow may be your biggest security risk.',
            $assistant->metadata['results'][0]['text']
        );
        $this->assertNull($assistant->metadata['results'][0]['title']);
        $this->assertNull($assistant->metadata['results'][0]['subject']);
        $this->assertSame([], $assistant->metadata['results'][0]['meta']);
        $this->assertSame(
            'Your auth flow may be your biggest security risk.',
            $assistant->metadata['state']['last_output']
        );
    }

    private function makeContext(): array
    {
        $user = User::factory()->create();
        $mainTool = MainTools::create([
            'name' => 'Structured AI Tools',
            'slug' => 'structured-ai-tools-'.Str::random(6),
        ]);
        SubTools::create([
            'id' => 11,
            'main_tool_id' => $mainTool->id,
            'name' => 'Idea Generator',
            'slug' => 'ai-idea-generator',
        ]);
        $this->seed(HookGeneratorSeeder::class);

        $conversation = Conversation::create([
            'user_id' => $user->id,
            'sub_tool_id' => 12,
            'uuid' => (string) Str::uuid(),
        ]);
        Wallet::create([
            'user_id' => $user->id,
            'uuid' => (string) Str::uuid(),
            'balance' => 10000,
        ]);

        return [$user, $conversation];
    }
}
