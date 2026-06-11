<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\MainTools;
use App\Models\Message;
use App\Models\SubTools;
use App\Models\User;
use App\Models\Wallet;
use Database\Seeders\PromptGeneratorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class Chat2PromptGeneratorPageTest extends TestCase
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

    public function test_main_tool_two_is_wired_to_the_prompt_generator_page(): void
    {
        $router = file_get_contents(resource_path('js/router/index.js'));
        $showPage = file_get_contents(resource_path('js/views/home/show.vue'));
        $chat2Page = file_get_contents(resource_path('js/views/home/chat2.vue'));
        $promptResultsHelper = file_get_contents(resource_path('js/utils/promptGeneratorResults.js'));
        $legacyChat = file_get_contents(resource_path('js/views/home/chat.vue'));
        $chatService = file_get_contents(resource_path('js/services/chat/chatServices.js'));

        $this->assertStringContainsString(
            'path: "/:lang/subtool/:slug/chat2/:uuid?"',
            $router
        );
        $this->assertStringContainsString(
            'const chat2 = () => import("../views/home/chat2.vue")',
            $router
        );
        $this->assertStringContainsString(
            'const PROMPT_GENERATOR_MAIN_TOOL_ID = 2;',
            $showPage
        );
        $this->assertStringContainsString(
            'Number(tool.value.id) === PROMPT_GENERATOR_MAIN_TOOL_ID',
            $showPage
        );
        $this->assertStringContainsString(
            'const PROMPT_GENERATOR_SUB_TOOL_ID = 9;',
            $promptResultsHelper
        );
        $this->assertStringContainsString(
            'const response = await chatServices.sendMessage(payload);',
            $chat2Page
        );
        $this->assertStringContainsString(
            'api.post("/message/send", payload)',
            $chatService
        );
        $this->assertStringContainsString(
            'sub_tool_id: PROMPT_GENERATOR_SUB_TOOL_ID',
            $chat2Page
        );
        $this->assertStringContainsString(
            'state: requestState',
            $chat2Page
        );
        $this->assertStringContainsString(
            'extractPromptGeneratorTexts',
            $chat2Page
        );
        $this->assertStringContainsString('Prompt {{ index + 1 }}', $chat2Page);
        $this->assertStringContainsString('copyResult(resultText, index)', $chat2Page);
        $this->assertStringNotContainsString('result.title', $chat2Page);
        $this->assertStringNotContainsString('result.subject', $chat2Page);
        $this->assertStringNotContainsString('content: directResponse.message', $chat2Page);
        $this->assertStringNotContainsString('message: String(payload.message', $chat2Page);
        $this->assertStringContainsString('isPromptGeneratorStatusText(content)', $chat2Page);
        $this->assertStringContainsString('isPromptGeneratorStatusText', $promptResultsHelper);
        $this->assertStringNotContainsString('chat2', $legacyChat);
    }

    public function test_chat2_payload_contract_uses_the_dynamic_tool_with_a_fake_provider(): void
    {
        $providerResults = [
            ['id' => 1, 'text' => 'First generated prompt'],
            ['id' => 2, 'text' => 'Second generated prompt'],
            ['id' => 3, 'text' => 'Third generated prompt'],
        ];

        Http::fake([
            'https://api.aiarabic.test/generate/prompt' => Http::response([
                'success' => true,
                'type' => 'result',
                'tool' => 'ai_prompt_generator',
                'provider' => 'openrouter',
                'model_key' => 'prompt_generator',
                'state' => [
                    'results_count' => 3,
                    'extra_options' => [],
                ],
                'results' => $providerResults,
                'usage' => [
                    'input_tokens' => 8,
                    'output_tokens' => 12,
                    'total_tokens' => 20,
                ],
            ]),
            '*' => Http::response(['status' => 'ok']),
        ]);

        $user = User::factory()->create();
        $mainTool = MainTools::forceCreate([
            'id' => 2,
            'name' => 'Prompt Tools',
            'slug' => 'prompt-tools-'.Str::random(6),
        ]);
        SubTools::forceCreate([
            'id' => 9,
            'main_tool_id' => $mainTool->id,
            'name' => 'Prompt Generator',
            'slug' => 'ai-prompt-generator',
            'endpoint' => '/generate/prompt',
        ]);
        $this->seed(PromptGeneratorSeeder::class);

        $conversation = Conversation::create([
            'user_id' => $user->id,
            'sub_tool_id' => 9,
            'uuid' => (string) Str::uuid(),
        ]);
        Wallet::create([
            'user_id' => $user->id,
            'uuid' => (string) Str::uuid(),
            'balance' => 1000,
        ]);

        $state = [
            'task' => null,
            'target_ai_tool' => null,
            'output_type' => null,
            'language' => null,
            'tone' => null,
            'audience' => null,
            'prompt_style' => null,
            'detail_level' => null,
            'include_constraints' => null,
            'results_count' => 3,
            'extra_options' => [],
            'last_output' => null,
        ];

        Sanctum::actingAs($user);

        $response = $this->withHeaders([
            'X-API-KEY' => 'testing-api-key',
        ])->postJson('/api/v1/message/send', [
            'user_id' => $user->id,
            'sub_tool_id' => 9,
            'conversation_uuid' => $conversation->uuid,
            'user_message' => 'Generate three professional prompts.',
            'state' => $state,
            'debug' => false,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.conversation_id', $conversation->id);

        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();

            return $request->url() === 'https://api.aiarabic.test/generate/prompt'
                && ($payload['sub_tool_id'] ?? null) === 9
                && ($payload['tool'] ?? null) === 'ai_prompt_generator'
                && ($payload['model_key'] ?? null) === 'prompt_generator'
                && ($payload['state']['results_count'] ?? null) === 3
                && ($payload['state']['extra_options'] ?? null) === [];
        });

        $assistant = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('role', 'assistant')
            ->firstOrFail();

        $this->assertSame($providerResults, $assistant->metadata['results']);
        $this->assertSame('ai_prompt_generator', $assistant->metadata['tool']);
        $this->assertSame('prompt_generator', $assistant->metadata['model_key']);
    }
}
