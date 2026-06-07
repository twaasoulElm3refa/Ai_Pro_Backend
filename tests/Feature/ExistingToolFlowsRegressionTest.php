<?php

namespace Tests\Feature;

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

class ExistingToolFlowsRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        putenv('API_KEY=testing-api-key');
        $_ENV['API_KEY'] = 'testing-api-key';
        $_SERVER['API_KEY'] = 'testing-api-key';
    }

    public function test_paraphraser_tool_still_returns_and_persists_results(): void
    {
        [$user, $conversation] = $this->makeContext(
            3,
            'AI Paraphraser',
            'ai-paraphraser',
            'tasks/paraphraser/chat'
        );
        Sanctum::actingAs($user);

        $writer = Mockery::mock(AiArabicWriterService::class);
        $writer->shouldReceive('generateReplyWithUsage')
            ->once()
            ->withArgs(fn (array $payload, string $endpoint): bool => $endpoint === 'tasks/paraphraser/chat'
                && $payload['sub_tool_id'] === 3
                && $payload['state']['content'] === 'النص الأصلي')
            ->andReturn([
                'reply' => 'تمت إعادة الصياغة.',
                'provider' => 'openrouter',
                'model_key' => 'paraphraser',
                'raw' => [
                    'success' => true,
                    'message' => 'تمت إعادة الصياغة بنجاح.',
                    'results' => [
                        ['id' => 1, 'text' => 'النص بعد إعادة الصياغة'],
                    ],
                ],
            ]);
        $this->app->instance(AiArabicWriterService::class, $writer);

        $response = $this->apiPostJson('/api/v1/message/send', [
            'sub_tool_id' => 3,
            'conversation_uuid' => $conversation->uuid,
            'user_message' => 'أعد صياغة النص بأسلوب رسمي',
            'state' => [
                'content' => 'النص الأصلي',
                'language' => 'Arabic',
                'tone' => 'Formal',
                'rewrite_mode' => 'Rewrite',
                'change_level' => 'Medium',
                'results_count' => 1,
                'extra_options' => [],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.success', true)
            ->assertJsonPath('data.sub_tool_id', 3)
            ->assertJsonPath('data.results.0.text', 'النص بعد إعادة الصياغة');

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'النص بعد إعادة الصياغة',
        ]);
    }

    public function test_headline_generator_tool_still_returns_and_persists_results(): void
    {
        [$user, $conversation] = $this->makeContext(
            4,
            'AI Headline Generator',
            'ai-headline-generator',
            'tasks/headline-generator/chat'
        );
        Sanctum::actingAs($user);

        $state = [
            'content' => 'إطلاق منتج ذكاء اصطناعي',
            'content_type' => 'Article',
            'goal' => 'Engagement',
            'language' => 'Arabic',
            'tone' => 'Professional',
            'number_of_headlines' => 2,
            'headline_length' => 'Medium',
            'extra_options' => [],
        ];

        $writer = Mockery::mock(AiArabicWriterService::class);
        $writer->shouldReceive('generateReplyWithUsage')
            ->once()
            ->withArgs(fn (array $payload, string $endpoint): bool => $endpoint === 'tasks/headline-generator/chat'
                && $payload['sub_tool_id'] === 4
                && $payload['state'] === $state)
            ->andReturn([
                'reply' => 'تم توليد العناوين بنجاح.',
                'type' => 'result',
                'tool' => 'ai_headline_generator',
                'provider' => 'openrouter',
                'model_key' => 'headline_generator',
                'state' => $state,
                'headlines' => [
                    ['id' => 1, 'text' => 'عنوان أول', 'subheadline' => null],
                    ['id' => 2, 'text' => 'عنوان ثان', 'subheadline' => null],
                ],
                'raw' => [
                    'type' => 'result',
                    'message' => 'تم توليد العناوين بنجاح.',
                    'count' => 2,
                ],
            ]);
        $this->app->instance(AiArabicWriterService::class, $writer);

        $response = $this->apiPostJson('/api/v1/message/send', [
            'sub_tool_id' => 4,
            'conversation_uuid' => $conversation->uuid,
            'user_message' => 'اكتب عنوانين عن إطلاق منتج ذكاء اصطناعي',
            'state' => $state,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.success', true)
            ->assertJsonPath('data.sub_tool_id', 4)
            ->assertJsonPath('data.headlines.0.text', 'عنوان أول')
            ->assertJsonPath('data.headlines.1.text', 'عنوان ثان');

        $assistant = Message::where('conversation_id', $conversation->id)
            ->where('role', 'assistant')
            ->firstOrFail();

        $this->assertSame(4, $assistant->metadata['sub_tool_id']);
        $this->assertCount(2, $assistant->metadata['headlines']);
    }

    private function makeContext(int $subToolId, string $name, string $slug, string $endpoint): array
    {
        $user = User::factory()->create();
        $mainTool = MainTools::create([
            'name' => 'Content Tools',
            'slug' => 'content-tools-'.Str::random(8),
        ]);
        $subTool = SubTools::create([
            'id' => $subToolId,
            'main_tool_id' => $mainTool->id,
            'name' => $name,
            'slug' => $slug,
            'endpoint' => $endpoint,
        ]);
        $conversation = Conversation::create([
            'user_id' => $user->id,
            'sub_tool_id' => $subTool->id,
            'uuid' => (string) Str::uuid(),
        ]);
        Wallet::create([
            'user_id' => $user->id,
            'uuid' => (string) Str::uuid(),
            'balance' => 100,
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
