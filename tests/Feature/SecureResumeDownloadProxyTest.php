<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\MainTools;
use App\Models\Message;
use App\Models\SubTools;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SecureResumeDownloadProxyTest extends TestCase
{
    use RefreshDatabase;

    public function test_resume_download_keeps_the_internal_key_on_the_server(): void
    {
        config()->set('services.aiarabic.public_base_url', 'https://ai.internal.test');
        config()->set('services.aiarabic.internal_api_key', 'server-only-key');
        Http::fake([
            'https://ai.internal.test/tasks/resume-builder/download/resume-file-1' => Http::response(
                'document bytes',
                200,
                ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']
            ),
        ]);

        $user = User::factory()->create();
        $mainTool = MainTools::create([
            'name' => 'Resume tools '.Str::random(6),
            'slug' => 'resume-tools-'.Str::random(6),
        ]);
        $subTool = SubTools::create([
            'id' => 19,
            'main_tool_id' => $mainTool->id,
            'name' => 'Resume Builder '.Str::random(6),
            'slug' => 'resume-builder-'.Str::random(6),
        ]);
        $conversation = Conversation::create([
            'user_id' => $user->id,
            'sub_tool_id' => $subTool->id,
            'uuid' => (string) Str::uuid(),
        ]);
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Resume generated successfully.',
            'metadata' => [
                'file' => [
                    'file_id' => 'resume-file-1',
                    'filename' => 'resume.docx',
                    'download_url' => '/tasks/resume-builder/download/resume-file-1',
                ],
            ],
        ]);

        Sanctum::actingAs($user);

        $this->get("/api/v1/message/{$message->id}/resume-file")
            ->assertOk()
            ->assertDownload('resume.docx');

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://ai.internal.test/tasks/resume-builder/download/resume-file-1'
                && $request->header('x-internal-api-key')[0] === 'server-only-key';
        });

        $this->assertStringNotContainsString(
            'x-internal-api-key',
            file_get_contents(resource_path('js/views/home/chat4.vue'))
        );
        $this->assertStringNotContainsString(
            'x-internal-api-key',
            file_get_contents(resource_path('js/views/home/chat5.vue'))
        );
    }
}
