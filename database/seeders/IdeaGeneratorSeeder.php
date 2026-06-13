<?php

namespace Database\Seeders;

use App\Models\MainTools;
use App\Models\SubTools;
use App\Models\SubToolTranlation;
use Illuminate\Database\Seeder;

class IdeaGeneratorSeeder extends Seeder
{
    public function run(): void
    {
        $subTool = SubTools::withTrashed()->find(11);
        $promptEnhancer = SubTools::withTrashed()->find(10);
        $promptGenerator = SubTools::withTrashed()->find(9);
        $mainToolId = $subTool?->main_tool_id
            ?? $promptEnhancer?->main_tool_id
            ?? $promptGenerator?->main_tool_id
            ?? MainTools::query()->value('id');

        if (! $mainToolId) {
            $mainTool = MainTools::create([
                'name' => 'AI Idea Tools',
                'meta_name' => 'AI Idea Tools',
                'description' => 'AI-powered idea generation tools.',
                'meta_description' => 'Generate useful ideas for content, products, and campaigns.',
                'slug' => 'ai-idea-tools',
                'is_active' => true,
                'sort_order' => 1,
            ]);
            $mainToolId = $mainTool->id;
        }

        if (! $subTool) {
            $subTool = new SubTools;
            $subTool->id = 11;
        }

        $endpoint = trim((string) ($subTool->endpoint ?? '')) ?: '/generate/ideas';
        $existingConfig = is_array($subTool->config ?? null) ? $subTool->config : [];

        $config = array_replace($existingConfig, [
            'tool_key' => 'ai_idea_generator',
            'model_key' => 'idea_generator',
            'provider' => 'openrouter',
            'endpoint' => $endpoint,
            'system_prompt' => implode("\n", [
                'You are an expert AI idea generator.',
                'Generate distinct, useful, and actionable ideas that match the user request and state.',
                'Respect the requested language, audience, industry, tone, creativity level, and result count.',
                'Avoid repetition and make every idea meaningfully different.',
                'When titles are requested, provide a concise title for each idea.',
                'When descriptions are requested, provide a practical description in text.',
                'Return structured results with id, title, text, subject, and meta.',
            ]),
            'state_schema' => [
                'topic' => ['nullable', 'string', 'max:500'],
                'idea_type' => ['nullable', 'string', 'max:100'],
                'industry' => ['nullable', 'string', 'max:150'],
                'audience' => ['nullable', 'string', 'max:250'],
                'language' => ['nullable', 'string', 'max:50'],
                'tone' => ['nullable', 'string', 'max:100'],
                'creativity_level' => ['nullable', 'string', 'max:50'],
                'results_count' => ['nullable', 'integer', 'min:1', 'max:20'],
                'include_titles' => ['nullable', 'boolean'],
                'include_descriptions' => ['nullable', 'boolean'],
                'extra_options' => [
                    'type' => 'array',
                    'nullable' => true,
                    'items' => ['string', 'max:150'],
                ],
                'last_output' => ['nullable'],
            ],
            'default_state' => [
                'topic' => null,
                'idea_type' => null,
                'industry' => null,
                'audience' => null,
                'language' => null,
                'tone' => null,
                'creativity_level' => null,
                'results_count' => null,
                'include_titles' => null,
                'include_descriptions' => null,
                'extra_options' => [],
                'last_output' => null,
            ],
            'payload_map' => [
                'content' => 'user_message',
            ],
            'response_format' => 'results',
            'error_message' => 'Failed to generate ideas.',
        ]);

        $subTool->forceFill([
            'main_tool_id' => $mainToolId,
            'name' => 'Idea Generator',
            'meta_name' => 'AI Idea Generator',
            'description' => 'Generate creative, actionable ideas for content, products, and campaigns.',
            'meta_description' => 'Generate structured AI ideas with titles and descriptions.',
            'slug' => 'ai-idea-generator',
            'prompt_placeholder' => 'Describe the ideas you want to generate...',
            'is_active' => true,
            'sort_order' => 11,
            'endpoint' => $endpoint,
            'config' => $config,
            'deleted_at' => null,
        ])->save();

        foreach ([
            'ar' => [
                'name' => 'مولد الأفكار',
                'prompt_placeholder' => 'اكتب الموضوع أو نوع الأفكار التي تريد توليدها...',
                'description' => 'ولّد أفكارًا إبداعية وعملية مع عناوين وأوصاف واضحة.',
            ],
            'en' => [
                'name' => 'Idea Generator',
                'prompt_placeholder' => 'Describe the ideas you want to generate...',
                'description' => 'Generate creative, actionable ideas with clear titles and descriptions.',
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
}
