<?php

namespace Database\Seeders;

use App\Models\MainTools;
use App\Models\SubTools;
use App\Services\TextToSpeechService;
use Illuminate\Database\Seeder;

class TextToSpeechSeeder extends Seeder
{
    public function run(): void
    {
        $chat6Tool = MainTools::withTrashed()->find(6);

        if (! $chat6Tool) {
            $chat6Tool = MainTools::forceCreate([
                'id' => 6,
                'name' => 'Audio & Video AI Tools',
                'meta_name' => 'Audio & Video AI Tools',
                'description' => 'Generate, transcribe, and summarize audio and video with AI.',
                'meta_description' => 'AI tools for text-to-speech, transcription, and YouTube summaries.',
                'slug' => 'audio-video-ai-tools',
                'is_active' => true,
                'sort_order' => 6,
            ]);
        } elseif ($chat6Tool->trashed()) {
            $chat6Tool->restore();
        }

        $attributes = [
            'main_tool_id' => $chat6Tool->id,
            'name' => 'Text to Voice',
            'meta_name' => 'AI Text to Voice',
            'description' => 'Convert written text into a playable MP3 voice recording.',
            'meta_description' => 'Generate natural speech audio from text with AI.',
            'prompt_placeholder' => 'Enter the text you want to convert to voice...',
            'slug' => TextToSpeechService::TOOL_KEY,
            'endpoint' => TextToSpeechService::ENDPOINT,
            'config' => [
                'tool_key' => TextToSpeechService::TOOL_KEY,
                'model_key' => TextToSpeechService::MODEL_KEY,
                'provider' => TextToSpeechService::DEFAULT_PROVIDER,
                'model' => TextToSpeechService::DEFAULT_MODEL,
                'endpoint' => TextToSpeechService::ENDPOINT,
                'request_type' => 'application/json',
                'default_state' => [
                    'provider' => null,
                    'model' => null,
                    'voice' => 'alloy',
                    'response_format' => 'mp3',
                    'speed' => 1.0,
                    'extra_options' => [],
                    'last_output' => null,
                ],
            ],
            'is_active' => true,
            'sort_order' => 27,
        ];

        $existing = SubTools::withTrashed()->find(TextToSpeechService::SUB_TOOL_ID);
        if ($existing) {
            $existing->fill($attributes);
            $existing->restore();
            $existing->save();

            return;
        }

        SubTools::create(['id' => TextToSpeechService::SUB_TOOL_ID, ...$attributes]);
    }
}
