<?php

namespace Database\Seeders;

use App\Models\MainTools;
use App\Models\SubTools;
use Illuminate\Database\Seeder;

class PromptGeneratorSeeder extends Seeder
{
    public function run(): void
    {
        $subTool = SubTools::withTrashed()->find(9);
        $mainToolId = $subTool?->main_tool_id ?? MainTools::query()->value('id');

        if (! $mainToolId) {
            $mainTool = MainTools::create([
                'name' => 'AI Content Tools',
                'meta_name' => 'AI Content Tools',
                'description' => 'AI-powered content generation tools.',
                'meta_description' => 'AI-powered content generation tools.',
                'slug' => 'ai-content-tools',
                'is_active' => true,
                'sort_order' => 1,
            ]);
            $mainToolId = $mainTool->id;
        }

        if (! $subTool) {
            $subTool = new SubTools;
            $subTool->id = 9;
            $subTool->main_tool_id = $mainToolId;
        }

        $endpoint = trim((string) ($subTool->endpoint ?? ''));
        if ($endpoint === '') {
            $endpoint = '/generate/prompt';
        }

        $existingConfig = is_array($subTool->config ?? null) ? $subTool->config : [];
        $config = array_replace($existingConfig, [
            'tool_key' => 'ai_prompt_generator',
            'model_key' => 'prompt_generator',
            'provider' => 'openrouter',
            'endpoint' => $endpoint,
            'state_schema' => [
                'task' => ['nullable', 'string', 'max:5000'],
                'target_ai_tool' => ['nullable', 'string', 'max:100'],
                'output_type' => ['nullable', 'string', 'max:100'],
                'language' => ['nullable', 'string', 'max:50'],
                'tone' => ['nullable', 'string', 'max:50'],
                'audience' => ['nullable', 'string', 'max:250'],
                'prompt_style' => ['nullable', 'string', 'max:100'],
                'detail_level' => ['nullable', 'string', 'max:100'],
                'include_constraints' => ['nullable', 'boolean'],
                'results_count' => ['nullable', 'integer', 'min:1', 'max:10'],
                'extra_options' => [
                    'type' => 'array',
                    'nullable' => true,
                    'items' => ['string', 'max:150'],
                ],
                'last_output' => ['nullable'],
            ],
            'default_state' => [
                'task' => null,
                'target_ai_tool' => null,
                'output_type' => null,
                'language' => null,
                'tone' => null,
                'audience' => null,
                'prompt_style' => null,
                'detail_level' => null,
                'include_constraints' => null,
                'results_count' => null,
                'extra_options' => [],
                'last_output' => null,
            ],
            'payload_map' => [
                'content' => 'user_message',
            ],
            'response_format' => 'results',
        ]);

        $subTool->forceFill([
            'main_tool_id' => $mainToolId,
            'name' => 'Prompt Generator',
            'meta_name' => 'AI Prompt Generator',
            'description' => 'Generate detailed prompts for ChatGPT and other AI tools.',
            'meta_description' => 'Create structured, reusable prompts for AI tools.',
            'slug' => 'ai-prompt-generator',
            'prompt_placeholder' => 'Describe the prompt you want to generate',
            'is_active' => true,
            'sort_order' => 9,
            'endpoint' => $endpoint,
            'config' => $config,
            'deleted_at' => null,
        ])->save();
    }
}
