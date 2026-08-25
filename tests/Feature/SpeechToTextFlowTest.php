<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\SubTools;
use App\Models\User;
use App\Models\Wallet;
use App\Services\SpeechToTextService;
use Database\Seeders\SpeechToTextSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SpeechToTextFlowTest extends TestCase
{
    use RefreshDatabase;

    private const API_KEY = 'testing-public-api-key';

    private const INTERNAL_KEY = 'testing-internal-speech-key';

    private const AI_BASE_URL = 'https://ai.internal.test';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.ai.base_url', self::AI_BASE_URL);
        config()->set('services.ai.internal_api_key', self::INTERNAL_KEY);
        config()->set('services.aiarabic.internal_api_key', self::INTERNAL_KEY);
        putenv('API_KEY='.self::API_KEY);
        $_ENV['API_KEY'] = self::API_KEY;
        $_SERVER['API_KEY'] = self::API_KEY;
    }

    protected function tearDown(): void
    {
        putenv('API_KEY');
        unset($_ENV['API_KEY'], $_SERVER['API_KEY']);

        parent::tearDown();
    }

    public function test_seeder_places_speech_to_text_in_chat6(): void
    {
        $this->seed(SpeechToTextSeeder::class);

        $subTool = SubTools::findOrFail(SpeechToTextService::SUB_TOOL_ID);
        $this->assertSame(6, (int) $subTool->main_tool_id);
        $this->assertSame(SpeechToTextService::TOOL_KEY, $subTool->slug);
        $this->assertSame(SpeechToTextService::ENDPOINT, $subTool->endpoint);
        $this->assertSame('ar', data_get($subTool->config, 'default_state.language'));
        $this->assertArrayNotHasKey('include_segments', data_get($subTool->config, 'default_state'));
    }

    public function test_it_forwards_only_the_authenticated_identity_and_persists_transcript_history(): void
    {
        [$user, $conversation] = $this->makeContext();
        $this->fakeSuccessfulTranscription();
        Sanctum::actingAs($user);

        $response = $this->sendAudio($conversation, ['user_id' => 999999]);

        $response->assertOk()
            ->assertJsonPath('data.success', true)
            ->assertJsonPath('data.tool', SpeechToTextService::TOOL_KEY)
            ->assertJsonPath('data.provider', 'openrouter')
            ->assertJsonPath('data.model', 'openai/whisper-large-v3')
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.sub_tool_id', SpeechToTextService::SUB_TOOL_ID)
            ->assertJsonPath('data.conversation_uuid', $conversation->uuid)
            ->assertJsonPath('data.transcript', 'بسم الله الرحمن الرحيم')
            ->assertJsonPath('data.detected_language', 'ar')
            ->assertJsonPath('data.duration_seconds', 4.25)
            ->assertJsonPath('data.metadata.provider_cost_usd', 0.000439365)
            ->assertJsonPath('data.state.last_output', 'بسم الله الرحمن الرحيم');

        $this->assertArrayNotHasKey('segments', $response->json('data'));
        $this->assertSame(
            ['provider_cost_usd' => 0.000439365],
            $response->json('data.metadata')
        );
        $this->assertSame(561, Wallet::where('user_id', $user->id)->value('balance'));
        $this->assertDatabaseHas('cost_loggers', [
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'sub_tool_id' => SpeechToTextService::SUB_TOOL_ID,
            'total_cost' => 0.00043937,
        ]);

        Http::assertSent(function (Request $request) use ($user, $conversation): bool {
            $payloadPart = collect($request->data())->firstWhere('name', 'payload');
            $payload = json_decode((string) ($payloadPart['contents'] ?? ''), true);

            return $request->method() === 'POST'
                && $request->url() === self::AI_BASE_URL.'/'.SpeechToTextService::ENDPOINT
                && str_starts_with(strtolower($request->header('Content-Type')[0] ?? ''), 'multipart/form-data')
                && ($request->header('x-internal-api-key')[0] ?? null) === self::INTERNAL_KEY
                && $request->hasFile('file', filename: 'Surah_Al_Fatiha.m4a')
                && is_array($payload)
                && $payload['user_id'] === $user->id
                && $payload['sub_tool_id'] === SpeechToTextService::SUB_TOOL_ID
                && $payload['conversation_uuid'] === $conversation->uuid
                && $payload['state']['language'] === 'ar'
                && ! array_key_exists('include_segments', $payload['state']);
        });

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'بسم الله الرحمن الرحيم',
            'is_error' => false,
        ]);

        $this->withHeaders([
            'X-API-KEY' => self::API_KEY,
            'Accept' => 'application/json',
        ])
            ->getJson('/api/v1/conversation/'.$conversation->uuid)
            ->assertOk()
            ->assertJsonPath('data.message.1.content', 'بسم الله الرحمن الرحيم')
            ->assertJsonPath('data.message.1.metadata.transcript', 'بسم الله الرحمن الرحيم')
            ->assertJsonPath('data.message.1.metadata.detected_language', 'ar')
            ->assertJsonPath('data.message.1.metadata.provider_metadata.provider_cost_usd', 0.000439365);
    }

    public function test_it_rejects_a_missing_audio_file_without_calling_the_provider(): void
    {
        [$user, $conversation] = $this->makeContext();
        Sanctum::actingAs($user);
        Http::fake();

        $this->withHeaders([
            'X-API-KEY' => self::API_KEY,
            'Accept' => 'application/json',
        ])
            ->post('/api/v1/message/send', [
                'payload' => $this->payload($conversation),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file']);

        Http::assertNothingSent();
        $this->assertSame(0, Message::query()->count());
    }

    public function test_it_returns_and_persists_a_safe_error_for_an_invalid_provider_response(): void
    {
        [$user, $conversation] = $this->makeContext();
        Sanctum::actingAs($user);
        Http::fake([
            self::AI_BASE_URL.'/'.SpeechToTextService::ENDPOINT => Http::response([
                'success' => true,
                'tool' => SpeechToTextService::TOOL_KEY,
                'sub_tool_id' => SpeechToTextService::SUB_TOOL_ID,
                'transcript' => '',
            ]),
        ]);

        $this->sendAudio($conversation)
            ->assertOk()
            ->assertJsonPath('data.success', false)
            ->assertJsonPath('data.type', 'error')
            ->assertJsonPath('data.transcript', null);

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'is_error' => true,
        ]);
    }

    public function test_it_is_idempotent_for_retried_multipart_requests(): void
    {
        [$user, $conversation] = $this->makeContext();
        $this->fakeSuccessfulTranscription();
        Sanctum::actingAs($user);
        $idempotencyKey = (string) Str::uuid();

        $this->sendAudio($conversation, ['idempotency_key' => $idempotencyKey])->assertOk();
        $this->sendAudio($conversation, ['idempotency_key' => $idempotencyKey])->assertOk();

        Http::assertSentCount(1);
        $this->assertSame(561, Wallet::where('user_id', $user->id)->value('balance'));
        $this->assertDatabaseCount('cost_loggers', 1);
        $this->assertSame(1, Message::where('conversation_id', $conversation->id)->where('role', 'user')->count());
        $this->assertSame(1, Message::where('conversation_id', $conversation->id)->where('role', 'assistant')->count());
    }

    public function test_it_does_not_charge_when_provider_cost_usd_is_missing(): void
    {
        [$user, $conversation] = $this->makeContext();
        Sanctum::actingAs($user);
        Log::spy();
        $this->fakeSuccessfulTranscription([
            'usage' => ['cost' => 0.9],
            'tokens' => 999999,
        ]);

        $this->sendAudio($conversation)
            ->assertOk()
            ->assertJsonPath('data.success', true)
            ->assertJsonPath('data.transcript', 'بسم الله الرحمن الرحيم')
            ->assertJsonPath('data.metadata', []);

        $this->assertSame(1_000, Wallet::where('user_id', $user->id)->value('balance'));
        $this->assertDatabaseCount('cost_loggers', 0);
        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context): bool => $message === 'Speech-to-text provider cost billing skipped.'
                && $context['source'] === 'metadata.provider_cost_usd'
                && str_contains($context['reason'], 'is missing'))
            ->once();
    }

    public function test_it_uses_the_existing_half_up_integer_rounding_policy(): void
    {
        [$user, $conversation] = $this->makeContext();
        Sanctum::actingAs($user);
        $this->fakeSuccessfulTranscription([
            'provider_cost_usd' => 0.0000005,
            'usage' => ['cost' => 0.99],
        ]);

        $this->sendAudio($conversation)->assertOk();

        $this->assertSame(999, Wallet::where('user_id', $user->id)->value('balance'));
        $this->assertDatabaseHas('cost_loggers', [
            'conversation_id' => $conversation->id,
            'sub_tool_id' => SpeechToTextService::SUB_TOOL_ID,
            'total_cost' => 0.0000005,
        ]);
    }

    public function test_it_handles_a_provider_connection_failure_without_duplicate_messages(): void
    {
        [$user, $conversation] = $this->makeContext();
        Sanctum::actingAs($user);
        Http::fake([
            self::AI_BASE_URL.'/'.SpeechToTextService::ENDPOINT => Http::failedConnection('Provider timeout.'),
        ]);

        $this->sendAudio($conversation)
            ->assertOk()
            ->assertJsonPath('data.success', false)
            ->assertJsonPath('data.type', 'error');

        $this->assertSame(1, Message::where('conversation_id', $conversation->id)->where('role', 'user')->count());
        $this->assertSame(1, Message::where('conversation_id', $conversation->id)->where('role', 'assistant')->count());
    }

    private function makeContext(): array
    {
        $this->seed(SpeechToTextSeeder::class);
        $user = User::factory()->create();
        $conversation = Conversation::create([
            'user_id' => $user->id,
            'sub_tool_id' => SpeechToTextService::SUB_TOOL_ID,
            'uuid' => (string) Str::uuid(),
        ]);
        Wallet::create([
            'user_id' => $user->id,
            'uuid' => (string) Str::uuid(),
            'balance' => 1_000,
            'payback_balance' => 0,
            'is_active' => true,
        ]);

        return [$user, $conversation];
    }

    private function sendAudio(Conversation $conversation, array $overrides = [])
    {
        return $this->withHeaders(['X-API-KEY' => self::API_KEY])
            ->post('/api/v1/message/send', [
                'file' => UploadedFile::fake()->create('Surah_Al_Fatiha.m4a', 64, 'audio/mp4'),
                'payload' => $this->payload($conversation, $overrides),
            ]);
    }

    private function payload(Conversation $conversation, array $overrides = []): string
    {
        return json_encode(array_replace_recursive([
            'user_id' => $conversation->user_id,
            'sub_tool_id' => SpeechToTextService::SUB_TOOL_ID,
            'conversation_uuid' => $conversation->uuid,
            'user_message' => 'فرّغ الملف الصوتي المرفق إلى نص.',
            'state' => [
                'provider' => null,
                'language' => 'ar',
                'extra_options' => [],
                'last_output' => null,
            ],
            'debug' => false,
            'idempotency_key' => (string) Str::uuid(),
        ], $overrides), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    private function fakeSuccessfulTranscription(?array $metadata = null): void
    {
        $metadata ??= [
            'provider_cost_usd' => 0.000439365,
            'usage' => ['cost' => 0.75],
            'tokens' => 999999,
        ];

        Http::fake([
            self::AI_BASE_URL.'/'.SpeechToTextService::ENDPOINT => Http::response([
                'success' => true,
                'tool' => SpeechToTextService::TOOL_KEY,
                'provider' => 'openrouter',
                'model' => 'openai/whisper-large-v3',
                'user_id' => 999999,
                'sub_tool_id' => SpeechToTextService::SUB_TOOL_ID,
                'conversation_uuid' => (string) Str::uuid(),
                'transcript' => 'بسم الله الرحمن الرحيم',
                'detected_language' => 'ar',
                'duration_seconds' => 4.25,
                'cost' => ['total_cost' => 0.9, 'currency' => 'USD'],
                'usage' => ['cost' => 0.8],
                'segments' => [
                    ['start' => 0, 'end' => 1.2, 'seek' => 100, 'text' => 'بسم الله'],
                ],
                'request_id' => 'speech-provider-request-id',
                'metadata' => $metadata,
            ]),
        ]);
    }
}
