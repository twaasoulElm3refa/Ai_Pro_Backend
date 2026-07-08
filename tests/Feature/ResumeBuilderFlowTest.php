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
use Illuminate\Http\UploadedFile;
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
        $writer = Mockery::mock(AiArabicWriterService::class);
        $writer->shouldReceive('generateReplyWithUsage')
            ->once()
            ->withArgs(function (array $payload, string $endpoint) use ($state): bool {
                return $endpoint === 'tasks/resume-builder/chat'
                    && $payload['sub_tool_id'] === 19
                    && $payload['tool_key'] === 'resume_builder'
                    && $payload['model_key'] === 'resume_builder'
                    && $payload['state']['target_role'] === $state['target_role']
                    && $payload['sensitive'] === true
                    && str_contains($payload['extracted_resume_text'], 'Jane Doe')
                    && str_contains($payload['instruction'], 'Do not invent jobs');
            })
            ->andReturn([
                'reply' => '{"results":[{"id":1,"text":"Jane Doe\n\nSummary\nSenior Laravel Developer with strong backend experience.","title":"Improved resume","subject":"Resume Builder","meta":{}}]}',
                'usage' => ['total_tokens' => 0],
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
            ->assertJsonPath('data.sub_tool_id', 19)
            ->assertJsonPath('data.tool_key', 'resume_builder')
            ->assertJsonPath('data.state.target_role', 'Senior Laravel Developer')
            ->assertJsonPath('data.results.0.meta.output_format', 'docx')
            ->assertJsonPath('data.results.0.meta.original_filename', 'resume.docx');

        $this->assertNotEmpty($response->json('data.results.0.meta.download_url'));
        $this->assertStringContainsString('/api/v1/message/resume-output/', $response->json('data.results.0.meta.download_url'));
        $this->assertCount(1, Storage::disk('local')->files('resume_uploads'));
        $this->assertCount(1, Storage::disk('local')->files('resume_outputs'));

        $assistant = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('role', 'assistant')
            ->firstOrFail();

        $this->assertSame(19, $assistant->metadata['sub_tool_id']);
        $this->assertSame('resume_builder', $assistant->metadata['tool_key']);
        $this->assertArrayNotHasKey('extracted_resume_text', $assistant->metadata['request_payload']);
        $this->assertArrayNotHasKey('path', $assistant->metadata['request_payload']['uploaded_file']);
    }

    public function test_resume_builder_rejects_doc_upload_with_415(): void
    {
        Storage::fake('local');
        [$user, $conversation] = $this->makeContext(19, 'Resume Builder', 'resume-builder', 'tasks/resume-builder/chat');
        Sanctum::actingAs($user);

        $writer = Mockery::mock(AiArabicWriterService::class);
        $writer->shouldNotReceive('generateReplyWithUsage');
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
        ])->assertStatus(415)
            ->assertJsonPath('message', 'DOC files are not supported yet. Please upload PDF or DOCX.');
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
