<?php

namespace Database\Seeders;

use App\Models\MainTools;
use App\Models\SubTools;
use Illuminate\Database\Seeder;

class ImageGeneratorSeeder extends Seeder
{
    public function run(): void
    {
        $mainTool = MainTools::withTrashed()->find(5);

        if (! $mainTool) {
            $mainTool = MainTools::create([
                'id' => 5,
                'name' => 'AI Image Generator',
                'meta_name' => 'AI Image Generator',
                'description' => 'Generate images from text descriptions with artificial intelligence.',
                'meta_description' => 'Generate images from text descriptions with artificial intelligence.',
                'slug' => 'ai-image-generator',
                'is_active' => true,
                'sort_order' => 5,
            ]);
        } elseif ($mainTool->trashed()) {
            $mainTool->restore();
        }

        $config = [
            'tool_key' => 'ai_image_generator',
            'model_key' => 'image_generator',
            'endpoint' => 'tasks/image-generator/chat',
            'response_format' => 'files',
            'default_state' => [
                'provider' => null,
                'negative_prompt' => '',
                'size' => '1024x1024',
                'quality' => 'medium',
                'results_count' => 1,
                'output_format' => 'png',
                'seed' => null,
                'extra_options' => [],
                'last_output' => null,
            ],
            'state_schema' => [
                'provider' => ['nullable', 'string', 'max:100'],
                'negative_prompt' => ['nullable', 'string', 'max:5000'],
                'size' => ['required', 'string', 'in:512x512,768x768,1024x1024,1024x1536,1536x1024'],
                'quality' => ['required', 'string', 'in:low,medium,high'],
                'results_count' => ['required', 'integer', 'min:1', 'max:4'],
                'output_format' => ['required', 'string', 'in:png,jpg,jpeg,webp'],
                'seed' => ['nullable', 'integer'],
                'extra_options' => ['nullable', 'array'],
                'last_output' => ['nullable'],
            ],
        ];

        $subTool = SubTools::withTrashed()->find(21);
        $attributes = [
            'main_tool_id' => $mainTool->id,
            'name' => 'AI Image Generator',
            'meta_name' => 'AI Image Generator',
            'description' => 'Create one or more images from a detailed text prompt.',
            'meta_description' => 'Create images from text prompts with configurable size, quality, and format.',
            'prompt_placeholder' => 'Describe the image you want to generate...',
            'slug' => 'ai-image-generator',
            'endpoint' => 'tasks/image-generator/chat',
            'config' => $config,
            'is_active' => true,
            'sort_order' => 1,
        ];

        if ($subTool) {
            $subTool->fill($attributes);
            $subTool->restore();
            $subTool->save();

            return;
        }

        SubTools::create(['id' => 21, ...$attributes]);
    }
}
