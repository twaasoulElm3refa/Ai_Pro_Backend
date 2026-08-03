<?php

namespace Database\Seeders;

use App\Models\MainTools;
use App\Models\SubTools;
use Illuminate\Database\Seeder;

class ImagePromptGeneratorSeeder extends Seeder
{
    public function run(): void
    {
        $mainTool = MainTools::withTrashed()->find(5);

        if (! $mainTool) {
            $mainTool = MainTools::create([
                'id' => 5,
                'name' => 'AI Image Tools',
                'meta_name' => 'AI Image Tools',
                'description' => 'AI-powered image generation and editing tools.',
                'meta_description' => 'Generate images, remove backgrounds, and create image prompts with AI.',
                'slug' => 'ai-image-tools',
                'is_active' => true,
                'sort_order' => 5,
            ]);
        } elseif ($mainTool->trashed()) {
            $mainTool->restore();
        }

        $config = [
            'tool_key' => 'image_prompt_generator',
            'model_key' => 'image_prompt_generator',
            'endpoint' => 'tasks/image-prompt-generator/chat',
            'response_format' => 'results',
            'default_state' => [
                'content' => null,
                'language' => 'English',
                'style' => 'high-end editorial photography',
                'aspect_ratio' => '4:5',
                'camera' => 'medium-wide shot',
                'lighting' => 'cinematic side lighting',
                'negative_prompt' => 'text, watermark, blurry image, distorted anatomy',
                'text_policy' => 'No text',
                'face_policy' => 'No visible human faces',
                'results_count' => 1,
                'extra_options' => ['realistic materials', '8K detail'],
                'last_output' => null,
            ],
            'state_schema' => [
                'content' => ['nullable', 'string', 'max:10000'],
                'language' => ['required', 'string', 'max:80'],
                'style' => ['required', 'string', 'max:150'],
                'aspect_ratio' => ['required', 'string', 'max:30'],
                'camera' => ['required', 'string', 'max:150'],
                'lighting' => ['required', 'string', 'max:150'],
                'negative_prompt' => ['nullable', 'string', 'max:5000'],
                'text_policy' => ['required', 'string', 'max:150'],
                'face_policy' => ['required', 'string', 'max:150'],
                'results_count' => ['required', 'integer', 'min:1', 'max:5'],
                'extra_options' => [
                    'type' => 'array',
                    'nullable' => true,
                    'items' => ['string', 'max:150'],
                ],
                'last_output' => ['nullable'],
            ],
            'payload_map' => [
                'task_key' => 'config.tool_key',
            ],
            'last_output_source' => 'content',
        ];

        $subTool = SubTools::withTrashed()->find(24);
        $attributes = [
            'main_tool_id' => $mainTool->id,
            'name' => 'Image Prompt Generator',
            'meta_name' => 'AI Image Prompt Generator',
            'description' => 'Turn an image idea into a professional prompt ready for image generation tools.',
            'meta_description' => 'Create polished image prompts with style, camera, lighting, and negative prompt controls.',
            'prompt_placeholder' => 'Describe the image idea you want to turn into a professional prompt...',
            'slug' => 'image-prompt-generator',
            'endpoint' => 'tasks/image-prompt-generator/chat',
            'config' => $config,
            'is_active' => true,
            'sort_order' => 3,
        ];

        if ($subTool) {
            $subTool->fill($attributes);
            $subTool->restore();
            $subTool->save();

            return;
        }

        SubTools::create(['id' => 24, ...$attributes]);
    }
}
