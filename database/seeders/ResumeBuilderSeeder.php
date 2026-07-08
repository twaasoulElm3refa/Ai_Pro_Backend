<?php

namespace Database\Seeders;

use App\Models\MainTools;
use App\Models\SubTools;
use App\Models\SubToolTranlation;
use Illuminate\Database\Seeder;

class ResumeBuilderSeeder extends Seeder
{
    public function run(): void
    {
        $subTool = SubTools::withTrashed()->find(19);
        $mainToolId = $subTool?->main_tool_id
            ?? SubTools::withTrashed()->find(17)?->main_tool_id
            ?? SubTools::withTrashed()->find(18)?->main_tool_id
            ?? SubTools::withTrashed()->find(20)?->main_tool_id
            ?? MainTools::query()->find(4)?->id
            ?? MainTools::query()->value('id');

        if (! $mainToolId) {
            $mainTool = MainTools::create([
                'name' => 'AI Utility Tools',
                'meta_name' => 'AI Utility Tools',
                'description' => 'AI-powered tools for analysis, rewriting, business naming, and resumes.',
                'meta_description' => 'AI utility tools for everyday professional workflows.',
                'slug' => 'ai-utility-tools',
                'is_active' => true,
                'sort_order' => 4,
            ]);
            $mainToolId = $mainTool->id;
        }

        if (! $subTool) {
            $subTool = new SubTools;
            $subTool->id = 19;
        }

        $endpoint = trim((string) ($subTool->endpoint ?? '')) ?: 'tasks/resume-builder/chat';
        $existingConfig = is_array($subTool->config ?? null) ? $subTool->config : [];
        $config = array_replace($existingConfig, [
            'tool_key' => 'resume_builder',
            'model_key' => 'resume_builder',
            'provider' => 'openrouter',
            'endpoint' => $endpoint,
            'response_format' => 'results',
            'normalize_results' => true,
            'supports_multipart' => true,
            'max_upload_kb' => 10240,
            'allowed_uploads' => ['pdf', 'doc', 'docx'],
            'default_state' => [
                'target_role' => 'Senior Laravel Developer',
                'candidate_name' => null,
                'language' => 'English',
                'tone' => 'Professional',
                'experience_level' => 'Senior',
                'resume_style' => 'ATS-friendly modern',
                'output_format' => 'docx',
                'sections_to_include' => ['Summary', 'Skills', 'Experience', 'Education', 'Certifications', 'Projects', 'Languages'],
                'extra_options' => ['Improve clarity', 'Use strong action verbs', 'Keep it honest', 'Do not invent experience'],
                'last_output' => null,
            ],
        ]);

        $subTool->forceFill([
            'main_tool_id' => $mainToolId,
            'name' => 'Resume Builder',
            'meta_name' => 'Resume Builder',
            'description' => 'Build or improve resumes with ATS-friendly structure.',
            'meta_description' => 'Create and improve professional resumes for a target role.',
            'slug' => 'resume-builder',
            'prompt_placeholder' => 'Improve this resume for a Senior Laravel Developer role and make it ATS-friendly.',
            'is_active' => true,
            'sort_order' => 19,
            'endpoint' => $endpoint,
            'config' => $config,
            'deleted_at' => null,
        ])->save();

        foreach ([
            'ar' => [
                'name' => 'Ù…Ù†Ø´Ø¦ Ø§Ù„Ø³ÙŠØ±Ø© Ø§Ù„Ø°Ø§ØªÙŠØ©',
                'prompt_placeholder' => 'Ø­Ø³Ù† Ø§Ù„Ø³ÙŠØ±Ø© Ø§Ù„Ø°Ø§ØªÙŠØ© Ù„ÙˆØ¸ÙŠÙØ© Senior Laravel Developer.',
                'description' => 'ØªØ­Ø³ÙŠÙ† ÙˆØ¥Ù†Ø´Ø§Ø¡ Ø§Ù„Ø³ÙŠØ±Ø© Ø§Ù„Ø°Ø§ØªÙŠØ© Ø¨ØªÙ†Ø³ÙŠÙ‚ Ù…Ù†Ø§Ø³Ø¨ Ù„Ø£Ù†Ø¸Ù…Ø© ATS.',
            ],
            'en' => [
                'name' => 'Resume Builder',
                'prompt_placeholder' => 'Improve this resume for a Senior Laravel Developer role and make it ATS-friendly.',
                'description' => 'Build or improve resumes with ATS-friendly structure.',
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
