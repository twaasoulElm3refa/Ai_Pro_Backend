<?php

namespace Database\Seeders;

use App\Models\MainTools;
use App\Models\SubTools;
use App\Services\SpeechToTextService;
use Illuminate\Database\Seeder;

class SpeechToTextSeeder extends Seeder
{
    public function run(): void
    {
        $chat6Tool = MainTools::withTrashed()->find(6);

        if (! $chat6Tool) {
            $chat6Tool = MainTools::forceCreate([
                'id' => 6,
                'name' => 'Audio & Video AI Tools',
                'meta_name' => 'Audio & Video AI Tools',
                'description' => 'Summarize video and transcribe audio with AI.',
                'meta_description' => 'AI tools for YouTube summaries and speech-to-text transcription.',
                'slug' => 'audio-video-ai-tools',
                'is_active' => true,
                'sort_order' => 6,
            ]);
        } elseif ($chat6Tool->trashed()) {
            $chat6Tool->restore();
        }

        $attributes = [
            'main_tool_id' => $chat6Tool->id,
            'name' => 'Speech To Text',
            'meta_name' => 'AI Speech To Text',
            'description' => 'Upload an audio file and convert its speech into an accurate transcript.',
            'meta_description' => 'Transcribe uploaded audio into text with AI.',
            'prompt_placeholder' => 'Upload an audio file to transcribe...',
            'slug' => SpeechToTextService::TOOL_KEY,
            'endpoint' => SpeechToTextService::ENDPOINT,
            'config' => [
                'tool_key' => SpeechToTextService::TOOL_KEY,
                'model_key' => SpeechToTextService::MODEL_KEY,
                'provider' => SpeechToTextService::DEFAULT_PROVIDER,
                'model' => SpeechToTextService::DEFAULT_MODEL,
                'endpoint' => SpeechToTextService::ENDPOINT,
                'request_type' => 'multipart/form-data',
                'default_state' => [
                    'provider' => null,
                    'language' => 'ar',
                    'extra_options' => [],
                    'last_output' => null,
                ],
            ],
            'is_active' => true,
            'sort_order' => 26,
        ];

        $existing = SubTools::withTrashed()->find(SpeechToTextService::SUB_TOOL_ID);

        if ($existing) {
            $existing->fill($attributes);
            $existing->restore();
            $existing->save();

            return;
        }

        SubTools::create([
            'id' => SpeechToTextService::SUB_TOOL_ID,
            ...$attributes,
        ]);
    }
}
