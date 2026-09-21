<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SUB_TOOL_ID = 33;

    private const SLUG = 'meet-past-self';

    private const PROMPT = "Create a realistic cinematic image showing the user's current self meeting their younger past self. Preserve the exact identity, face features, hairstyle, and natural appearance of the uploaded person. Show both versions together in one realistic scene with emotional storytelling, cinematic lighting, realistic skin texture, and high-quality photography style. Do not change the person's identity.";

    public function up(): void
    {
        if (! DB::table('main_tools')->where('id', 7)->exists()) {
            return;
        }

        DB::transaction(function (): void {
            $idOwner = DB::table('sub_tools')->where('id', self::SUB_TOOL_ID)->first();
            if ($idOwner && ($idOwner->slug !== self::SLUG || (int) $idOwner->main_tool_id !== 7)) {
                throw new \RuntimeException('Subtool ID 33 is already assigned to a different tool.');
            }

            $slugOwner = DB::table('sub_tools')->where('slug', self::SLUG)->first();
            if ($slugOwner && (int) $slugOwner->id !== self::SUB_TOOL_ID) {
                throw new \RuntimeException('The meet-past-self slug is already assigned to a different subtool.');
            }

            DB::table('sub_tools')->updateOrInsert(
                ['id' => self::SUB_TOOL_ID],
                [
                    'main_tool_id' => 7,
                    'name' => 'Meet Your Past Self',
                    'name_en' => 'Meet Your Past Self',
                    'name_ar' => 'مقابلة نفسك في الماضي',
                    'slug' => self::SLUG,
                    'description' => 'Create a realistic cinematic image of your present self meeting your past self using AI.',
                    'prompt_template' => self::PROMPT,
                    'endpoint' => 'tasks/trends/meet-past-self',
                    'config' => json_encode([
                        'provider' => 'runware',
                        'model' => 'bfl:5@1',
                        'operation' => 'image_edit',
                        'selected_model_id' => 46,
                        'category' => 'AI Image Tools',
                        'task' => 'تحرير الصور وإنشاء صور بالذكاء الاصطناعي.',
                    ], JSON_THROW_ON_ERROR),
                    'allowed_model_ids' => json_encode([46], JSON_THROW_ON_ERROR),
                    'input_schema' => json_encode([
                        'file' => [
                            'required' => true,
                            'accept' => ['image/png', 'image/jpeg', 'image/webp'],
                        ],
                    ], JSON_THROW_ON_ERROR),
                    'output_schema' => json_encode([
                        'type' => 'image',
                        'recommended_aspect_ratio' => '4:5',
                    ], JSON_THROW_ON_ERROR),
                    'is_active' => true,
                    'sort_order' => 50,
                    'deleted_at' => null,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            $translations = [
                'ar' => ['مقابلة نفسك في الماضي', 'أنشئ صورة تجمع بينك الآن ونسختك في الماضي باستخدام الذكاء الاصطناعي بأسلوب واقعي وسينمائي.'],
                'en' => ['Meet Your Past Self', 'Create a realistic cinematic image of your present self meeting your past self using AI.'],
                'fr' => ['Rencontrez votre vous du passé', 'Créez une image cinématographique réaliste de votre moi actuel rencontrant votre moi passé grâce à l’IA.'],
                'es' => ['Conoce a tu yo del pasado', 'Crea con IA una imagen cinematográfica realista de tu yo actual encontrándose con tu yo del pasado.'],
                'de' => ['Triff dein vergangenes Ich', 'Erstelle mit KI ein realistisches, filmisches Bild, auf dem dein heutiges Ich deinem früheren Ich begegnet.'],
            ];

            foreach ($translations as $locale => [$name, $description]) {
                DB::table('sub_tool_tranlations')->updateOrInsert(
                    ['sub_tool_id' => self::SUB_TOOL_ID, 'locale' => $locale],
                    [
                        'name' => $name,
                        'description' => $description,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        });

        $this->flushCaches();
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $isExpectedTool = DB::table('sub_tools')
                ->where('id', self::SUB_TOOL_ID)
                ->where('slug', self::SLUG)
                ->exists();

            if (! $isExpectedTool) {
                return;
            }

            DB::table('sub_tool_tranlations')->where('sub_tool_id', self::SUB_TOOL_ID)->delete();
            DB::table('sub_tools')->where('id', self::SUB_TOOL_ID)->delete();
        });

        $this->flushCaches();
    }

    private function flushCaches(): void
    {
        try {
            if (Cache::supportsTags()) {
                Cache::tags(['tools', 'subtools', 'trends'])->flush();
            }
        } catch (\Throwable) {
            // Cache availability must not roll back or invalidate tool registration.
        }
    }
};
