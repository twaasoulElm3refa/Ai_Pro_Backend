<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\MainTools;
use App\Models\SubTools;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DynamicNewToolFlowTest extends TestCase
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

    public function test_new_sub_tool_uses_database_config_without_a_controller_condition(): void
    {
        Http::fake([
            'https://api.aiarabic.test/tasks/dynamic-999/chat' => Http::response([
                'reply' => 'Dynamic tool response',
                'tool' => 'dynamic_tool_999',
                'model_key' => 'dynamic_model_999',
                'usage' => [
                    'input_tokens' => 4,
                    'output_tokens' => 3,
                    'total_tokens' => 7,
                ],
            ]),
            '*' => Http::response(['status' => 'ok']),
        ]);

        $user = User::factory()->create();
        $mainTool = MainTools::create([
            'name' => 'Dynamic Test Tools',
            'slug' => 'dynamic-test-tools-'.Str::random(6),
        ]);
        $subTool = SubTools::create([
            'id' => 999,
            'main_tool_id' => $mainTool->id,
            'name' => 'Dynamic Tool 999',
            'slug' => 'dynamic-tool-999',
            'config' => [
                'tool_key' => 'dynamic_tool_999',
                'model_key' => 'dynamic_model_999',
                'endpoint' => 'tasks/dynamic-999/chat',
                'default_state' => [
                    'task' => null,
                    'results_count' => 1,
                    'extra_options' => [],
                ],
                'state_schema' => [
                    'task' => ['required', 'string', 'max:5000'],
                    'results_count' => ['required', 'integer', 'min:1', 'max:10'],
                    'extra_options' => [
                        'type' => 'array',
                        'nullable' => true,
                        'items' => ['string', 'max:150'],
                    ],
                ],
                'payload_map' => [
                    'requested_task' => 'state.task',
                ],
            ],
        ]);
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
        $state = [
            'task' => 'Create a launch plan for a new AI product.',
            'results_count' => 3,
            'extra_options' => ['Include risks'],
        ];

        Sanctum::actingAs($user);

        $response = $this->withHeaders([
            'X-API-KEY' => 'testing-api-key',
        ])->postJson('/api/v1/message/send', [
            'sub_tool_id' => 999,
            'conversation_uuid' => $conversation->uuid,
            'user_message' => 'Generate the launch plan.',
            'state' => $state,
            'debug' => false,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.conversation_id', $conversation->id);

        Http::assertSent(function (Request $request) use ($state): bool {
            $payload = $request->data();

            return $request->url() === 'https://api.aiarabic.test/tasks/dynamic-999/chat'
                && $request->method() === 'POST'
                && ($payload['sub_tool_id'] ?? null) === 999
                && ($payload['tool'] ?? null) === 'dynamic_tool_999'
                && ($payload['model_key'] ?? null) === 'dynamic_model_999'
                && ($payload['state'] ?? null) === $state
                && ($payload['requested_task'] ?? null) === $state['task'];
        });

        $this->assertStringNotContainsString(
            '999',
            file_get_contents(base_path('app/Http/Controllers/api/home/MessageController.php'))
        );
    }
}
