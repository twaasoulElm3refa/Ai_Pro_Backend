<?php

namespace Database\Seeders;

use App\Models\MainTools;
use App\Models\SubTools;
use App\Services\YouTubeSummarizerService;
use Illuminate\Database\Seeder;

class YouTubeSummarizerSeeder extends Seeder
{
    public function run(): void
    {
        $existing = SubTools::withTrashed()->find(YouTubeSummarizerService::SUB_TOOL_ID);
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

        $mainToolId = $chat6Tool->id;

        $config = [
            'tool_key' => YouTubeSummarizerService::TOOL_KEY,
            'model_key' => YouTubeSummarizerService::MODEL_KEY,
            'endpoint' => YouTubeSummarizerService::ENDPOINT,
            'response_format' => 'summary',
            'default_state' => [
                'transcript_languages' => ['ar', 'en'],
                'summary_language' => 'Arabic',
                'summary_style' => 'structured summary with a headline and key points',
                'max_summary_words' => 1000,
                'extra_options' => [],
                'last_output' => null,
            ],
            'state_schema' => [
                'transcript_languages' => [
                    'required', 'array', 'min:1', 'max:5',
                    'items' => ['required', 'string', 'regex:/^[a-z]{2,3}(?:-[A-Za-z]{2,4})?$/'],
                ],
                'summary_language' => ['required', 'string', 'max:80'],
                'summary_style' => ['required', 'string', 'max:500'],
                'max_summary_words' => ['required', 'integer', 'min:50', 'max:10000'],
                'extra_options' => [
                    'nullable', 'array', 'max:20',
                    'items' => ['string', 'max:150'],
                ],
                'last_output' => ['nullable', 'string', 'max:100000'],
            ],
        ];

        $attributes = [
            'main_tool_id' => $mainToolId,
            'name' => 'YouTube Summarizer',
            'meta_name' => 'AI YouTube Summarizer',
            'description' => 'Summarize a YouTube video from its available transcript.',
            'meta_description' => 'Generate a structured, language-aware summary of a YouTube video.',
            'prompt_placeholder' => 'Paste a YouTube video URL to summarize...',
            'slug' => YouTubeSummarizerService::TOOL_KEY,
            'endpoint' => YouTubeSummarizerService::ENDPOINT,
            'config' => $config,
            'is_active' => true,
            'sort_order' => 25,
        ];

        if ($existing) {
            $existing->fill($attributes);
            $existing->restore();
            $existing->save();

            return;
        }

        SubTools::create([
            'id' => YouTubeSummarizerService::SUB_TOOL_ID,
            ...$attributes,
        ]);
    }
}
