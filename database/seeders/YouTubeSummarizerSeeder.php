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
        $mainToolId = $existing?->main_tool_id
            ?? SubTools::withTrashed()
                ->where('slug', 'ai-text-summarizer')
                ->value('main_tool_id')
            ?? MainTools::query()->oldest('id')->value('id');

        if (! $mainToolId) {
            $mainTool = MainTools::firstOrCreate(
                ['slug' => 'ai-content-tools'],
                [
                    'name' => 'AI Content Tools',
                    'meta_name' => 'AI Content Tools',
                    'description' => 'Create, summarize, and improve content with AI.',
                    'meta_description' => 'AI tools for creating and summarizing content.',
                    'is_active' => true,
                    'sort_order' => 1,
                ]
            );
            $mainToolId = $mainTool->id;
        }

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
