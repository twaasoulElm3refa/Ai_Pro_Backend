<?php

namespace Database\Seeders;

use App\Models\MainTools;
use App\Models\SubTools;
use App\Models\SubToolTranlation;
use Illuminate\Database\Seeder;

class KeywordGeneratorSeeder extends Seeder
{
    public function run(): void
    {
        $subTool = SubTools::withTrashed()->find(13);
        $hookGenerator = SubTools::withTrashed()->find(12);
        $mainToolId = $subTool?->main_tool_id
            ?? $hookGenerator?->main_tool_id
            ?? MainTools::query()->value('id');

        if (! $mainToolId) {
            $mainTool = MainTools::create([
                'name' => 'AI Content Tools',
                'meta_name' => 'AI Content Tools',
                'description' => 'AI-powered content creation tools.',
                'meta_description' => 'Generate keywords and other content assets with AI.',
                'slug' => 'ai-content-tools',
                'is_active' => true,
                'sort_order' => 1,
            ]);
            $mainToolId = $mainTool->id;
        }

        if (! $subTool) {
            $subTool = new SubTools;
            $subTool->id = 13;
        }

        $endpoint = trim((string) ($subTool->endpoint ?? '')) ?: '/generate/keywords';
        $existingConfig = is_array($subTool->config ?? null) ? $subTool->config : [];

        $config = array_replace($existingConfig, [
            'tool_key' => 'ai_keyword_generator',
            'model_key' => 'keyword_generator',
            'provider' => 'openrouter',
            'endpoint' => $endpoint,
            'system_prompt' => $this->systemPrompt(),
            'state_schema' => [
                'topic' => ['nullable', 'string', 'max:1000'],
                'industry' => ['nullable', 'string', 'max:150'],
                'target_audience' => ['nullable', 'string', 'max:250'],
                'language' => ['nullable', 'string', 'max:50'],
                'keyword_type' => ['nullable', 'string', 'max:100'],
                'search_intent' => ['nullable', 'string', 'max:100'],
                'location' => ['nullable', 'string', 'max:150'],
                'results_count' => ['nullable', 'integer', 'min:1', 'max:100'],
                'include_long_tail' => ['nullable', 'boolean'],
                'include_clusters' => ['nullable', 'boolean'],
                'extra_options' => [
                    'type' => 'array',
                    'nullable' => true,
                    'items' => ['string', 'max:150'],
                ],
                'last_output' => ['nullable', 'string', 'max:50000'],
            ],
            'default_state' => [
                'topic' => null,
                'industry' => null,
                'target_audience' => null,
                'language' => null,
                'keyword_type' => null,
                'search_intent' => null,
                'location' => null,
                'results_count' => null,
                'include_long_tail' => null,
                'include_clusters' => null,
                'extra_options' => [],
                'last_output' => null,
            ],
            'payload_map' => [
                'content' => 'user_message',
            ],
            'response_format' => 'results',
            'last_output_source' => 'first_result',
            'normalize_results' => true,
            'error_message' => 'Failed to generate keywords.',
        ]);

        $subTool->forceFill([
            'main_tool_id' => $mainToolId,
            'name' => 'Keyword Generator',
            'meta_name' => 'AI Keyword Generator',
            'description' => 'Generate SEO keywords with intent, type, and optional clusters.',
            'meta_description' => 'Generate organized keyword ideas for SEO and content planning.',
            'slug' => 'keyword-generator',
            'prompt_placeholder' => 'Generate 20 SEO keywords for an article about AI tools for content creators in arabic',
            'is_active' => true,
            'sort_order' => 13,
            'endpoint' => $endpoint,
            'config' => $config,
            'deleted_at' => null,
        ])->save();

        foreach ([
            'ar' => [
                'name' => 'مولد الكلمات المفتاحية',
                'prompt_placeholder' => 'اكتب موضوعًا أو طلبًا لتوليد كلمات مفتاحية...',
                'description' => 'ولّد كلمات مفتاحية للسيو مع نية البحث والنوع والمجموعات عند الحاجة.',
            ],
            'en' => [
                'name' => 'Keyword Generator',
                'prompt_placeholder' => 'Describe the keyword set you want to generate...',
                'description' => 'Generate SEO keywords with search intent, type, and optional clusters.',
            ],
        ] as $locale => $translation) {
            SubToolTranlation::query()->updateOrCreate(
                ['sub_tool_id' => $subTool->id, 'locale' => $locale],
                [
                    ...$translation,
                    'meta_name' => $translation['name'],
                    'meta_description' => $translation['description'],
                ]
            );
        }
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You are a professional SEO Keyword Generator.

Return valid JSON only. Do not return markdown or explanations outside JSON.

Infer missing state values from user_message when possible. Generate keyword ideas based on:
- topic
- industry
- target_audience
- language
- keyword_type
- search_intent
- location
- results_count
- include_long_tail
- include_clusters
- extra_options
- regenerate
- previous_output

Rules:
1. If results_count is missing, generate 20 keywords.
2. Avoid duplicates.
3. If language is Arabic, write natural Arabic keywords.
4. If include_long_tail is true, include long-tail keyword phrases.
5. If include_clusters is true, group useful keywords with a cluster name.
6. If previous_output exists and regenerate is true, produce a fresh variation and avoid repeating the same exact keywords.
7. Return only JSON.

Return exactly this JSON structure:

{
  "success": true,
  "type": "result",
  "tool": "ai_keyword_generator",
  "provider": "openrouter",
  "model_key": "keyword_generator",
  "state": {
    "topic": "",
    "industry": "",
    "target_audience": "",
    "language": "",
    "keyword_type": "",
    "search_intent": "",
    "location": null,
    "results_count": 20,
    "include_long_tail": true,
    "include_clusters": true,
    "extra_options": [],
    "last_output": ""
  },
  "results": [
    {
      "id": 1,
      "text": "",
      "title": null,
      "subject": null,
      "meta": {
        "type": null,
        "intent": null,
        "cluster": null
      }
    }
  ]
}
PROMPT;
    }
}
