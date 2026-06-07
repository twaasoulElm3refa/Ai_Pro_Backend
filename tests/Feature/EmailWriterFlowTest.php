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

class EmailWriterFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        putenv('API_KEY=testing-api-key');
        $_ENV['API_KEY'] = 'testing-api-key';
        $_SERVER['API_KEY'] = 'testing-api-key';
    }

    public function test_postman_shaped_payload_returns_email_and_charges_successful_usage(): void
    {
        [$user, $conversation, $wallet] = $this->makeContext(3000);
        Sanctum::actingAs($user);

        $state = $this->completeState();
        $requestId = (string) Str::uuid();
        $writer = Mockery::mock(AiArabicWriterService::class);
        $writer->shouldReceive('generateReplyWithUsage')
            ->once()
            ->withArgs(function (array $payload, string $endpoint): bool {
                return $endpoint === 'tasks/email-writer/chat'
                    && $payload['sub_tool_id'] === 6
                    && $payload['tool'] === 'ai_email_writer'
                    && $payload['model_key'] === 'email_writer'
                    && $payload['state']['purpose'] === 'إبلاغ المستلم بقبول طلبه على موقع CodeCanyon'
                    && $payload['state']['email_type'] === 'Acceptance Email'
                    && $payload['state']['language'] === 'Arabic'
                    && $payload['state']['tone'] === 'Professional'
                    && $payload['state']['include_subject'] === true
                    && str_contains($payload['system_prompt'], 'formal Modern Standard Arabic')
                    && str_contains($payload['system_prompt'], 'Do not invent a sender name');
            })
            ->andReturn([
                'reply' => 'تمت كتابة الإيميل بنجاح.',
                'type' => 'result',
                'provider' => 'openrouter',
                'request_id' => $requestId,
                'state' => $state,
                'usage' => [
                    'input_tokens' => 1245,
                    'output_tokens' => 1162,
                    'total_tokens' => 2407,
                ],
                'cost' => [
                    'input_cost' => 0.0001245,
                    'output_cost' => 0.000581,
                    'web_search_cost' => 0,
                    'total_cost' => 0.0007055,
                    'currency' => 'USD',
                ],
                'raw' => [
                    'success' => true,
                    'type' => 'result',
                    'tool' => 'ai_email_writer',
                    'provider' => 'openrouter',
                    'model_key' => 'email_writer',
                    'request_id' => $requestId,
                    'message' => 'تمت كتابة الإيميل بنجاح.',
                    'state' => $state,
                    'results' => [[
                        'id' => 1,
                        'text' => "Subject: تهنئة بقبول طلبك\n\nمرحبًا، تم قبول طلبك بنجاح.",
                        'title' => null,
                        'subject' => 'تهنئة بقبول طلبك',
                        'meta' => [],
                    ]],
                    'count' => 1,
                    'usage' => [
                        'input_tokens' => 1245,
                        'output_tokens' => 1162,
                        'total_tokens' => 2407,
                    ],
                    'cost' => [
                        'input_cost' => 0.0001245,
                        'output_cost' => 0.000581,
                        'web_search_cost' => 0,
                        'total_cost' => 0.0007055,
                        'currency' => 'USD',
                    ],
                ],
            ]);
        $this->app->instance(AiArabicWriterService::class, $writer);

        $response = $this->apiPostJson('/api/v1/message/send', $this->payload($conversation));

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.success', true)
            ->assertJsonPath('data.type', 'result')
            ->assertJsonPath('data.tool', 'ai_email_writer')
            ->assertJsonPath('data.model_key', 'email_writer')
            ->assertJsonPath('data.results.0.subject', 'تهنئة بقبول طلبك')
            ->assertJsonPath('data.usage.total_tokens', 2407)
            ->assertJsonPath('data.tokens_deducted', 2407);

        $assistant = Message::where('conversation_id', $conversation->id)
            ->where('role', 'assistant')
            ->firstOrFail();

        $this->assertSame("Subject: تهنئة بقبول طلبك\n\nمرحبًا، تم قبول طلبك بنجاح.", $assistant->content);
        $this->assertSame('تهنئة بقبول طلبك', $assistant->metadata['results'][0]['subject']);
        $this->assertSame($requestId, $assistant->metadata['request_id']);

        $wallet->refresh();
        $this->assertSame(593, (int) $wallet->balance);

        $costLog = CostLogger::where('conversation_id', $conversation->id)->firstOrFail();
        $this->assertSame(6, (int) $costLog->sub_tool_id);
        $this->assertSame(2407, (int) $costLog->total_tokens);
        $this->assertSame('email_writer', $costLog->model_key);
    }

    public function test_domain_renewal_request_is_inferred_and_formatted_as_formal_arabic(): void
    {
        [$user, $conversation] = $this->makeContext(1000);
        Sanctum::actingAs($user);

        $emailBody = implode("\n\n", [
            'السادة المحترمون،',
            'أتمنى أن تكونوا بخير.',
            'أكتب إليكم بخصوص تجديد نطاق الدومين الخاص بنا، والذي يقترب موعد انتهائه. نرجو منكم تأكيد استلام هذا الطلب وإتمام إجراءات التجديد في أقرب وقت ممكن، وذلك لتجنب أي انقطاع في الخدمة.',
            'في حال احتجتم إلى أي معلومات إضافية أو مستندات داعمة، يرجى إعلامي، وسأكون سعيدًا بتوفيرها.',
            'شكرًا لتعاونكم المستمر.',
            'مع خالص التحية والتقدير،',
        ]);

        $writer = Mockery::mock(AiArabicWriterService::class);
        $writer->shouldReceive('generateReplyWithUsage')
            ->once()
            ->withArgs(function (array $payload, string $endpoint): bool {
                $state = $payload['state'];

                return $endpoint === 'tasks/email-writer/chat'
                    && $state['purpose'] === 'تجديد نطاق الدومين الخاص بالشركة قبل موعد انتهائه لتجنب انقطاع الخدمة'
                    && $state['email_type'] === 'Renewal Email'
                    && $state['recipient'] === 'General Recipient'
                    && $state['sender_name'] === null
                    && $state['language'] === 'Arabic'
                    && $state['tone'] === 'Formal'
                    && $state['length'] === 'Medium'
                    && $state['subject_line'] === 'طلب تجديد نطاق الدومين'
                    && $state['call_to_action'] === 'تأكيد استلام الطلب وإتمام إجراءات التجديد في أقرب وقت ممكن'
                    && $state['include_subject'] === true
                    && in_array('Formal Arabic', $state['extra_options'], true);
            })
            ->andReturn([
                'reply' => 'تمت كتابة الإيميل بنجاح.',
                'type' => 'result',
                'provider' => 'openrouter',
                'model_key' => 'email_writer',
                'state' => [],
                'raw' => [
                    'success' => true,
                    'type' => 'result',
                    'message' => 'تمت كتابة الإيميل بنجاح.',
                    'results' => [[
                        'id' => 1,
                        'text' => "**{$emailBody}** ✅",
                        'subject' => null,
                    ]],
                ],
            ]);
        $this->app->instance(AiArabicWriterService::class, $writer);

        $payload = $this->payload($conversation);
        $payload['user_message'] = 'اكتب ايميل رسمي لتجديد الدومين الخاص بنا قبل ما ينتهي لتجنب توقف الخدمة';

        $response = $this->apiPostJson('/api/v1/message/send', $payload);

        $response->assertOk()
            ->assertJsonPath('data.type', 'result')
            ->assertJsonPath('data.state.email_type', 'Renewal Email')
            ->assertJsonPath('data.state.sender_name', null)
            ->assertJsonPath('data.state.subject_line', 'طلب تجديد نطاق الدومين')
            ->assertJsonPath('data.results.0.subject', 'طلب تجديد نطاق الدومين');

        $output = (string) $response->json('data.results.0.text');
        $this->assertStringStartsWith("Subject: طلب تجديد نطاق الدومين\n\n", $output);
        $this->assertStringNotContainsString('**', $output);
        $this->assertStringNotContainsString('✅', $output);
        $this->assertStringNotContainsString('فريق CodeCanyon', $output);

        $assistant = Message::where('conversation_id', $conversation->id)
            ->where('role', 'assistant')
            ->firstOrFail();

        $this->assertSame($output, $assistant->content);
        $this->assertSame('ai_email_writer', $assistant->metadata['tool']);
        $this->assertSame(6, $assistant->metadata['sub_tool_id']);
        $this->assertSame('email_writer', $assistant->metadata['model_key']);
        $this->assertSame('طلب تجديد نطاق الدومين', $assistant->metadata['results'][0]['subject']);
    }

    public function test_include_subject_false_removes_provider_subject_from_result_and_content(): void
    {
        [$user, $conversation] = $this->makeContext(1000);
        Sanctum::actingAs($user);

        $writer = Mockery::mock(AiArabicWriterService::class);
        $writer->shouldReceive('generateReplyWithUsage')
            ->once()
            ->withArgs(fn (array $payload): bool => $payload['state']['include_subject'] === false
                && $payload['state']['subject_line'] === null)
            ->andReturn([
                'reply' => 'تمت كتابة الإيميل بنجاح.',
                'type' => 'result',
                'state' => [],
                'raw' => [
                    'success' => true,
                    'type' => 'result',
                    'message' => 'تمت كتابة الإيميل بنجاح.',
                    'results' => [[
                        'id' => 1,
                        'text' => "Subject: عنوان يجب حذفه\n\nالسادة المحترمون،\n\nتحية طيبة وبعد.",
                        'subject' => 'عنوان يجب حذفه',
                    ]],
                ],
            ]);
        $this->app->instance(AiArabicWriterService::class, $writer);

        $payload = $this->payload($conversation);
        $payload['user_message'] = 'اكتب إيميل رسمي بدون عنوان لطلب معلومات إضافية';
        $payload['state']['include_subject'] = false;

        $response = $this->apiPostJson('/api/v1/message/send', $payload);

        $response->assertOk()
            ->assertJsonPath('data.state.include_subject', false)
            ->assertJsonPath('data.state.subject_line', null)
            ->assertJsonPath('data.results.0.subject', null);

        $output = (string) $response->json('data.results.0.text');
        $this->assertStringNotContainsString('Subject:', $output);
        $this->assertStringStartsWith('السادة المحترمون،', $output);
    }

    public function test_question_response_does_not_charge_wallet(): void
    {
        [$user, $conversation, $wallet] = $this->makeContext(100);
        Sanctum::actingAs($user);

        $questionState = $this->emptyState();
        $questionState['purpose'] = 'طلب معلومات';

        $writer = Mockery::mock(AiArabicWriterService::class);
        $writer->shouldReceive('generateReplyWithUsage')
            ->once()
            ->andReturn([
                'reply' => 'من فضلك حدد المستلم ونبرة الإيميل.',
                'type' => 'question',
                'state' => $questionState,
                'raw' => [
                    'success' => true,
                    'type' => 'question',
                    'tool' => 'ai_email_writer',
                    'provider' => 'openrouter',
                    'model_key' => 'email_writer',
                    'message' => 'من فضلك حدد المستلم ونبرة الإيميل.',
                    'state' => $questionState,
                    'results' => [],
                    'count' => 0,
                ],
            ]);
        $this->app->instance(AiArabicWriterService::class, $writer);

        $response = $this->apiPostJson('/api/v1/message/send', $this->payload($conversation));

        $response->assertOk()
            ->assertJsonPath('data.success', true)
            ->assertJsonPath('data.type', 'question')
            ->assertJsonPath('data.state.purpose', 'طلب معلومات')
            ->assertJsonPath('data.tokens_deducted', 0)
            ->assertJsonCount(0, 'data.results');

        $wallet->refresh();
        $this->assertSame(100, (int) $wallet->balance);
        $this->assertSame(0, CostLogger::where('conversation_id', $conversation->id)->count());
    }

    public function test_provider_failure_is_logged_and_persisted_without_charge(): void
    {
        [$user, $conversation, $wallet] = $this->makeContext(100);
        Sanctum::actingAs($user);

        $writer = Mockery::mock(AiArabicWriterService::class);
        $writer->shouldReceive('generateReplyWithUsage')
            ->once()
            ->andThrow(new \RuntimeException('Email provider unavailable'));
        $this->app->instance(AiArabicWriterService::class, $writer);

        $payload = $this->payload($conversation);
        $payload['debug'] = true;

        $response = $this->apiPostJson('/api/v1/message/send', $payload);

        $response->assertOk()
            ->assertJsonPath('data.success', false)
            ->assertJsonPath('data.type', 'error')
            ->assertJsonPath('data.message', 'Email provider unavailable')
            ->assertJsonPath('data.debug.error', 'Email provider unavailable')
            ->assertJsonPath('data.tokens_deducted', 0);

        $assistant = Message::where('conversation_id', $conversation->id)
            ->where('role', 'assistant')
            ->firstOrFail();

        $this->assertTrue($assistant->is_error);
        $this->assertSame('error', $assistant->metadata['type']);
        $this->assertSame('Email provider unavailable', $assistant->metadata['debug_error']);

        $wallet->refresh();
        $this->assertSame(100, (int) $wallet->balance);
        $this->assertSame(0, CostLogger::where('conversation_id', $conversation->id)->count());
    }

    protected function makeContext(int $walletBalance): array
    {
        $user = User::factory()->create();
        $mainTool = MainTools::create([
            'name' => 'Content Tools',
            'slug' => 'content-tools-'.Str::random(8),
        ]);
        $subTool = SubTools::create([
            'id' => 6,
            'main_tool_id' => $mainTool->id,
            'name' => 'AI Email Writer',
            'slug' => 'ai-email-writer',
            'endpoint' => 'tasks/email-writer/chat',
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

    protected function payload(Conversation $conversation): array
    {
        return [
            'user_id' => $conversation->user_id,
            'sub_tool_id' => 6,
            'conversation_uuid' => $conversation->uuid,
            'user_message' => 'ايميل عن مثلا تم قبول طلبك علي موقع code canyon',
            'state' => $this->emptyState(),
            'debug' => false,
        ];
    }

    protected function emptyState(): array
    {
        return [
            'purpose' => null,
            'email_type' => null,
            'recipient' => null,
            'sender_name' => null,
            'language' => null,
            'tone' => null,
            'length' => null,
            'subject_line' => null,
            'call_to_action' => null,
            'include_subject' => null,
            'extra_options' => [],
            'last_output' => null,
        ];
    }

    protected function completeState(): array
    {
        return [
            'purpose' => 'تم قبول طلبك على موقع CodeCanyon',
            'email_type' => 'General Email',
            'recipient' => 'General Recipient',
            'sender_name' => null,
            'language' => 'Arabic',
            'tone' => 'Professional',
            'length' => 'Medium',
            'subject_line' => null,
            'call_to_action' => null,
            'include_subject' => true,
            'extra_options' => ['Clear structure', 'Ready to send'],
            'last_output' => "Subject: تهنئة بقبول طلبك\n\nمرحبًا، تم قبول طلبك بنجاح.",
        ];
    }

    protected function apiPostJson(string $uri, array $payload)
    {
        return $this->withHeaders([
            'X-API-KEY' => 'testing-api-key',
        ])->postJson($uri, $payload);
    }
}
