<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TrendsChatSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->ensureMainTool();

            $this->ensureSubtool(28, 'cup-lifting-moment', 'Cup Lift Moment', 'لحظة رفع الكأس', 10);
            $this->ensureSubtool(29, 'locker-room', 'Locker Room', 'غرفة الملابس', 20);
            $this->ensureSubtool(30, 'players-tunnel', 'Players Tunnel', 'ممر اللاعبين', 30);

            $this->ensureSubtool(31, 'paparazzi', 'Paparazzi', 'باباراتزي', 40);

            $translations = [
                28 => [
                    'en' => ['Cup Lift', 'Turn your photo into a realistic championship cup-lift celebration.'],
                    'ar' => ['لحظة رفع الكأس', 'حوّل صورتك إلى لحظة احتفالية واقعية أثناء رفع كأس البطولة.'],
                    'fr' => ['Levée de coupe', 'Transformez votre photo en une célébration réaliste avec une coupe.'],
                    'ru' => ['Поднятие кубка', 'Превратите фотографию в реалистичную сцену празднования с кубком.'],
                    'zh' => ['举杯时刻', '将您的照片变成逼真的冠军举杯庆祝场景。'],
                ],
                29 => [
                    'en' => ['Locker Room', 'Place yourself in a realistic cinematic professional locker-room scene.'],
                    'ar' => ['غرفة الملابس', 'ضع صورتك داخل غرفة ملابس فريق محترف في مشهد سينمائي واقعي.'],
                    'fr' => ['Vestiaire', 'Placez-vous dans une scène réaliste et cinématographique de vestiaire professionnel.'],
                    'ru' => ['Раздевалка', 'Поместите себя в реалистичную кинематографичную сцену профессиональной раздевалки.'],
                    'zh' => ['更衣室', '将自己置于逼真的电影级职业球队更衣室场景中。'],
                ],
                30 => [
                    'en' => ['Players Tunnel', 'Place yourself in a cinematic professional players-tunnel scene.'],
                    'ar' => ['ممر اللاعبين', 'ضع صورتك داخل ممر اللاعبين في مشهد رياضي سينمائي واقعي.'],
                    'fr' => ['Tunnel des joueurs', 'Placez-vous dans une scène cinématographique réaliste du tunnel des joueurs.'],
                    'ru' => ['Тоннель игроков', 'Поместите себя в реалистичную кинематографичную сцену в тоннеле игроков.'],
                    'zh' => ['球员通道', '将自己置于逼真的电影级职业球员通道场景中。'],
                ],
            ];

            $translations[31] = [
                'en' => ['Paparazzi', 'Place yourself in a cinematic paparazzi photo scene.'],
                'ar' => ['باباراتزي', 'ضع صورتك في مشهد سينمائي واقعي وسط عدسات الباباراتزي.'],
                'fr' => ['Paparazzi', 'Placez-vous dans une scène photo cinématographique avec des paparazzis.'],
                'ru' => ['Папарацци', 'Поместите себя в реалистичную кинематографическую сцену с папарацци.'],
                'zh' => ['狗仔队', '将自己置于逼真的电影级狗仔队拍摄场景中。'],
            ];

            foreach ($translations as $subtoolId => $locales) {
                foreach ($locales as $locale => [$name, $description]) {
                    DB::table('sub_tool_tranlations')->updateOrInsert(
                        ['sub_tool_id' => $subtoolId, 'locale' => $locale],
                        [
                            'name' => $name,
                            'description' => $description,
                            'updated_at' => now(),
                            'created_at' => now(),
                        ]
                    );
                }
            }
        });

        if (Cache::supportsTags()) {
            Cache::tags(['tools', 'subtools', 'trends'])->flush();
        }
    }

    private function ensureMainTool(): void
    {
        $tool = DB::table('main_tools')->where('id', 7)->first();
        if ($tool && $tool->slug !== 'trends') {
            throw new RuntimeException('Main tool ID 7 is already assigned to a non-Trends tool.');
        }

        $slugOwner = DB::table('main_tools')->where('slug', 'trends')->first();
        if ($slugOwner && (int) $slugOwner->id !== 7) {
            throw new RuntimeException('The Trends slug is assigned to a main tool other than ID 7.');
        }

        DB::table('main_tools')->updateOrInsert(
            ['id' => 7],
            [
                'name' => 'Trends',
                'slug' => 'trends',
                'description' => 'AI-powered trend image experiences.',
                'is_active' => true,
                'sort_order' => 7,
                'deleted_at' => null,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $names = [
            'en' => 'Trends',
            'ar' => 'الترندات',
            'fr' => 'Tendances',
            'ru' => 'Тренды',
            'zh' => '热门趋势',
        ];
        foreach ($names as $locale => $name) {
            DB::table('main_tool_tranlations')->updateOrInsert(
                ['main_tools_id' => 7, 'locale' => $locale],
                [
                    'name' => $name,
                    'description' => 'AI-powered trend image experiences.',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    private function ensureSubtool(
        int $id,
        string $slug,
        string $nameEn,
        string $nameAr,
        int $sortOrder
    ): void {
        $subtool = DB::table('sub_tools')->where('id', $id)->first();
        if ($subtool && ($subtool->slug !== $slug || (int) $subtool->main_tool_id !== 7)) {
            throw new RuntimeException("Subtool ID {$id} is assigned to a different tool.");
        }

        $slugOwner = DB::table('sub_tools')->where('slug', $slug)->first();
        if ($slugOwner && (int) $slugOwner->id !== $id) {
            throw new RuntimeException("The {$slug} slug is assigned to subtool ID {$slugOwner->id}.");
        }

        DB::table('sub_tools')->updateOrInsert(
            ['id' => $id],
            [
                'main_tool_id' => 7,
                'name' => $nameEn,
                'name_en' => $nameEn,
                'name_ar' => $nameAr,
                'slug' => $slug,
                'description' => match ($slug) {
                    'cup-lifting-moment' => 'Create a realistic championship cup-lift celebration.',
                    'locker-room' => 'Create a realistic professional locker-room scene.',
                    'players-tunnel' => 'Create a cinematic professional players-tunnel scene.',
                    'paparazzi' => 'Create a cinematic paparazzi photo scene.',
                    default => 'Create a cinematic trend image.',
                },
                'endpoint' => $slug === 'cup-lifting-moment'
                    ? 'tasks/trends/cup-lift'
                    : "tasks/trends/{$slug}",
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
                'sort_order' => $sortOrder,
                'deleted_at' => null,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }
}
