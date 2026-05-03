<?php

namespace Tests\Feature;

use App\Exceptions\AiServiceException;
use App\Models\Conversation;
use App\Models\MainTools;
use App\Models\SubTools;
use App\Models\User;
use App\Repository\Messages\MessageInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class MessageSendErrorHandlingTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_message_success(): void
    {
        Queue::fake();
        [$user, $conversation] = $this->makeUserAndConversation();
        Sanctum::actingAs($user);

        $payload = $this->sendPayload($conversation->id);
        $response = $this->postJson('/api/v1/message/send', $payload);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.conversation_id', $conversation->id)
            ->assertJsonPath('data.was_created', true);

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $payload['content'],
        ]);
    }

    public function test_send_message_ai_failure(): void
    {
        $this->setLocalEnvironment();
        Log::spy();

        [$user, $conversation] = $this->makeUserAndConversation();
        Sanctum::actingAs($user);

        $mockedRepository = Mockery::mock(MessageInterface::class);
        $mockedRepository
            ->shouldReceive('send')
            ->andThrow(new AiServiceException('Upstream AI provider failed: 503'));
        $this->app->instance(MessageInterface::class, $mockedRepository);

        $payload = $this->sendPayload($conversation->id);
        $response = $this->postJson('/api/v1/message/send', $payload);

        $response->assertStatus(500)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'Upstream AI provider failed: 503')
            ->assertJsonStructure([
                'success',
                'error',
                'trace',
            ]);

        $this->assertNotSame(
            'Sorry, I could not generate a response right now.',
            (string) $response->json('error')
        );

        Log::shouldHaveReceived('error')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'Send message failed.'
                    && ($context['message'] ?? null) === 'Upstream AI provider failed: 503'
                    && ! empty($context['trace']);
            });
    }

    public function test_send_message_validation_error(): void
    {
        Log::spy();
        [$user, $conversation] = $this->makeUserAndConversation();
        Sanctum::actingAs($user);

        $payload = $this->sendPayload($conversation->id);
        unset($payload['content']);

        $response = $this->postJson('/api/v1/message/send', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['content']);

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'Message request validation failed.'
                    && isset($context['errors']['content']);
            });
    }

    protected function makeUserAndConversation(): array
    {
        $user = User::factory()->create();

        $mainTool = MainTools::create([
            'name' => 'Main Tool ' . Str::random(8),
            'slug' => 'main-' . Str::random(10),
        ]);

        $subTool = SubTools::create([
            'main_tool_id' => $mainTool->id,
            'name' => 'Sub Tool ' . Str::random(8),
            'slug' => 'sub-' . Str::random(10),
        ]);

        $conversation = Conversation::create([
            'user_id' => $user->id,
            'sub_tool_id' => $subTool->id,
            'uuid' => (string) Str::uuid(),
        ]);

        return [$user, $conversation];
    }

    protected function sendPayload(int $conversationId): array
    {
        return [
            'content' => 'Test message',
            'conversation_id' => $conversationId,
            'role' => 'user',
            'idempotency_key' => (string) Str::uuid(),
        ];
    }

    protected function setLocalEnvironment(): void
    {
        putenv('APP_ENV=local');
        $_ENV['APP_ENV'] = 'local';
        $_SERVER['APP_ENV'] = 'local';
        $this->app->instance('env', 'local');
    }
}
