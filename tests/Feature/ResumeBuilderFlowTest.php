<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\CostLogger;
use App\Models\MainTools;
use App\Models\Message;
use App\Models\SubTools;
use App\Models\User;
use App\Models\Wallet;
use App\Exceptions\AiServiceException;
use App\Services\AiArabicWriterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Mockery;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ResumeBuilderFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        putenv('API_KEY=testing-api-key');
        $_ENV['API_KEY'] = 'testing-api-key';
        $_SERVER['API_KEY'] = 'testing-api-key';
    }

    public function test_resume_builder_accepts_multipart_state_upload_and_returns_download_url(): void
    {
        Storage::fake('local');
        [$user, $conversation] = $this->makeContext(19, 'Resume Builder', 'resume-builder', 'tasks/resume-builder/chat');
        Sanctum::actingAs($user);

        $state = $this->resumeState(['output_format' => 'docx']);
        $upload = $this->docxUpload('resume.docx', 'Jane Doe Senior Laravel Developer');
        $requestId = (string) Str::uuid();
        $providerFileId = (string) Str::uuid();
        $writer = Mockery::mock(AiArabicWriterService::class);
        $writer->shouldReceive('generateResumeBuilderReplyWithUsage')
            ->once()
            ->withArgs(function (array $fields, UploadedFile $file, string $endpoint) use ($state): bool {
                $decodedState = json_decode($fields['state'] ?? '', true);

                return $endpoint === 'tasks/resume-builder/chat'
                    && $fields['user_id'] !== ''
                    && $fields['sub_tool_id'] === '19'
                    && $fields['conversation_uuid'] !== ''
                    && $fields['user_message'] === 'Improve this resume for a Senior Laravel Developer role and make it ATS-friendly.'
                    && $fields['content'] === 'Improve this resume for a Senior Laravel Developer role and make it ATS-friendly.'
                    && $fields['tool'] === 'resume_builder'
                    && $fields['tool_key'] === 'resume_builder'
                    && $fields['model_key'] === 'resume_builder'
                    && ($decodedState['target_role'] ?? null) === $state['target_role']
                    && $fields['debug'] === '0'
                    && $fields['idempotency_key'] !== ''
                    && $file->getClientOriginalName() === 'resume.docx'
                    && ! array_key_exists('body', $fields);
            })
            ->andReturn([
                'success' => true,
                'type' => 'result',
                'tool' => 'resume_builder_ai',
                'provider' => 'openrouter',
                'model_key' => 'resume_builder',
                'message' => 'Resume generated successfully.',
                'state' => [
                    ...$state,
                    'last_output' => 'Jane Doe resume preview',
                ],
                'results' => [[
                    'id' => 1,
                    'text' => "Jane Doe\n\nSummary\nSenior Laravel Developer with strong backend experience.",
                    'title' => 'Resume Preview',
                    'subject' => null,
                    'meta' => [],
                ]],
                'file' => [
                    'file_id' => $providerFileId,
                    'filename' => 'Jane_Doe_resume.docx',
                    'content_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'download_url' => "/tasks/resume-builder/download/{$providerFileId}",
                ],
                'request_id' => $requestId,
                'usage' => [
                    'input_tokens' => 2250,
                    'output_tokens' => 2941,
                    'total_tokens' => 5191,
                ],
                'cost' => [
                    'input_cost' => 0.000225,
                    'output_cost' => 0.0014705,
                    'web_search_cost' => 0.0,
                    'total_cost' => 0.0016955,
                    'currency' => 'USD',
                ],
                'model_key' => 'resume_builder',
            ]);
        $this->app->instance(AiArabicWriterService::class, $writer);

        $response = $this->withHeaders($this->apiHeaders())->post('/api/v1/message/send', [
            'user_id' => $user->id,
            'sub_tool_id' => 19,
            'conversation_uuid' => $conversation->uuid,
            'user_message' => 'Improve this resume for a Senior Laravel Developer role and make it ATS-friendly.',
            'content' => 'Improve this resume for a Senior Laravel Developer role and make it ATS-friendly.',
            'tool' => 'resume_builder',
            'tool_key' => 'resume_builder',
            'model_key' => 'resume_builder',
            'state' => json_encode($state),
            'debug' => '0',
            'idempotency_key' => (string) Str::uuid(),
            'file' => $upload,
        ]);

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/json; charset=UTF-8')
            ->assertJsonPath('data.sub_tool_id', 19)
            ->assertJsonPath('data.tool', 'resume_builder_ai')
            ->assertJsonPath('data.tool_key', 'resume_builder')
            ->assertJsonPath('data.state.target_role', 'Senior Laravel Developer')
            ->assertJsonPath('data.results.0.title', 'Resume Preview')
            ->assertJsonPath('data.results.0.meta.output_format', 'docx')
            ->assertJsonPath('data.results.0.meta.original_filename', 'resume.docx')
            ->assertJsonPath('data.results.0.meta.filename', 'Jane_Doe_resume.docx')
            ->assertJsonPath('data.file.filename', 'Jane_Doe_resume.docx')
            ->assertJsonPath('data.request_id', $requestId)
            ->assertJsonPath('data.usage.total_tokens', 5191)
            ->assertJsonPath('data.cost.total_cost', 0.0016955);

        $this->assertNotEmpty($response->json('data.results.0.meta.download_url'));
        $this->assertSame(
            "https://api.aiarabic.com/tasks/resume-builder/download/{$providerFileId}",
            $response->json('data.results.0.meta.download_url')
        );
        $this->assertSame(
            "https://api.aiarabic.com/tasks/resume-builder/download/{$providerFileId}",
            $response->json('data.file.download_url')
        );
        $this->assertCount(0, Storage::disk('local')->files('resume_uploads'));
        $this->assertCount(0, Storage::disk('local')->files('resume_outputs'));
        $this->assertDatabaseCount('resume_generated_files', 0);

        $assistant = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('role', 'assistant')
            ->firstOrFail();

        $this->assertSame(19, $assistant->metadata['sub_tool_id']);
        $this->assertSame('resume_builder', $assistant->metadata['tool_key']);
        $this->assertSame('resume_builder_ai', $assistant->metadata['tool']);
        $this->assertSame($requestId, $assistant->metadata['request_id']);
        $this->assertSame(5191, $assistant->metadata['usage']['total_tokens']);
        $this->assertSame('Jane_Doe_resume.docx', $assistant->metadata['file']['filename']);
        $this->assertSame(
            "https://api.aiarabic.com/tasks/resume-builder/download/{$providerFileId}",
            $assistant->metadata['file']['download_url']
        );
        $this->assertSame(
            "https://api.aiarabic.com/tasks/resume-builder/download/{$providerFileId}",
            $assistant->metadata['normalized_results'][0]['meta']['download_url']
        );
        $this->assertArrayNotHasKey('body', $assistant->metadata['request_payload']);
        $this->assertArrayNotHasKey('path', $assistant->metadata['request_payload']['uploaded_file']);

        $userMessage = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('role', 'user')
            ->firstOrFail();
        $this->assertSame('resume.docx', $userMessage->metadata['uploaded_file']['filename']);
        $this->assertSame('resume.docx', $userMessage->metadata['uploaded_file']['name']);
        $this->assertSame('resume.docx', $userMessage->metadata['uploaded_file']['original_filename']);
        $this->assertSame('docx', $userMessage->metadata['uploaded_file']['extension']);
        $this->assertSame('Document', $userMessage->metadata['uploaded_file']['label']);
        $this->assertArrayNotHasKey('path', $userMessage->metadata['uploaded_file']);

        $wallet = Wallet::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(0, (int) $wallet->balance);
        $this->assertSame(4191, (int) $wallet->payback_balance);

        $cost = CostLogger::where('conversation_id', $conversation->id)->firstOrFail();
        $this->assertSame(5191, (int) $cost->total_tokens);
        $this->assertEqualsWithDelta(0.0016955, (float) $cost->total_cost, 0.00000001);

        $history = $this->withHeaders($this->apiHeaders())
            ->getJson("/api/v1/conversation/{$conversation->uuid}");
        $history->assertOk()
            ->assertJsonPath('data.message.0.metadata.uploaded_file.filename', 'resume.docx')
            ->assertJsonPath('data.message.0.metadata.uploaded_file.name', 'resume.docx')
            ->assertJsonPath('data.message.0.metadata.uploaded_file.label', 'Document')
            ->assertJsonPath('data.message.1.metadata.file.filename', 'Jane_Doe_resume.docx')
            ->assertJsonPath('data.message.1.metadata.file.download_url', "https://api.aiarabic.com/tasks/resume-builder/download/{$providerFileId}")
            ->assertJsonPath('data.message.1.metadata.normalized_results.0.meta.filename', 'Jane_Doe_resume.docx')
            ->assertJsonPath('data.message.1.metadata.normalized_results.0.meta.download_url', "https://api.aiarabic.com/tasks/resume-builder/download/{$providerFileId}")
            ->assertJsonPath('data.message.1.file.filename', 'Jane_Doe_resume.docx')
            ->assertJsonPath('data.message.1.file.download_url', "https://api.aiarabic.com/tasks/resume-builder/download/{$providerFileId}");

        $this->assertArrayNotHasKey('path', $history->json('data.message.0.metadata.uploaded_file'));
    }

    public function test_resume_builder_accepts_file_only_and_prevents_duplicate_deduction(): void
    {
        Storage::fake('local');
        [$user, $conversation] = $this->makeContext(19, 'Resume Builder', 'resume-builder', 'tasks/resume-builder/chat');
        Sanctum::actingAs($user);

        $idempotencyKey = (string) Str::uuid();
        $writer = Mockery::mock(AiArabicWriterService::class);
        $writer->shouldReceive('generateResumeBuilderReplyWithUsage')
            ->once()
            ->withArgs(fn (array $fields, UploadedFile $file): bool =>
                $fields['user_message'] === 'Improve this resume for Senior Laravel Developer.'
                && $fields['content'] === 'Improve this resume for Senior Laravel Developer.'
                && $fields['sub_tool_id'] === '19'
                && $file->getClientOriginalName() === 'resume-file-only.docx'
                && ! array_key_exists('body', $fields)
            )
            ->andReturn([
                'results' => [[
                    'id' => 1,
                    'text' => 'Resume preview from file only.',
                    'title' => 'Resume Preview',
                    'meta' => [],
                ]],
                'usage' => ['input_tokens' => 10, 'output_tokens' => 15, 'total_tokens' => 25],
                'cost' => ['total_cost' => 0.0001, 'currency' => 'USD'],
                'request_id' => 'file-only-request',
                'model_key' => 'resume_builder',
            ]);
        $this->app->instance(AiArabicWriterService::class, $writer);

        $payload = [
            'sub_tool_id' => 19,
            'conversation_uuid' => $conversation->uuid,
            'tool_key' => 'resume_builder',
            'model_key' => 'resume_builder',
            'state' => json_encode($this->resumeState()),
            'debug' => '0',
            'idempotency_key' => $idempotencyKey,
        ];

        $first = $this->withHeaders($this->apiHeaders())->post('/api/v1/message/send', [
            ...$payload,
            'file' => $this->docxUpload('resume-file-only.docx', 'Jane Doe Senior Laravel Developer'),
        ]);

        $first->assertOk()
            ->assertJsonPath('data.results.0.text', 'Resume preview from file only.')
            ->assertJsonPath('data.tokens_deducted', 25);

        $second = $this->withHeaders($this->apiHeaders())->post('/api/v1/message/send', [
            ...$payload,
            'file' => $this->docxUpload('resume-file-only-retry.docx', 'Jane Doe Senior Laravel Developer'),
        ]);

        $second->assertOk()
            ->assertJsonPath('data.results.0.text', 'Resume preview from file only.')
            ->assertJsonPath('data.tokens_deducted', 25);

        $this->assertSame(1, Message::where('conversation_id', $conversation->id)->where('role', 'user')->count());
        $this->assertSame(1, Message::where('conversation_id', $conversation->id)->where('role', 'assistant')->count());
        $this->assertSame(1, CostLogger::where('conversation_id', $conversation->id)->count());
        $wallet = Wallet::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(975, (int) $wallet->balance);
    }

    public function test_resume_builder_forwards_doc_upload_to_ai_service(): void
    {
        Storage::fake('local');
        [$user, $conversation] = $this->makeContext(19, 'Resume Builder', 'resume-builder', 'tasks/resume-builder/chat');
        Sanctum::actingAs($user);

        $writer = Mockery::mock(AiArabicWriterService::class);
        $writer->shouldReceive('generateResumeBuilderReplyWithUsage')
            ->once()
            ->withArgs(fn (array $fields, UploadedFile $file, string $endpoint): bool =>
                $endpoint === 'tasks/resume-builder/chat'
                && $fields['sub_tool_id'] === '19'
                && $file->getClientOriginalName() === 'resume.doc'
            )
            ->andReturn([
                'results' => [[
                    'id' => 1,
                    'text' => 'DOC resume preview.',
                    'title' => 'Resume Preview',
                    'meta' => [],
                ]],
                'usage' => ['total_tokens' => 0],
                'cost' => ['total_cost' => 0, 'currency' => 'USD'],
                'model_key' => 'resume_builder',
            ]);
        $this->app->instance(AiArabicWriterService::class, $writer);

        $this->withHeaders($this->apiHeaders())->post('/api/v1/message/send', [
            'sub_tool_id' => 19,
            'conversation_uuid' => $conversation->uuid,
            'user_message' => 'Improve this resume for a Senior Laravel Developer role.',
            'content' => 'Improve this resume for a Senior Laravel Developer role.',
            'tool_key' => 'resume_builder',
            'model_key' => 'resume_builder',
            'state' => json_encode($this->resumeState()),
            'file' => UploadedFile::fake()->create('resume.doc', 1, 'application/msword'),
        ])->assertOk()
            ->assertJsonPath('data.results.0.text', 'DOC resume preview.');
    }

    public function test_resume_builder_provider_failure_does_not_save_failed_user_message(): void
    {
        Storage::fake('local');
        [$user, $conversation] = $this->makeContext(19, 'Resume Builder', 'resume-builder', 'tasks/resume-builder/chat');
        Sanctum::actingAs($user);

        $writer = Mockery::mock(AiArabicWriterService::class);
        $writer->shouldReceive('generateResumeBuilderReplyWithUsage')
            ->once()
            ->andThrow(new AiServiceException(
                'The file field is required.',
                [
                    'status' => 422,
                    'friendly_message' => 'Could not improve the resume right now. Please try again.',
                    'code' => 'AI_VALIDATION_ERROR',
                ],
                422
            ));
        $this->app->instance(AiArabicWriterService::class, $writer);

        $this->withHeaders($this->apiHeaders())->post('/api/v1/message/send', [
            'sub_tool_id' => 19,
            'conversation_uuid' => $conversation->uuid,
            'user_message' => 'Improve this resume for a Senior Laravel Developer role.',
            'content' => 'Improve this resume for a Senior Laravel Developer role.',
            'tool_key' => 'resume_builder',
            'model_key' => 'resume_builder',
            'state' => json_encode($this->resumeState()),
            'idempotency_key' => (string) Str::uuid(),
            'file' => UploadedFile::fake()->create('resume.pdf', 1, 'application/pdf'),
        ])->assertStatus(422)
            ->assertJsonPath('code', 'AI_VALIDATION_ERROR')
            ->assertJsonPath('message', 'Could not improve the resume right now. Please try again.');

        $this->assertSame(0, Message::where('conversation_id', $conversation->id)->count());
        $this->assertSame(0, CostLogger::where('conversation_id', $conversation->id)->count());
        $this->assertCount(0, Storage::disk('local')->files('resume_outputs'));
    }

    public function test_resume_builder_writer_sends_multipart_fields_and_file(): void
    {
        config([
            'services.aiarabic.url' => 'https://api.aiarabic.test',
            'services.aiarabic.key' => 'testing-internal-key',
        ]);

        Http::fake([
            'https://api.aiarabic.test/tasks/resume-builder/chat' => Http::response([
                'success' => true,
                'type' => 'result',
                'tool' => 'resume_builder_ai',
                'model_key' => 'resume_builder',
                'results' => [[
                    'id' => 1,
                    'text' => 'Multipart resume result.',
                    'title' => 'Resume Preview',
                    'meta' => [],
                ]],
                'usage' => ['total_tokens' => 12],
                'cost' => ['total_cost' => 0.0001, 'currency' => 'USD'],
            ], 200),
        ]);

        $service = app(AiArabicWriterService::class);
        $state = $this->resumeState();
        $response = $service->generateResumeBuilderReplyWithUsage([
            'user_id' => '2',
            'sub_tool_id' => '19',
            'conversation_uuid' => (string) Str::uuid(),
            'user_message' => 'enhance this cv as backend developer',
            'content' => 'enhance this cv as backend developer',
            'state' => json_encode($state, JSON_UNESCAPED_UNICODE),
            'tool' => 'resume_builder',
            'tool_key' => 'resume_builder',
            'model_key' => 'resume_builder',
            'debug' => '0',
            'idempotency_key' => (string) Str::uuid(),
        ], UploadedFile::fake()->create('resume.pdf', 10, 'application/pdf'), 'tasks/resume-builder/chat');

        $this->assertSame('Multipart resume result.', $response['results'][0]['text']);

        Http::assertSent(function (Request $request): bool {
            $fields = collect($request->data())
                ->filter(fn ($part): bool => is_array($part) && isset($part['name']) && array_key_exists('contents', $part))
                ->mapWithKeys(fn ($part): array => [$part['name'] => $part['contents']])
                ->all();

            return $request->url() === 'https://api.aiarabic.test/tasks/resume-builder/chat'
                && $request->isMultipart()
                && ! $request->isJson()
                && $request->hasHeader('x-internal-api-key', 'testing-internal-key')
                && $request->hasFile('file', null, 'resume.pdf')
                && ($fields['user_id'] ?? null) === '2'
                && ($fields['sub_tool_id'] ?? null) === '19'
                && ($fields['user_message'] ?? null) === 'enhance this cv as backend developer'
                && ($fields['content'] ?? null) === 'enhance this cv as backend developer'
                && ($fields['tool'] ?? null) === 'resume_builder'
                && ($fields['tool_key'] ?? null) === 'resume_builder'
                && ($fields['model_key'] ?? null) === 'resume_builder'
                && ($fields['debug'] ?? null) === '0'
                && ! array_key_exists('body', $fields);
        });
    }

    public function test_resume_builder_writer_uses_multipart_even_without_file(): void
    {
        config([
            'services.aiarabic.url' => 'https://api.aiarabic.test',
            'services.aiarabic.key' => 'testing-internal-key',
        ]);

        Http::fake([
            'https://api.aiarabic.test/tasks/resume-builder/chat' => Http::response([
                'success' => true,
                'type' => 'result',
                'results' => [[
                    'id' => 1,
                    'text' => 'Text-only resume result.',
                    'meta' => [],
                ]],
            ], 200),
        ]);

        $service = app(AiArabicWriterService::class);
        $service->generateResumeBuilderReplyWithUsage([
            'user_id' => '2',
            'sub_tool_id' => '19',
            'conversation_uuid' => (string) Str::uuid(),
            'user_message' => 'build a resume from my notes',
            'content' => 'build a resume from my notes',
            'state' => json_encode($this->resumeState(), JSON_UNESCAPED_UNICODE),
            'tool' => 'resume_builder',
            'tool_key' => 'resume_builder',
            'model_key' => 'resume_builder',
            'debug' => '0',
            'idempotency_key' => (string) Str::uuid(),
        ], null, 'tasks/resume-builder/chat');

        Http::assertSent(fn (Request $request): bool =>
            $request->url() === 'https://api.aiarabic.test/tasks/resume-builder/chat'
            && $request->isMultipart()
            && ! $request->isJson()
            && ! $request->hasFile('file')
        );
    }

    #[DataProvider('existingChat4ToolProvider')]
    public function test_existing_chat4_tools_still_accept_json_payloads(int $subToolId, string $toolKey, string $modelKey, string $endpoint, array $state): void
    {
        [$user, $conversation] = $this->makeContext($subToolId, $toolKey, $toolKey.'-slug', $endpoint);
        Sanctum::actingAs($user);

        $writer = Mockery::mock(AiArabicWriterService::class);
        $writer->shouldReceive('generateReplyWithUsage')
            ->once()
            ->withArgs(fn (array $payload, string $actualEndpoint): bool =>
                $actualEndpoint === $endpoint
                && $payload['sub_tool_id'] === $subToolId
                && $payload['tool_key'] === $toolKey
                && $payload['model_key'] === $modelKey
            )
            ->andReturn([
                'reply' => '{"results":[{"id":1,"text":"Tool result","meta":{}}]}',
                'usage' => ['total_tokens' => 0],
                'model_key' => $modelKey,
            ]);
        $this->app->instance(AiArabicWriterService::class, $writer);

        $this->withHeaders($this->apiHeaders())->postJson('/api/v1/message/send', [
            'sub_tool_id' => $subToolId,
            'conversation_uuid' => $conversation->uuid,
            'user_message' => 'Run this existing Chat4 tool.',
            'content' => 'Run this existing Chat4 tool.',
            'tool' => $toolKey,
            'tool_key' => $toolKey,
            'model_key' => $modelKey,
            'state' => $state,
            'idempotency_key' => (string) Str::uuid(),
        ])->assertOk()
            ->assertJsonPath('data.sub_tool_id', $subToolId)
            ->assertJsonPath('data.tool_key', $toolKey)
            ->assertJsonPath('data.results.0.text', 'Tool result');
    }

    public static function existingChat4ToolProvider(): array
    {
        return [
            'ai detector' => [17, 'ai_detector', 'ai_detector', 'tasks/ai-detector/chat', [
                'content' => 'Text to analyze.',
                'language' => 'Auto Detect',
                'analysis_depth' => 'Medium',
                'detection_focus' => 'AI writing signals',
                'include_score' => true,
                'include_evidence' => true,
                'include_rewrite_tips' => true,
                'extra_options' => [],
            ]],
            'ai humanizer' => [18, 'ai_humanizer', 'ai_humanizer', 'tasks/ai-humanizer/chat', [
                'content' => 'Text to humanize.',
                'language' => 'English',
                'tone' => 'Natural',
                'audience' => 'General Audience',
                'humanize_level' => 'Medium',
                'preserve_meaning' => true,
                'preserve_keywords' => true,
                'results_count' => 1,
                'extra_options' => [],
            ]],
            'business name generator' => [20, 'business_name_generator', 'business_name_generator', 'tasks/business-name-generator/chat', [
                'business_idea' => 'AI tools for marketers',
                'industry' => 'AI tools',
                'target_audience' => 'marketers',
                'language' => 'English',
                'tone' => 'Creative',
                'name_style' => 'Brandable',
                'keywords' => ['AI'],
                'avoid_words' => [],
                'results_count' => 1,
                'include_slogans' => true,
                'include_domain_ideas' => true,
                'extra_options' => [],
            ]],
        ];
    }

    private function makeContext(int $subToolId, string $name, string $slug, string $endpoint): array
    {
        $user = User::factory()->create();
        $mainTool = MainTools::create([
            'name' => 'Chat4 Tools '.Str::random(8),
            'slug' => 'chat4-tools-'.Str::random(8),
        ]);
        $subTool = SubTools::create([
            'id' => $subToolId,
            'main_tool_id' => $mainTool->id,
            'name' => $name.' '.Str::random(8),
            'slug' => $slug.'-'.Str::random(8),
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
            'balance' => 1000,
        ]);

        return [$user, $conversation];
    }

    private function resumeState(array $overrides = []): array
    {
        return array_replace([
            'target_role' => 'Senior Laravel Developer',
            'candidate_name' => 'Jane Doe',
            'language' => 'English',
            'tone' => 'Professional',
            'experience_level' => 'Senior',
            'resume_style' => 'ATS-friendly modern',
            'output_format' => 'docx',
            'sections_to_include' => ['Summary', 'Skills', 'Experience', 'Education'],
            'extra_options' => ['Improve clarity', 'Use strong action verbs', 'Keep it honest', 'Do not invent experience'],
            'last_output' => null,
        ], $overrides);
    }

    private function docxUpload(string $filename, string $content): UploadedFile
    {
        $path = storage_path('framework/testing/'.$filename);
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        $phpWord = new PhpWord;
        $section = $phpWord->addSection();
        $section->addText($content);
        IOFactory::createWriter($phpWord, 'Word2007')->save($path);

        return new UploadedFile(
            $path,
            $filename,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            null,
            true
        );
    }

    private function apiHeaders(): array
    {
        return [
            'X-API-KEY' => 'testing-api-key',
            'Accept' => 'application/json',
        ];
    }
}
