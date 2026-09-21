<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SUB_TOOL_ID = 33;

    private const PROMPT = "Create a realistic cinematic image showing the user's current self meeting their younger past self. Preserve the exact identity, face features, hairstyle, and natural appearance of the uploaded person. Show both versions together in one realistic scene with emotional storytelling, cinematic lighting, realistic skin texture, and high-quality photography style. Do not change the person's identity.";

    public function up(): void
    {
        DB::transaction(function (): void {
            $subTool = DB::table('sub_tools')
                ->where('id', self::SUB_TOOL_ID)
                ->first();

            if (! $subTool) {
                throw new \RuntimeException(
                    'Meet Your Past Self subtool (ID 33) does not exist in the sub_tools table.'
                );
            }

            $config = array_replace($this->decodeArray($subTool->config ?? null), [
                'provider' => 'runware',
                'model' => 'bfl:5@1',
                'operation' => 'image_edit',
                'selected_model_id' => 46,
                'category' => 'AI Image Tools',
                'task' => 'تحرير الصور وإنشاء صور بالذكاء الاصطناعي.',
            ]);

            $allowedModelIds = array_values(array_unique(array_map(
                'intval',
                [...$this->decodeArray($subTool->allowed_model_ids ?? null), 46]
            )));

            $updates = [
                'prompt_template' => self::PROMPT,
                'endpoint' => 'tasks/trends/meet-past-self',
                'config' => json_encode($config, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'allowed_model_ids' => json_encode($allowedModelIds, JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ];

            if ($this->decodeArray($subTool->input_schema ?? null) === []) {
                $updates['input_schema'] = json_encode([
                    'file' => [
                        'required' => true,
                        'accept' => ['image/png', 'image/jpeg', 'image/webp'],
                    ],
                ], JSON_THROW_ON_ERROR);
            }

            if ($this->decodeArray($subTool->output_schema ?? null) === []) {
                $updates['output_schema'] = json_encode([
                    'type' => 'image',
                    'recommended_aspect_ratio' => '4:5',
                ], JSON_THROW_ON_ERROR);
            }

            DB::table('sub_tools')
                ->where('id', self::SUB_TOOL_ID)
                ->update($updates);

            $translations = [
                'ar' => ['مقابلة نفسك في الماضي', 'أنشئ صورة تجمع بينك الآن ونسختك في الماضي باستخدام الذكاء الاصطناعي بأسلوب واقعي وسينمائي.'],
                'en' => ['Meet Your Past Self', 'Create a realistic cinematic image of your present self meeting your past self using AI.'],
                'fr' => ['Rencontrez votre vous du passé', 'Créez une image cinématographique réaliste de votre moi actuel rencontrant votre moi passé grâce à l’IA.'],
                'es' => ['Conoce a tu yo del pasado', 'Crea con IA una imagen cinematográfica realista de tu yo actual encontrándose con tu yo del pasado.'],
                'de' => ['Triff dein vergangenes Ich', 'Erstelle mit KI ein realistisches, filmisches Bild, auf dem dein heutiges Ich deinem früheren Ich begegnet.'],
            ];

            $timestamp = now();

            foreach ($translations as $locale => [$name, $description]) {
                DB::table('sub_tool_tranlations')->insertOrIgnore([
                    'sub_tool_id' => self::SUB_TOOL_ID,
                    'locale' => $locale,
                    'name' => $name,
                    'description' => $description,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);
            }
        });

        $this->flushCaches();
    }

    public function down(): void
    {
        // Intentionally non-destructive: tool 33 and its translations predate this migration.
    }

    private function decodeArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function flushCaches(): void
    {
        try {
            if (Cache::supportsTags()) {
                Cache::tags(['tools', 'subtools', 'trends'])->flush();
            }
        } catch (\Throwable) {
            // Cache availability must not block configuration of the existing tool.
        }
    }
};
