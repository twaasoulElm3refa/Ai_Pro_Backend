<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\GeneratedImage;
use App\Models\MainTools;
use App\Models\Message;
use App\Models\SubTools;
use App\Models\User;
use App\Models\Wallet;
use App\Services\TextToSpeechService;
use Database\Seeders\TextToSpeechSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TextToSpeechFlowTest extends TestCase
{
    use RefreshDatabase;

    private const API_KEY = 'testing-public-api-key';

    private const INTERNAL_KEY = 'testing-internal-tts-key';

    private const AI_BASE_URL = 'https://ai.internal.test';

    protected function setUp(): void
    {
        parent::setUp();

        putenv('API_KEY='.self::API_KEY);
        $_ENV['API_KEY'] = self::API_KEY;
        $_SERVER['API_KEY'] = self::API_KEY;
        config()->set('services.ai.base_url', self::AI_BASE_URL);
        config()->set('services.ai.internal_api_key', self::INTERNAL_KEY);
        config()->set('services.aiarabic.base_url', self::AI_BASE_URL);
        config()->set('services.aiarabic.url', self::AI_BASE_URL);
        config()->set('services.aiarabic.internal_api_key', self::INTERNAL_KEY);
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        putenv('API_KEY');
        unset($_ENV['API_KEY'], $_SERVER['API_KEY']);

        parent::tearDown();
    }

    public function test_seeder_registers_text_to_voice_in_chat6(): void
    {
        $this->seed(TextToSpeechSeeder::class);

        $tool = SubTools::findOrFail(TextToSpeechService::SUB_TOOL_ID);
        $this->assertSame(6, (int) $tool->main_tool_id);
        $this->assertSame('Text to Voice', $tool->name);
        $this->assertSame(TextToSpeechService::TOOL_KEY, $tool->slug);
        $this->assertSame(TextToSpeechService::ENDPOINT, $tool->endpoint);
        $this->assertSame('application/json', data_get($tool->config, 'request_type'));
        $this->assertSame('alloy', data_get($tool->config, 'default_state.voice'));
        $this->assertSame('mp3', data_get($tool->config, 'default_state.response_format'));
    }

    public function test_it_sends_authenticated_json_downloads_audio_and_does_not_bill(): void
    {
        [$user, $conversation] = $this->makeContext();
        $this->fakeSuccess();
        Sanctum::actingAs($user);

        $response = $this->sendText($conversation, ['user_id' => 999999]);

        $response->assertOk()
            ->assertJsonPath('data.success', true)
            ->assertJsonPath('data.tool', TextToSpeechService::TOOL_KEY)
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.sub_tool_id', TextToSpeechService::SUB_TOOL_ID)
            ->assertJsonPath('data.conversation_uuid', $conversation->uuid)
            ->assertJsonPath('data.count', 1)
            ->assertJsonPath('data.files.0.filename', 'generated-speech.mp3')
            ->assertJsonPath('data.files.0.content_type', 'audio/mpeg')
            ->assertJsonPath('data.metadata.voice', 'alloy')
            ->assertJsonPath('data.metadata.provider_cost_usd', null)
            ->assertJsonPath('data.points_deducted', 0);

        $body = $response->getContent();
        $this->assertStringNotContainsString(self::INTERNAL_KEY, $body);
        $this->assertStringNotContainsString(self::AI_BASE_URL, $body);
        $this->assertStringStartsWith('/api/v1/generated-images/', $response->json('data.files.0.preview_url'));
        $this->assertSame(10_000, Wallet::where('user_id', $user->id)->value('balance'));
        $this->assertDatabaseCount('cost_loggers', 0);

        $file = GeneratedImage::firstOrFail();
        $this->assertSame(TextToSpeechService::SUB_TOOL_ID, (int) $file->sub_tool_id);
        $this->assertSame('audio/mpeg', $file->content_type);
        Storage::disk('local')->assertExists($file->path);

        Http::assertSent(function (Request $request) use ($user, $conversation): bool {
            if ($request->method() !== 'POST') {
                return false;
            }

            return $request->url() === self::AI_BASE_URL.'/'.TextToSpeechService::ENDPOINT
                && ($request->header('x-internal-api-key')[0] ?? null) === self::INTERNAL_KEY
                && str_starts_with(strtolower($request->header('Content-Type')[0] ?? ''), 'application/json')
                && $request['user_id'] === $user->id
                && $request['sub_tool_id'] === TextToSpeechService::SUB_TOOL_ID
                && $request['conversation_uuid'] === $conversation->uuid
                && $request['user_message'] === 'مرحبًا بكم في منصتنا الجديدة للذكاء الاصطناعي.'
                && $request['state'] === [
                    'provider' => null,
                    'model' => null,
                    'voice' => 'alloy',
                    'response_format' => 'mp3',
                    'speed' => 1.0,
                    'extra_options' => [],
                    'last_output' => null,
                ]
                && $request['debug'] === false;
        });

        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && $request->url() === self::AI_BASE_URL.'/tasks/generated-files/download/audio-file-1'
            && ($request->header('x-internal-api-key')[0] ?? null) === self::INTERNAL_KEY
            && ($request->header('Accept')[0] ?? null) === 'audio/mpeg'
        );
    }

    public function test_audio_preview_download_ownership_and_range_requests(): void
    {
        [$owner, $conversation] = $this->makeContext();
        $this->fakeSuccess();
        Sanctum::actingAs($owner);
        $result = $this->sendText($conversation);
        $previewUrl = $result->json('data.files.0.preview_url');
        $downloadUrl = $result->json('data.files.0.download_url');

        $this->withHeader('Range', 'bytes=0-2')
            ->get($previewUrl)
            ->assertStatus(206)
            ->assertHeader('Accept-Ranges', 'bytes')
            ->assertHeader('Content-Range', 'bytes 0-2/'.strlen($this->mp3Bytes()))
            ->assertHeader('Content-Type', 'audio/mpeg');

        $this->get($downloadUrl)
            ->assertOk()
            ->assertDownload('generated-speech.mp3');

        Sanctum::actingAs(User::factory()->create());
        $this->get($previewUrl)->assertForbidden();
        $this->get($downloadUrl)->assertForbidden();
    }

    public function test_history_restores_the_secure_audio_file_metadata(): void
    {
        [$user, $conversation] = $this->makeContext();
        $this->fakeSuccess();
        Sanctum::actingAs($user);
        $this->sendText($conversation)->assertOk();

        $history = $this->withHeaders(['X-API-KEY' => self::API_KEY])
            ->getJson('/api/v1/conversation/'.$conversation->uuid);

        $history->assertOk()
            ->assertJsonPath('data.message.1.metadata.tool', TextToSpeechService::TOOL_KEY)
            ->assertJsonPath('data.message.1.metadata.files.0.filename', 'generated-speech.mp3')
            ->assertJsonPath('data.message.1.files.0.content_type', 'audio/mpeg')
            ->assertJsonPath('data.message.1.metadata.state.voice', 'alloy')
            ->assertJsonPath('data.message.1.metadata.state.response_format', 'mp3');

        $this->assertStringNotContainsString('blob:', $history->getContent());
        $this->assertStringNotContainsString(self::INTERNAL_KEY, $history->getContent());
    }

    public function test_retries_are_idempotent_and_do_not_repeat_download_or_deduction(): void
    {
        [$user, $conversation] = $this->makeContext();
        $this->fakeSuccess();
        Sanctum::actingAs($user);
        $key = (string) Str::uuid();

        $this->sendText($conversation, ['idempotency_key' => $key])->assertOk()->assertJsonPath('data.success', true);
        $this->sendText($conversation, ['idempotency_key' => $key])->assertOk()->assertJsonPath('data.success', true);

        $this->assertSame(10_000, Wallet::where('user_id', $user->id)->value('balance'));
        $this->assertDatabaseCount('generated_images', 1);
        $this->assertSame(1, Message::where('conversation_id', $conversation->id)->where('role', 'assistant')->count());
        $this->assertCount(1, collect(Http::recorded())->filter(
            fn (array $record): bool => $record[0]->method() === 'POST'
        ));
        $this->assertCount(1, collect(Http::recorded())->filter(
            fn (array $record): bool => $record[0]->method() === 'GET'
        ));
    }

    public function test_invalid_or_untrusted_generated_files_return_a_safe_error(): void
    {
        [$user, $conversation] = $this->makeContext();
        Sanctum::actingAs($user);
        Http::fake([
            self::AI_BASE_URL.'/'.TextToSpeechService::ENDPOINT => Http::response(
                $this->providerResponse([[
                    'file_id' => 'evil-audio',
                    'filename' => 'generated-speech.mp3',
                    'content_type' => 'audio/mpeg',
                    'download_url' => 'https://evil.example/private',
                    'size_bytes' => 10,
                ]])
            ),
            '*' => Http::response('unexpected', 500),
        ]);

        $response = $this->sendText($conversation);
        $response->assertOk()
            ->assertJsonPath('data.success', false)
            ->assertJsonPath('data.type', 'error')
            ->assertJsonCount(0, 'data.files');

        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'evil.example'));
        $this->assertDatabaseCount('generated_images', 0);
        $this->assertSame(10_000, Wallet::where('user_id', $user->id)->value('balance'));
    }

    public function test_empty_text_and_invalid_fixed_options_are_rejected_before_provider_call(): void
    {
        [$user, $conversation] = $this->makeContext();
        Sanctum::actingAs($user);
        Http::fake();

        $this->sendText($conversation, [
            'user_message' => '',
            'state' => [
                'provider' => null,
                'model' => null,
                'voice' => 'unsupported',
                'response_format' => 'wav',
                'speed' => 10,
                'extra_options' => [],
                'last_output' => null,
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors([
                'user_message',
                'state.voice',
                'state.response_format',
                'state.speed',
            ]);

        Http::assertNothingSent();
    }

    private function makeContext(): array
    {
        $user = User::factory()->create();
        $main = MainTools::create([
            'id' => 6,
            'name' => 'Audio & Video Tools',
            'slug' => 'audio-video-tools-'.Str::random(5),
        ]);
        $subTool = SubTools::create([
            'id' => TextToSpeechService::SUB_TOOL_ID,
            'main_tool_id' => $main->id,
            'name' => 'Text to Voice',
            'slug' => TextToSpeechService::TOOL_KEY,
            'endpoint' => TextToSpeechService::ENDPOINT,
            'config' => [
                'tool_key' => TextToSpeechService::TOOL_KEY,
                'model_key' => TextToSpeechService::MODEL_KEY,
                'endpoint' => TextToSpeechService::ENDPOINT,
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
            'balance' => 10_000,
            'payback_balance' => 0,
            'is_active' => true,
        ]);

        return [$user, $conversation];
    }

    private function sendText(Conversation $conversation, array $overrides = [])
    {
        $payload = array_replace_recursive([
            'user_id' => $conversation->user_id,
            'sub_tool_id' => TextToSpeechService::SUB_TOOL_ID,
            'conversation_uuid' => (string) $conversation->uuid,
            'user_message' => 'مرحبًا بكم في منصتنا الجديدة للذكاء الاصطناعي.',
            'state' => [
                'provider' => null,
                'model' => null,
                'voice' => 'alloy',
                'response_format' => 'mp3',
                'speed' => 1.0,
                'extra_options' => [],
                'last_output' => null,
            ],
            'debug' => false,
            'idempotency_key' => (string) Str::uuid(),
        ], $overrides);

        return $this->withHeaders(['X-API-KEY' => self::API_KEY])
            ->postJson('/api/v1/message/send', $payload);
    }

    private function fakeSuccess(): void
    {
        Http::fake([
            self::AI_BASE_URL.'/'.TextToSpeechService::ENDPOINT => Http::response(
                $this->providerResponse([$this->providerFile()])
            ),
            self::AI_BASE_URL.'/tasks/generated-files/download/audio-file-1' => Http::response(
                $this->mp3Bytes(),
                200,
                ['Content-Type' => 'audio/mpeg']
            ),
        ]);
    }

    private function providerResponse(array $files): array
    {
        return [
            'success' => true,
            'type' => 'result',
            'tool' => TextToSpeechService::TOOL_KEY,
            'provider' => 'openrouter',
            'model' => TextToSpeechService::DEFAULT_MODEL,
            'user_id' => 999999,
            'sub_tool_id' => TextToSpeechService::SUB_TOOL_ID,
            'message' => 'Speech generated successfully.',
            'files' => $files,
            'count' => count($files),
            'request_id' => 'request-tts-1',
            'metadata' => [
                'model' => TextToSpeechService::DEFAULT_MODEL,
                'voice' => 'alloy',
                'format' => 'mp3',
                'provider_cost_usd' => null,
                'generation_id' => 'generation-tts-1',
            ],
            'usage' => null,
            'cost' => null,
        ];
    }

    private function providerFile(): array
    {
        return [
            'file_id' => 'audio-file-1',
            'filename' => 'generated-speech.mp3',
            'content_type' => 'audio/mpeg',
            'download_url' => '/tasks/generated-files/download/audio-file-1',
            'size_bytes' => strlen($this->mp3Bytes()),
        ];
    }

    private function mp3Bytes(): string
    {
        return "ID3\x04\x00\x00\x00\x00\x00\x15TIT2\x00\x00\x00\x05\x00\x00Test\xFF\xFB\x90\x64";
    }
}
