<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\MainTools;
use App\Models\Message;
use App\Models\SubTools;
use App\Models\User;
use App\Models\Wallet;
use Database\Seeders\IdeaGeneratorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IdeaGeneratorToolTest extends TestCase
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

    public function test_idea_generator_preserves_ten_structured_results_in_database_api_and_sse(): void
    {
        $ideas = collect(range(1, 10))
            ->map(fn (int $id): array => [
                'id' => $id,
                'title' => "AI Content Idea {$id}",
                'text' => "Actionable description for AI content idea {$id}.",
                'subject' => 'AI tools',
                'meta' => [],
            ])
            ->all();

        Http::fake([
            'https://api.aiarabic.test/generate/ideas' => Http::response([
                'success' => true,
                'type' => 'result',
                'tool' => 'ai_idea_generator',
                'provider' => 'openrouter',
                'model_key' => 'idea_generator',
                'message' => 'تم توليد الأفكار بنجاح.',
                'state' => [
                    'topic' => 'AI tools',
                    'idea_type' => 'Content ideas',
                    'industry' => 'Tech',
                    'audience' => 'General Audience',
                    'language' => 'Arabic',
                    'tone' => 'Creative and useful',
                    'creativity_level' => 'Balanced',
                    'results_count' => 10,
                    'include_titles' => true,
                    'include_descriptions' => true,
                    'extra_options' => [
                        'Make ideas actionable',
                        'Avoid repetition',
                    ],
                    'last_output' => $ideas[9]['text'],
                ],
                'results' => $ideas,
                'count' => 10,
                'request_id' => 'idea-request-id',
                'usage' => [
                    'input_tokens' => 100,
                    'output_tokens' => 400,
                    'total_tokens' => 500,
                ],
                'cost' => [
                    'total_cost' => 0.002,
                    'currency' => 'USD',
                ],
            ]),
            '*' => Http::response(['status' => 'ok']),
        ]);

        [$user, $conversation] = $this->makeContext();
        Sanctum::actingAs($user);

        $sendResponse = $this->withHeaders([
            'X-API-KEY' => 'testing-api-key',
        ])->postJson('/api/v1/message/send', [
            'user_id' => $user->id,
            'sub_tool_id' => 11,
            'conversation_uuid' => $conversation->uuid,
            'user_message' => 'Give me 10 content ideas for a tech website about AI tools in arabic',
            'state' => [
                'topic' => null,
                'idea_type' => null,
                'industry' => null,
                'audience' => null,
                'language' => null,
                'tone' => null,
                'creativity_level' => null,
                'results_count' => 10,
                'include_titles' => null,
                'include_descriptions' => null,
                'extra_options' => [],
                'last_output' => null,
            ],
            'debug' => false,
        ]);

        $sendResponse->assertOk()
            ->assertJsonPath('data.conversation_id', $conversation->id)
            ->assertJsonPath('data.assistant', null);

        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();

            return $request->url() === 'https://api.aiarabic.test/generate/ideas'
                && ($payload['sub_tool_id'] ?? null) === 11
                && ($payload['tool'] ?? null) === 'ai_idea_generator'
                && ($payload['model_key'] ?? null) === 'idea_generator'
                && ($payload['provider'] ?? null) === 'openrouter'
                && ($payload['state']['results_count'] ?? null) === 10
                && ($payload['state']['extra_options'] ?? null) === [];
        });

        $assistant = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('role', 'assistant')
            ->firstOrFail();

        $this->assertCount(10, $assistant->metadata['results']);
        $this->assertSame($ideas[0], $assistant->metadata['results'][0]);
        $this->assertSame('ai_idea_generator', $assistant->metadata['tool']);
        $this->assertSame('openrouter', $assistant->metadata['provider']);
        $this->assertSame('idea_generator', $assistant->metadata['model_key']);
        $this->assertSame(10, $assistant->metadata['count']);
        $this->assertSame(10, $assistant->metadata['state']['results_count']);
        $this->assertStringContainsString($ideas[9]['text'], $assistant->metadata['state']['last_output']);
        $this->assertStringNotContainsString('تم توليد الأفكار بنجاح', $assistant->content);

        $conversationResponse = $this->withHeaders([
            'X-API-KEY' => 'testing-api-key',
        ])->getJson("/api/v1/conversation/{$conversation->uuid}");

        $assistantIndex = collect($conversationResponse->json('data.message'))
            ->search(fn (array $message): bool => ($message['role'] ?? null) === 'assistant');

        $this->assertNotFalse($assistantIndex);
        $conversationResponse
            ->assertJsonPath("data.message.{$assistantIndex}.tool", 'ai_idea_generator')
            ->assertJsonPath("data.message.{$assistantIndex}.provider", 'openrouter')
            ->assertJsonPath("data.message.{$assistantIndex}.model_key", 'idea_generator')
            ->assertJsonPath("data.message.{$assistantIndex}.results.0.title", $ideas[0]['title'])
            ->assertJsonPath("data.message.{$assistantIndex}.results.0.text", $ideas[0]['text'])
            ->assertJsonPath("data.message.{$assistantIndex}.results.0.subject", 'AI tools')
            ->assertJsonPath("data.message.{$assistantIndex}.count", 10)
            ->assertJsonPath("data.message.{$assistantIndex}.usage.total_tokens", 500)
            ->assertJsonPath("data.message.{$assistantIndex}.cost.total_cost", 0.002);

        $streamResponse = $this->get(
            "/api/v1/conversation/{$conversation->uuid}/stream?after_id={$sendResponse->json('data.message_id')}"
        );

        preg_match_all('/^data: (.+)$/m', $streamResponse->streamedContent(), $streamEvents);
        $doneEvent = collect($streamEvents[1] ?? [])
            ->map(fn (string $event): ?array => json_decode($event, true))
            ->first(fn (?array $event): bool => ($event['type'] ?? null) === 'done');

        $this->assertIsArray($doneEvent);
        $this->assertSame('ai_idea_generator', data_get($doneEvent, 'response.tool'));
        $this->assertSame('openrouter', data_get($doneEvent, 'response.provider'));
        $this->assertSame('idea_generator', data_get($doneEvent, 'response.model_key'));
        $this->assertCount(10, data_get($doneEvent, 'response.results'));
        $this->assertSame($ideas[0]['title'], data_get($doneEvent, 'response.results.0.title'));
        $this->assertSame($ideas[0]['text'], data_get($doneEvent, 'response.results.0.text'));
        $this->assertSame(10, data_get($doneEvent, 'response.state.results_count'));
        $this->assertSame(500, data_get($doneEvent, 'response.usage.total_tokens'));
        $this->assertSame(0.002, data_get($doneEvent, 'response.cost.total_cost'));

        $this->assertDoesNotMatchRegularExpression(
            '/(?:subToolId|sub_tool_id)[^\r\n]*(?:===|==|=>)\s*11\b/',
            file_get_contents(base_path('app/Http/Controllers/api/home/MessageController.php'))
        );
    }

    public function test_idea_generator_is_wired_to_chat2_with_structured_result_cards(): void
    {
        $showPage = file_get_contents(resource_path('js/views/home/show.vue'));
        $chatPage = file_get_contents(resource_path('js/views/home/chat2.vue'));
        $helper = file_get_contents(resource_path('js/utils/ideaGeneratorResults.js'));

        $this->assertStringContainsString('const PROMPT_CHAT_SUB_TOOL_IDS = [9, 10, 11, 12];', $showPage);
        $this->assertStringContainsString('const IDEA_GENERATOR_SUB_TOOL_ID = 11;', $chatPage);
        $this->assertStringContainsString('const IDEA_GENERATOR_TOOL_KEY = "ai_idea_generator";', $chatPage);
        $this->assertStringContainsString('const IDEA_GENERATOR_MODEL_KEY = "idea_generator";', $chatPage);
        $this->assertStringContainsString('createIdeaGeneratorState()', $chatPage);
        $this->assertStringContainsString('extractIdeaGeneratorResults(source, fallbackText)', $chatPage);
        $this->assertStringContainsString('debug: false', $chatPage);
        $this->assertStringContainsString('resultCardTitle(message, resultItem, index)', $chatPage);
        $this->assertStringContainsString('resultCardText(resultItem)', $chatPage);
        $this->assertStringContainsString('resultCardSubject(resultItem)', $chatPage);
        $this->assertStringContainsString('IDEA GENERATOR STREAM DEBUG', $chatPage);
        $this->assertStringContainsString('export const IDEA_GENERATOR_SUB_TOOL_ID = 11;', $helper);
    }

    private function makeContext(): array
    {
        $user = User::factory()->create();
        $mainTool = MainTools::create([
            'name' => 'Structured AI Tools',
            'slug' => 'structured-ai-tools-'.Str::random(6),
        ]);
        SubTools::create([
            'id' => 10,
            'main_tool_id' => $mainTool->id,
            'name' => 'Prompt Enhancer',
            'slug' => 'ai-prompt-enhancer',
        ]);
        $this->seed(IdeaGeneratorSeeder::class);

        $conversation = Conversation::create([
            'user_id' => $user->id,
            'sub_tool_id' => 11,
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
