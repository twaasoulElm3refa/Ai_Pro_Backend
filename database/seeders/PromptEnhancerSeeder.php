<?php

namespace Database\Seeders;

use App\Models\MainTools;
use App\Models\SubTools;
use App\Models\SubToolTranlation;
use Illuminate\Database\Seeder;

class PromptEnhancerSeeder extends Seeder
{
    public function run(): void
    {
        $subTool = SubTools::withTrashed()->find(10);
        $promptGenerator = SubTools::withTrashed()->find(9);
        $mainToolId = $subTool?->main_tool_id
            ?? $promptGenerator?->main_tool_id
            ?? MainTools::query()->value('id');

        if (! $mainToolId) {
            $mainTool = MainTools::create([
                'name' => 'AI Prompt Tools',
                'meta_name' => 'AI Prompt Tools',
                'description' => 'AI-powered prompt creation and improvement tools.',
                'meta_description' => 'Create and improve prompts for AI models.',
                'slug' => 'ai-prompt-tools',
                'is_active' => true,
                'sort_order' => 1,
            ]);
            $mainToolId = $mainTool->id;
        }

        if (! $subTool) {
            $subTool = new SubTools;
            $subTool->id = 10;
        }

        $endpoint = trim((string) ($subTool->endpoint ?? '')) ?: '/enhance/prompt';
        $systemPrompt = <<<'PROMPT'
You are an expert AI prompt enhancer.
Your task is to improve the user's original prompt while preserving the original intent.
Make the prompt clearer, more structured, more specific, and more effective for AI models.
Add useful constraints only when they improve the result.
Do not change the user's core goal.
If the user specifies a target AI tool, adapt the prompt for that tool.
If language is Auto Detect, respond in the same language as the original prompt.
If output_format is "Improved prompt only", return only the improved prompt without explanations.
If results_count is more than 1, return multiple alternative improved prompts.
Avoid unnecessary complexity.
Make the final prompt practical, direct, and ready to copy.
PROMPT;

        $existingConfig = is_array($subTool->config ?? null) ? $subTool->config : [];
        $config = array_replace($existingConfig, [
            'tool_key' => 'ai_prompt_enhancer',
            'model_key' => 'prompt_enhancer',
            'provider' => 'openrouter',
            'endpoint' => $endpoint,
            'system_prompt' => $systemPrompt,
            'response_format' => 'results',
            'error_message' => 'Failed to enhance prompt.',
            'default_state_mode' => 'null_or_empty',
            'state_schema' => [
                'original_prompt' => ['nullable', 'string', 'max:5000'],
                'target_ai_tool' => ['nullable', 'string', 'max:100'],
                'language' => ['nullable', 'string', 'max:50'],
                'enhancement_goal' => ['nullable', 'string', 'max:150'],
                'tone' => ['nullable', 'string', 'max:100'],
                'output_format' => ['nullable', 'string', 'max:100'],
                'detail_level' => ['nullable', 'string', 'max:50'],
                'preserve_intent' => ['nullable', 'boolean'],
                'results_count' => ['nullable', 'integer', 'min:1', 'max:3'],
                'extra_options' => [
                    'type' => 'array',
                    'nullable' => true,
                    'items' => ['string', 'max:150'],
                ],
                'last_output' => ['nullable', 'string', 'max:20000'],
            ],
            'default_state' => [
                'original_prompt' => null,
                'target_ai_tool' => 'Any AI model',
                'language' => 'Auto Detect',
                'enhancement_goal' => 'Make it clearer and more effective',
                'tone' => 'Clear and practical',
                'output_format' => 'Improved prompt only',
                'detail_level' => 'Medium',
                'preserve_intent' => true,
                'results_count' => 1,
                'extra_options' => [
                    'Improve structure',
                    'Add useful constraints',
                ],
                'last_output' => null,
            ],
            'state_extractors' => [
                'original_prompt' => [
                    'source' => 'user_message',
                    'strip_prefixes' => [
                        'Improve this prompt:',
                        'Enhance this prompt:',
                        'حسّن هذا البرومبت:',
                        'حسن هذا البرومبت:',
                        'تحسين هذا البرومبت:',
                    ],
                ],
            ],
            'payload_map' => [
                'content' => 'user_message',
            ],
        ]);

        $subTool->forceFill([
            'main_tool_id' => $mainToolId,
            'name' => 'Prompt Enhancer',
            'meta_name' => 'AI Prompt Enhancer',
            'description' => 'Improve weak prompts while preserving their original intent.',
            'meta_description' => 'Make AI prompts clearer, structured, specific, and ready to use.',
            'slug' => 'ai-prompt-enhancer',
            'prompt_placeholder' => 'Enter the prompt you want to improve...',
            'is_active' => true,
            'sort_order' => 10,
            'endpoint' => $endpoint,
            'config' => $config,
            'deleted_at' => null,
        ])->save();

        $translations = [
            'ar' => [
                'name' => 'محسن البرومبتات',
                'prompt_placeholder' => 'اكتب البرومبت الذي تريد تحسينه...',
                'description' => 'حسّن البرومبت ليصبح أوضح وأكثر تنظيمًا مع الحفاظ على نيتك الأصلية.',
            ],
            'en' => [
                'name' => 'Prompt Enhancer',
                'prompt_placeholder' => 'Enter the prompt you want to improve...',
                'description' => 'Improve prompts while preserving the original intent.',
            ],
        ];

        foreach ($translations as $locale => $translation) {
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
}
