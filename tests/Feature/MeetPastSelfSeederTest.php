<?php

namespace Tests\Feature;

use App\Models\SubTools;
use Database\Seeders\TrendsChatSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeetPastSelfSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_registers_meet_past_self_without_duplicates(): void
    {
        $this->seed(TrendsChatSeeder::class);
        $this->seed(TrendsChatSeeder::class);

        $tool = SubTools::query()->findOrFail(33);

        $this->assertSame(7, (int) $tool->main_tool_id);
        $this->assertSame('meet-past-self', $tool->slug);
        $this->assertSame('tasks/trends/meet-past-self', $tool->endpoint);
        $this->assertSame([46], $tool->allowed_model_ids);
        $this->assertSame('runware', $tool->config['provider']);
        $this->assertSame('bfl:5@1', $tool->config['model']);
        $this->assertSame('image_edit', $tool->config['operation']);
        $this->assertSame('AI Image Tools', $tool->config['category']);
        $this->assertSame('تحرير الصور وإنشاء صور بالذكاء الاصطناعي.', $tool->config['task']);
        $this->assertSame(
            "Create a realistic cinematic image showing the user's current self meeting their younger past self. Preserve the exact identity, face features, hairstyle, and natural appearance of the uploaded person. Show both versions together in one realistic scene with emotional storytelling, cinematic lighting, realistic skin texture, and high-quality photography style. Do not change the person's identity.",
            $tool->prompt_template
        );

        $this->assertDatabaseCount('sub_tools', 5);
        $this->assertDatabaseCount('sub_tool_tranlations', 25);

        foreach ([
            'ar' => 'مقابلة نفسك في الماضي',
            'en' => 'Meet Your Past Self',
            'fr' => 'Rencontrez votre vous du passé',
            'es' => 'Conoce a tu yo del pasado',
            'de' => 'Triff dein vergangenes Ich',
        ] as $locale => $name) {
            $this->assertDatabaseHas('sub_tool_tranlations', [
                'sub_tool_id' => 33,
                'locale' => $locale,
                'name' => $name,
            ]);
        }
    }
}
