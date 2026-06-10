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

class ProductDescriptionGeneratorFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        putenv('API_KEY=testing-api-key');
        $_ENV['API_KEY'] = 'testing-api-key';
        $_SERVER['API_KEY'] = 'testing-api-key';
    }

    public function test_product_description_payload_applies_defaults_and_persists_result(): void
    {
        [$user, $conversation, $wallet] = $this->makeContext(500);
        Sanctum::actingAs($user);

        $output = "سماعة لاسلكية تمنحك صوتًا نقيًا أينما ذهبت.\n\n• إلغاء فعال للضوضاء\n• بطارية تدوم طويلًا";
        $requestId = (string) Str::uuid();
        $writer = Mockery::mock(AiArabicWriterService::class);
        $writer->shouldReceive('generateReplyWithUsage')
            ->once()
            ->withArgs(function (array $payload, string $endpoint): bool {
                $state = $payload['state'];

                return $endpoint === 'tasks/product-description-generator/chat'
                    && $payload['sub_tool_id'] === 8
                    && $payload['tool'] === 'ai_product_description_generator'
                    && $payload['model_key'] === 'product_description_generator'
                    && $state['product'] === 'سماعة لاسلكية'
                    && $state['product_features'] === 'إلغاء الضوضاء وبطارية تدوم طويلًا'
                    && $state['target_audience'] === 'General Customers'
                    && $state['language'] === 'Arabic'
                    && $state['tone'] === 'Professional'
                    && $state['length'] === 'Medium'
                    && $state['platform'] === 'Website / Store'
                    && $state['include_bullets'] === true
                    && $state['include_seo_keywords'] === true
                    && $state['extra_options'] === ['Benefit-focused', 'Clear and persuasive']
                    && str_contains($payload['system_prompt'], 'results[0].text');
            })
            ->andReturn([
                'reply' => 'تم توليد وصف المنتج بنجاح.',
                'type' => 'result',
                'provider' => 'openrouter',
                'model_key' => 'product_description_generator',
                'request_id' => $requestId,
                'state' => $this->completeState(),
                'usage' => [
                    'input_tokens' => 20,
                    'output_tokens' => 30,
                    'total_tokens' => 50,
                ],
                'cost' => [
                    'input_cost' => 0.00002,
                    'output_cost' => 0.00003,
                    'web_search_cost' => 0,
                    'total_cost' => 0.00005,
                    'currency' => 'USD',
                ],
                'raw' => [
                    'success' => true,
                    'type' => 'result',
                    'tool' => 'ai_product_description_generator',
                    'provider' => 'openrouter',
                    'model_key' => 'product_description_generator',
                    'request_id' => $requestId,
                    'message' => 'تم توليد وصف المنتج بنجاح.',
                    'state' => $this->completeState(),
                    'results' => [[
                        'id' => 1,
                        'text' => $output,
                        'title' => null,
                        'subject' => null,
                        'meta' => [],
                    ]],
                    'count' => 1,
                    'usage' => [
                        'input_tokens' => 20,
                        'output_tokens' => 30,
                        'total_tokens' => 50,
                    ],
                    'cost' => [
                        'input_cost' => 0.00002,
                        'output_cost' => 0.00003,
                        'web_search_cost' => 0,
                        'total_cost' => 0.00005,
                        'currency' => 'USD',
                    ],
                ],
            ]);
        $this->app->instance(AiArabicWriterService::class, $writer);

        $response = $this->apiPostJson('/api/v1/message/send', [
            'user_id' => $user->id,
            'sub_tool_id' => 8,
            'conversation_uuid' => $conversation->uuid,
            'user_message' => 'اكتب وصف منتج احترافي لسماعة لاسلكية تدعم إلغاء الضوضاء وبطارية تدوم طويلًا',
            'tool' => 'ai_product_description_generator',
            'model_key' => 'product_description_generator',
            'state' => $this->emptyState(),
            'debug' => false,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.success', true)
            ->assertJsonPath('data.type', 'result')
            ->assertJsonPath('data.tool', 'ai_product_description_generator')
            ->assertJsonPath('data.model_key', 'product_description_generator')
            ->assertJsonPath('data.sub_tool_id', 8)
            ->assertJsonPath('data.results.0.text', $output)
            ->assertJsonPath('data.state.last_output', $output)
            ->assertJsonPath('data.count', 1)
            ->assertJsonPath('data.usage.total_tokens', 50);

        $assistant = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('role', 'assistant')
            ->firstOrFail();

        $this->assertSame($output, $assistant->content);
        $this->assertSame($output, $assistant->metadata['state']['last_output']);
        $this->assertSame('ai_product_description_generator', $assistant->metadata['tool']);
        $this->assertSame('product_description_generator', $assistant->metadata['model_key']);

        $wallet->refresh();
        $this->assertSame(450, (int) $wallet->balance);

        $costLog = CostLogger::query()
            ->where('conversation_id', $conversation->id)
            ->firstOrFail();
        $this->assertSame(8, (int) $costLog->sub_tool_id);
        $this->assertSame('product_description_generator', $costLog->model_key);
    }

    public function test_product_description_requires_message_or_product_name(): void
    {
        [$user, $conversation] = $this->makeContext(100);
        Sanctum::actingAs($user);

        $writer = Mockery::mock(AiArabicWriterService::class);
        $writer->shouldNotReceive('generateReplyWithUsage');
        $this->app->instance(AiArabicWriterService::class, $writer);

        $response = $this->apiPostJson('/api/v1/message/send', [
            'user_id' => $user->id,
            'sub_tool_id' => 8,
            'conversation_uuid' => $conversation->uuid,
            'tool' => 'ai_product_description_generator',
            'model_key' => 'product_description_generator',
            'state' => $this->emptyState(),
            'debug' => false,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'يرجى إدخال اسم المنتج أو وصف المنتج أولًا.');
    }

    public function test_provider_failure_returns_safe_error_without_charging_wallet(): void
    {
        [$user, $conversation, $wallet] = $this->makeContext(100);
        Sanctum::actingAs($user);

        $writer = Mockery::mock(AiArabicWriterService::class);
        $writer->shouldReceive('generateReplyWithUsage')
            ->once()
            ->andThrow(new \RuntimeException('Provider secret failure.'));
        $this->app->instance(AiArabicWriterService::class, $writer);

        $response = $this->apiPostJson('/api/v1/message/send', [
            'user_id' => $user->id,
            'sub_tool_id' => 8,
            'conversation_uuid' => $conversation->uuid,
            'user_message' => 'اكتب وصفًا لساعة ذكية',
            'tool' => 'ai_product_description_generator',
            'model_key' => 'product_description_generator',
            'state' => $this->emptyState(),
            'debug' => false,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.success', false)
            ->assertJsonPath('data.type', 'error')
            ->assertJsonPath('data.message', 'حدث خطأ أثناء توليد وصف المنتج. حاول مرة أخرى.')
            ->assertJsonPath('data.tokens_deducted', 0)
            ->assertJsonCount(0, 'data.results');

        $wallet->refresh();
        $this->assertSame(100, (int) $wallet->balance);
        $this->assertSame(0, CostLogger::query()->where('conversation_id', $conversation->id)->count());
    }

    protected function makeContext(int $walletBalance): array
    {
        $user = User::factory()->create();
        $mainTool = MainTools::create([
            'name' => 'Content Tools',
            'slug' => 'content-tools-'.Str::random(8),
        ]);
        $subTool = SubTools::create([
            'id' => 8,
            'main_tool_id' => $mainTool->id,
            'name' => 'Product Description Generator',
            'slug' => 'ai-product-description-generator',
            'endpoint' => 'tasks/product-description-generator/chat',
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

    protected function emptyState(): array
    {
        return [
            'product' => null,
            'brand_name' => null,
            'product_features' => null,
            'target_audience' => null,
            'language' => null,
            'tone' => null,
            'length' => null,
            'platform' => null,
            'include_bullets' => null,
            'include_seo_keywords' => null,
            'extra_options' => [],
            'last_output' => null,
        ];
    }

    protected function completeState(): array
    {
        return [
            'product' => 'سماعة لاسلكية',
            'brand_name' => null,
            'product_features' => 'إلغاء الضوضاء وبطارية تدوم طويلًا',
            'target_audience' => 'General Customers',
            'language' => 'Arabic',
            'tone' => 'Professional',
            'length' => 'Medium',
            'platform' => 'Website / Store',
            'include_bullets' => true,
            'include_seo_keywords' => true,
            'extra_options' => ['Benefit-focused', 'Clear and persuasive'],
            'last_output' => null,
        ];
    }

    protected function apiPostJson(string $uri, array $payload)
    {
        return $this->withHeaders([
            'X-API-KEY' => 'testing-api-key',
        ])->postJson($uri, $payload);
    }
}
