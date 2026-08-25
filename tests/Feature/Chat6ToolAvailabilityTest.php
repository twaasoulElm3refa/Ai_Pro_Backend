<?php

namespace Tests\Feature;

use Tests\TestCase;

class Chat6ToolAvailabilityTest extends TestCase
{
    public function test_chat6_contains_youtube_and_speech_while_chat5_redirects_legacy_youtube_urls(): void
    {
        $chat5 = file_get_contents(resource_path('js/views/home/chat5.vue'));
        $chat6 = file_get_contents(resource_path('js/views/home/chat6.vue'));
        $home = file_get_contents(resource_path('js/views/home/home.vue'));

        $this->assertStringContainsString('YOUTUBE_SUMMARIZER_TOOL', $chat6);
        $this->assertStringContainsString('sub_tool_id: 25', $chat6);
        $this->assertStringContainsString('SPEECH_TO_TEXT_TOOL', $chat6);
        $this->assertStringContainsString('sub_tool_id: 26', $chat6);
        $this->assertStringContainsString('/chat6${suffix}', $chat6);
        $this->assertStringNotContainsString('/chat5${suffix}', $chat6);
        $this->assertStringContainsString('Number(data?.id || data?.sub_tool_id) === YOUTUBE_SUMMARIZER_TOOL.sub_tool_id', $chat5);
        $this->assertStringContainsString('/chat6${suffix}', $chat5);
        $this->assertStringContainsString('6: "chat6"', $home);
    }

    public function test_speech_frontend_uses_the_required_two_part_form_data_contract(): void
    {
        $chat6 = file_get_contents(resource_path('js/views/home/chat6.vue'));
        $client = file_get_contents(resource_path('js/services/chat/chatServices.js'));
        $speechService = file_get_contents(app_path('Services/SpeechToTextService.php'));

        $this->assertStringContainsString('const formData = new FormData()', $chat6);
        $this->assertSame(2, substr_count($chat6, 'formData.append('));
        $this->assertStringContainsString('formData.append("file", file)', $chat6);
        $this->assertStringContainsString('formData.append("payload", JSON.stringify(payload))', $chat6);
        $this->assertStringContainsString('user_id: Number(conversation?.user_id) || null', $chat6);
        $this->assertStringContainsString('conversation_uuid: conversation.uuid', $chat6);
        $this->assertStringContainsString('language: "ar"', $chat6);
        $this->assertStringNotContainsString('include_segments', $chat6);
        $this->assertStringNotContainsString('segments', $chat6);
        $this->assertStringContainsString('await chatServices.sendMessageFormData(', $chat6);
        $this->assertStringContainsString('result.transcript ||', $chat6);
        $this->assertStringContainsString('if (isSpeechToText.value && !selectedFile.value)', $chat6);
        $this->assertStringContainsString('if (isSending.value) return', $chat6);
        $this->assertStringContainsString('messages.value = messages.value.filter((item) => item.localKey !== optimisticKey)', $chat6);
        $this->assertStringContainsString('isSending.value = false', $chat6);
        $this->assertStringNotContainsString('Content-Type', $client);
        $this->assertStringNotContainsString('segments', $speechService);
        $this->assertStringNotContainsString("['usage']", $speechService);
    }
}
