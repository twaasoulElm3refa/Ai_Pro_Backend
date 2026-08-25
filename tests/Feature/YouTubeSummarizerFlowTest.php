<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\MainTools;
use App\Models\Message;
use App\Models\SubTools;
use App\Models\User;
use App\Services\YouTubeSummarizerService;
use Database\Seeders\YouTubeSummarizerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class YouTubeSummarizerFlowTest extends TestCase
{
    use RefreshDatabase;

    private const API_KEY = 'testing-public-api-key';
    private const INTERNAL_KEY = 'testing-internal-youtube-key';
    private const AI_BASE_URL = 'https://ai.internal.test';
    private const VIDEO_URL = 'https://www.youtube.com/watch?v=by12E-0i7qI';

    private string|false $previousApiKey;
    private bool $hadEnvApiKey;
    private ?string $previousEnvApiKey;
    private bool $hadServerApiKey;
    private ?string $previousServerApiKey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousApiKey = getenv('API_KEY');
        $this->hadEnvApiKey = array_key_exists('API_KEY', $_ENV);
        $this->previousEnvApiKey = $_ENV['API_KEY'] ?? null;
        $this->hadServerApiKey = array_key_exists('API_KEY', $_SERVER);
        $this->previousServerApiKey = $_SERVER['API_KEY'] ?? null;
        putenv('API_KEY='.self::API_KEY);
        $_ENV['API_KEY'] = self::API_KEY;
        $_SERVER['API_KEY'] = self::API_KEY;

        config()->set('services.aiarabic.url', self::AI_BASE_URL);
        config()->set('services.aiarabic.base_url', self::AI_BASE_URL);
        config()->set('services.aiarabic.internal_api_key', self::INTERNAL_KEY);
        config()->set('services.aiarabic.inject_qdrant_context', false);
    }

    protected function tearDown(): void
    {
        $this->previousApiKey === false
            ? putenv('API_KEY')
            : putenv('API_KEY='.$this->previousApiKey);
        $this->restoreEnvironmentValue($_ENV, $this->hadEnvApiKey, $this->previousEnvApiKey);
        $this->restoreEnvironmentValue($_SERVER, $this->hadServerApiKey, $this->previousServerApiKey);

        parent::tearDown();
    }

    public function test_seeder_creates_the_expected_subtool_and_conversation_route_resolves_it(): void
    {
        $this->seed(YouTubeSummarizerSeeder::class);
        $subTool = SubTools::findOrFail(YouTubeSummarizerService::SUB_TOOL_ID);

        $this->assertSame('youtube_summarizer', $subTool->slug);
        $this->assertSame(6, (int) $subTool->main_tool_id);
        $this->assertSame('youtube_summarizer', data_get($subTool->config, 'tool_key'));
        $this->assertSame('youtube_summarizer', data_get($subTool->config, 'model_key'));

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->withHeaders(['X-API-KEY' => self::API_KEY])
            ->postJson('/api/v1/conversation/youtube_summarizer')
            ->assertOk()
            ->assertJsonPath('data.sub_tool_id', YouTubeSummarizerService::SUB_TOOL_ID);
    }

    public function test_it_sends_the_youtube_contract_persists_metadata_and_restores_history(): void
    {
        [$user, $conversation] = $this->makeContext();
        $this->fakeSuccessfulSummary();
        Sanctum::actingAs($user);

        $response = $this->sendSummary($conversation);

        $response->assertOk()
            ->assertJsonPath('data.success', true)
            ->assertJsonPath('data.tool', 'youtube_summarizer')
            ->assertJsonPath('data.summary', "Title: Video summary\n\n- First key point")
            ->assertJsonPath('data.video_id', 'by12E-0i7qI')
            ->assertJsonPath('data.transcript_language', 'ar')
            ->assertJsonPath('data.state.summary_language', 'Arabic');

        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();

            return $request->method() === 'POST'
                && $request->url() === self::AI_BASE_URL.'/tasks/youtube-summarizer/chat'
                && $request->header('x-internal-api-key')[0] === self::INTERNAL_KEY
                && $payload['sub_tool_id'] === 25
                && $payload['tool_key'] === 'youtube_summarizer'
                && $payload['model_key'] === 'youtube_summarizer'
                && $payload['video_id'] === 'by12E-0i7qI'
                && $payload['state']['max_summary_words'] === 1000;
        });

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => "Title: Video summary\n\n- First key point",
            'is_error' => false,
        ]);

        $history = $this->withHeaders(['X-API-KEY' => self::API_KEY])
            ->getJson("/api/v1/conversation/{$conversation->uuid}");
        $history->assertOk()
            ->assertJsonPath('data.message.1.content', "Title: Video summary\n\n- First key point")
            ->assertJsonPath('data.message.1.metadata.video_id', 'by12E-0i7qI')
            ->assertJsonPath('data.message.1.metadata.transcript_segments', 12)
            ->assertJsonPath('data.message.1.metadata.state.max_summary_words', 1000);
    }

    public function test_it_accepts_supported_youtube_url_forms_and_rejects_invalid_urls(): void
    {
        foreach ([
            self::VIDEO_URL,
            'https://youtu.be/by12E-0i7qI',
            'https://www.youtube.com/shorts/by12E-0i7qI',
        ] as $url) {
            $this->assertSame('by12E-0i7qI', YouTubeSummarizerService::normalizeYouTubeUrl($url)['video_id']);
        }
        $this->assertNull(YouTubeSummarizerService::normalizeYouTubeUrl('https://example.com/watch?v=by12E-0i7qI'));

        [$user, $conversation] = $this->makeContext();
        Sanctum::actingAs($user);
        $this->sendSummary($conversation, ['user_message' => 'https://example.com/not-youtube'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user_message']);
    }

    public function test_it_prevents_cross_user_and_wrong_subtool_conversation_requests(): void
    {
        [$owner, $conversation] = $this->makeContext();
        $otherUser = User::factory()->create();
        Sanctum::actingAs($otherUser);
        $this->sendSummary($conversation)->assertForbidden();

        $mainTool = $conversation->subTool->mainTools;
        $otherSubTool = SubTools::create([
            'main_tool_id' => $mainTool->id,
            'name' => 'Other Tool '.Str::random(6),
            'slug' => 'other-tool-'.Str::random(6),
        ]);
        $wrongConversation = Conversation::create([
            'user_id' => $owner->id,
            'sub_tool_id' => $otherSubTool->id,
            'uuid' => (string) Str::uuid(),
        ]);
        Sanctum::actingAs($owner);
        $this->sendSummary($wrongConversation)
            ->assertStatus(422)
            ->assertJsonPath('errors.sub_tool_id.0', 'The selected tool does not match this conversation.');
    }

    public function test_it_is_idempotent(): void
    {
        [$user, $conversation] = $this->makeContext();
        $this->fakeSuccessfulSummary();
        Sanctum::actingAs($user);
        $idempotencyKey = (string) Str::uuid();

        $this->sendSummary($conversation, ['idempotency_key' => $idempotencyKey])->assertOk();
        $this->sendSummary($conversation, ['idempotency_key' => $idempotencyKey])->assertOk();
        Http::assertSentCount(1);
        $this->assertSame(1, Message::query()->where('conversation_id', $conversation->id)->where('role', 'user')->count());
        $this->assertSame(1, Message::query()->where('conversation_id', $conversation->id)->where('role', 'assistant')->count());

    }

    public function test_it_persists_a_provider_failure_as_a_retryable_error(): void
    {
        [$user, $failureConversation] = $this->makeContext();
        Sanctum::actingAs($user);
        Http::fake([
            self::AI_BASE_URL.'/tasks/youtube-summarizer/chat' => Http::response([
                'success' => false,
                'type' => 'error',
                'message' => 'Transcript unavailable.',
            ]),
        ]);

        $this->sendSummary($failureConversation)
            ->assertOk()
            ->assertJsonPath('data.success', false)
            ->assertJsonPath('data.type', 'error');
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $failureConversation->id,
            'role' => 'assistant',
            'is_error' => true,
        ]);
    }

    private function unusedFailureConversation(User $user): Conversation
    {
        return Conversation::create([
            'user_id' => $user->id,
            'sub_tool_id' => YouTubeSummarizerService::SUB_TOOL_ID,
            'uuid' => (string) Str::uuid(),
        ]);
    }

    private function makeContext(): array
    {
        $this->seed(YouTubeSummarizerSeeder::class);
        $user = User::factory()->create();
        $conversation = Conversation::create([
            'user_id' => $user->id,
            'sub_tool_id' => YouTubeSummarizerService::SUB_TOOL_ID,
            'uuid' => (string) Str::uuid(),
        ]);

        return [$user, $conversation];
    }

    private function sendSummary(Conversation $conversation, array $overrides = [])
    {
        return $this->withHeaders(['X-API-KEY' => self::API_KEY])
            ->postJson('/api/v1/message/send', array_replace_recursive([
                'sub_tool_id' => YouTubeSummarizerService::SUB_TOOL_ID,
                'conversation_uuid' => $conversation->uuid,
                'user_message' => self::VIDEO_URL,
                'tool' => YouTubeSummarizerService::TOOL_KEY,
                'tool_key' => YouTubeSummarizerService::TOOL_KEY,
                'model_key' => YouTubeSummarizerService::MODEL_KEY,
                'task_key' => YouTubeSummarizerService::TOOL_KEY,
                'regenerate' => false,
                'idempotency_key' => (string) Str::uuid(),
                'debug' => false,
                'state' => [
                    'transcript_languages' => ['ar', 'en'],
                    'summary_language' => 'Arabic',
                    'summary_style' => 'structured summary with a headline and key points',
                    'max_summary_words' => 1000,
                    'extra_options' => [],
                    'last_output' => null,
                ],
            ], $overrides));
    }

    private function fakeSuccessfulSummary(): void
    {
        Http::fake([
            self::AI_BASE_URL.'/tasks/youtube-summarizer/chat' => Http::response([
                'success' => true,
                'type' => 'result',
                'tool' => 'youtube_summarizer',
                'provider' => 'supadata+openrouter',
                'model_key' => 'youtube_summarizer',
                'summary' => "Title: Video summary\n\n- First key point",
                'video_id' => 'by12E-0i7qI',
                'transcript_language' => 'ar',
                'transcript_chars' => 93,
                'transcript_segments' => 12,
                'transcript_is_generated' => false,
                'request_id' => 'provider-request-id',
                'usage' => [
                    'input_tokens' => 321,
                    'output_tokens' => 91,
                    'total_tokens' => 412,
                ],
                'cost' => [
                    'input_cost' => 0.0001605,
                    'output_cost' => 0.0000637,
                    'web_search_cost' => 0,
                    'total_cost' => 0.0002242,
                    'currency' => 'USD',
                ],
            ]),
        ]);
    }

    private function restoreEnvironmentValue(array &$environment, bool $existed, ?string $value): void
    {
        if ($existed) {
            $environment['API_KEY'] = $value;

            return;
        }

        unset($environment['API_KEY']);
    }
}
