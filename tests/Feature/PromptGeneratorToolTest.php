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

class PromptGeneratorToolTest extends TestCase
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

    public function test_prompt_generator_runs_from_sub_tool_config_without_a_controller_condition(): void
    {
        $providerState = [
            'task' => 'Write an SEO article about artificial intelligence tools for marketers',
            'target_ai_tool' => 'ChatGPT',
            'output_type' => 'Prompt',
            'language' => 'English',
            'tone' => 'Professional',
            'audience' => 'Marketers',
            'prompt_style' => 'Long',
            'detail_level' => 'Detailed',
            'include_constraints' => true,
            'results_count' => 3,
            'extra_options' => ['Include SEO constraints'],
            'last_output' => null,
        ];
        $providerResults = [
            ['id' => 1, 'text' => 'Prompt result one'],
            ['id' => 2, 'text' => 'Prompt result two'],
            ['id' => 3, 'text' => 'Prompt result three'],
        ];

        Http::fake([
            'https://api.aiarabic.test/tasks/custom-prompt-generator/chat' => Http::response([
                'success' => true,
                'type' => 'result',
                'tool' => 'ai_prompt_generator',
                'provider' => 'openrouter',
                'model_key' => 'prompt_generator',
                'state' => $providerState,
                'results' => $providerResults,
                'usage' => [
                    'input_tokens' => 5,
                    'output_tokens' => 6,
                    'total_tokens' => 11,
                ],
            ]),
            '*' => Http::response(['status' => 'ok']),
        ]);

        $user = User::factory()->create();
        $mainTool = MainTools::create([
            'name' => 'Prompt Tools',
            'slug' => 'prompt-tools-'.Str::random(6),
        ]);
        SubTools::create([
            'id' => 9,
            'main_tool_id' => $mainTool->id,
            'name' => 'Existing Prompt Tool',
            'slug' => 'existing-prompt-tool',
            'endpoint' => 'tasks/custom-prompt-generator/chat',
        ]);

        $this->seed(PromptGeneratorSeeder::class);

        $subTool = SubTools::findOrFail(9);
        $this->assertSame('tasks/custom-prompt-generator/chat', $subTool->endpoint);
        $this->assertSame('ai_prompt_generator', $subTool->config['tool_key']);
        $this->assertSame('prompt_generator', $subTool->config['model_key']);

        $conversation = Conversation::create([
            'user_id' => $user->id,
            'sub_tool_id' => $subTool->id,
            'uuid' => (string) Str::uuid(),
        ]);
        Wallet::create([
            'user_id' => $user->id,
            'uuid' => (string) Str::uuid(),
            'balance' => 1000,
        ]);
        $requestState = [
            'task' => null,
            'target_ai_tool' => null,
            'output_type' => null,
            'language' => null,
            'tone' => null,
            'audience' => null,
            'prompt_style' => null,
            'detail_level' => null,
            'include_constraints' => null,
            'results_count' => null,
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
            'user_message' => 'Generate a 3 long professional ChatGPT prompt to write an SEO article about artificial intelligence tools for marketers',
            'state' => $requestState,
            'debug' => false,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.conversation_id', $conversation->id);

        Http::assertSent(function (Request $request) use ($requestState): bool {
            $payload = $request->data();

            return $request->url() === 'https://api.aiarabic.test/tasks/custom-prompt-generator/chat'
                && $request->method() === 'POST'
                && ($payload['sub_tool_id'] ?? null) === 9
                && ($payload['tool'] ?? null) === 'ai_prompt_generator'
                && ($payload['model_key'] ?? null) === 'prompt_generator'
                && array_key_exists('results_count', $payload['state'] ?? [])
                && $payload['state']['results_count'] === null
                && ($payload['state']['extra_options'] ?? null) === []
                && ($payload['state'] ?? null) === $requestState
                && ($payload['debug'] ?? null) === false;
        });

        $assistant = Message::where('conversation_id', $conversation->id)
            ->where('role', 'assistant')
            ->firstOrFail();

        $this->assertSame('result', $assistant->metadata['type']);
        $this->assertSame('ai_prompt_generator', $assistant->metadata['tool']);
        $this->assertSame('prompt_generator', $assistant->metadata['model_key']);
        $this->assertSame(3, $assistant->metadata['state']['results_count']);
        $this->assertSame($providerResults, $assistant->metadata['results']);
        $this->assertSame(
            "Prompt result one\n\nPrompt result two\n\nPrompt result three",
            $assistant->metadata['state']['last_output']
        );

        $controller = file_get_contents(base_path('app/Http/Controllers/api/home/MessageController.php'));
        $this->assertDoesNotMatchRegularExpression(
            '/(?:subToolId|sub_tool_id)[^\r\n]*(?:===|==|=>)\s*9\b/',
            $controller
        );
    }
}
