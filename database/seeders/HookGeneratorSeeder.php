<?php

namespace Database\Seeders;

use App\Models\MainTools;
use App\Models\SubTools;
use App\Models\SubToolTranlation;
use Illuminate\Database\Seeder;

class HookGeneratorSeeder extends Seeder
{
    public function run(): void
    {
        $subTool = SubTools::withTrashed()->find(12);
        $ideaGenerator = SubTools::withTrashed()->find(11);
        $promptEnhancer = SubTools::withTrashed()->find(10);
        $mainToolId = $subTool?->main_tool_id
            ?? $ideaGenerator?->main_tool_id
            ?? $promptEnhancer?->main_tool_id
            ?? MainTools::query()->value('id');

        if (! $mainToolId) {
            $mainTool = MainTools::create([
                'name' => 'AI Content Tools',
                'meta_name' => 'AI Content Tools',
                'description' => 'AI-powered content creation tools.',
                'meta_description' => 'Generate hooks and other content assets with AI.',
                'slug' => 'ai-content-tools',
                'is_active' => true,
                'sort_order' => 1,
            ]);
            $mainToolId = $mainTool->id;
        }

        if (! $subTool) {
            $subTool = new SubTools;
            $subTool->id = 12;
        }

        $endpoint = trim((string) ($subTool->endpoint ?? '')) ?: '/generate/hooks';
        $existingConfig = is_array($subTool->config ?? null) ? $subTool->config : [];

        $config = array_replace($existingConfig, [
            'tool_key' => 'ai_hook_generator',
            'model_key' => 'hook_generator',
            'provider' => 'openrouter',
            'endpoint' => $endpoint,
            'system_prompt' => $this->systemPrompt(),
            'state_schema' => [
                'topic' => ['nullable', 'string', 'max:1000'],
                'platform' => ['nullable', 'string', 'max:100'],
                'content_type' => ['nullable', 'string', 'max:150'],
                'language' => ['nullable', 'string', 'max:50'],
                'tone' => ['nullable', 'string', 'max:100'],
                'audience' => ['nullable', 'string', 'max:250'],
                'hook_style' => ['nullable', 'string', 'max:100'],
                'length' => ['nullable', 'string', 'max:50'],
                'results_count' => ['nullable', 'integer', 'min:1', 'max:20'],
                'extra_options' => [
                    'type' => 'array',
                    'nullable' => true,
                    'items' => ['string', 'max:150'],
                ],
                'last_output' => ['nullable', 'string', 'max:10000'],
            ],
            'default_state' => [
                'topic' => null,
                'platform' => null,
                'content_type' => null,
                'language' => null,
                'tone' => null,
                'audience' => null,
                'hook_style' => null,
                'length' => null,
                'results_count' => null,
                'extra_options' => [],
                'last_output' => null,
            ],
            'payload_map' => [
                'content' => 'user_message',
            ],
            'response_format' => 'results',
            'last_output_source' => 'first_result',
            'normalize_results' => true,
            'error_message' => 'Failed to generate hooks.',
        ]);

        $subTool->forceFill([
            'main_tool_id' => $mainToolId,
            'name' => 'AI Hook Generator',
            'meta_name' => 'AI Hook Generator',
            'description' => 'Generate distinct, scroll-stopping hooks for digital content.',
            'meta_description' => 'Generate short hooks for social posts, videos, and campaigns.',
            'slug' => 'ai-hook-generator',
            'prompt_placeholder' => 'Describe the hooks you want to generate...',
            'is_active' => true,
            'sort_order' => 12,
            'endpoint' => $endpoint,
            'config' => $config,
            'deleted_at' => null,
        ])->save();

        foreach ([
            'ar' => [
                'name' => 'مولد الهوكات',
                'prompt_placeholder' => 'اكتب الموضوع أو المحتوى الذي تريد توليد هوكات له...',
                'description' => 'أنشئ هوكات قصيرة وقوية وجذابة للمحتوى الرقمي.',
            ],
            'en' => [
                'name' => 'AI Hook Generator',
                'prompt_placeholder' => 'Describe the hooks you want to generate...',
                'description' => 'Generate short, powerful, scroll-stopping hooks for digital content.',
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
You are a professional AI Hook Generator.

Your job is to generate strong, scroll-stopping hooks for digital content.

You must always return valid JSON only.
Do not return markdown.
Do not return explanations outside JSON.

The user may send incomplete state. Infer missing fields from the user_message and the existing state.

Generate hooks based on:
- topic
- platform
- content_type
- language
- tone
- audience
- hook_style
- length
- results_count
- extra_options

Rules:
1. Generate distinct hooks.
2. Do not repeat the same structure.
3. Avoid misleading clickbait.
4. Make the hook suitable for the selected platform.
5. If the language is Arabic, write natural Arabic.
6. If results_count is missing, generate 10 hooks.
7. If platform is LinkedIn, use a professional but engaging tone.
8. The hook must be short, clear, and powerful.
9. Do not write a full post, only hooks.
10. Return only JSON.

Return exactly this JSON structure:

{
  "state": {
    "topic": "",
    "platform": "",
    "content_type": "",
    "language": "",
    "tone": "",
    "audience": "",
    "hook_style": "",
    "length": "",
    "results_count": 10,
    "extra_options": [],
    "last_output": ""
  },
  "results": [
    {
      "id": 1,
      "text": "",
      "title": null,
      "subject": null,
      "meta": {}
    }
  ]
}
PROMPT;
    }
}
