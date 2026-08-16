<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AiMainModelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Main AI Model
        |--------------------------------------------------------------------------
        */

        DB::table('ai_main_models')->updateOrInsert(
            ['id' => 1],
            [
                'name' => 'أدوات الذكاء الاصطناعي',
                'meta_name' => 'أدوات الذكاء الاصطناعي',
                'meta_description' => 'مجموعة من أدوات الذكاء الاصطناعي المتقدمة لمساعدتك في الكتابة والتحليل والإبداع وتنفيذ المهام المختلفة.',
                'description' => 'اكتشف مجموعة متنوعة من أدوات الذكاء الاصطناعي المصممة لمساعدتك على إنجاز مهامك بسهولة وسرعة.',
                'slug' => 'ai-tools',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );


        /*
        |--------------------------------------------------------------------------
        | Translations
        |--------------------------------------------------------------------------
        */

        $translations = [

            /*
            |--------------------------------------------------------------------------
            | Arabic
            |--------------------------------------------------------------------------
            */

            [
                'locale' => 'ar',
                'name' => 'أدوات الذكاء الاصطناعي',

                'description' =>
                    'اكتشف مجموعة متنوعة من أدوات الذكاء الاصطناعي المصممة لمساعدتك في الكتابة والتحليل والإبداع وتنفيذ المهام المختلفة بسهولة وسرعة.',

                'meta_title' =>
                    'أدوات الذكاء الاصطناعي | AI Pro',

                'meta_description' =>
                    'استخدم مجموعة متطورة من أدوات الذكاء الاصطناعي للكتابة والتحليل والإبداع والمساعدة في تنفيذ العديد من المهام.',

                'seo_keywords' =>
                    'أدوات الذكاء الاصطناعي, الذكاء الاصطناعي, AI, أدوات AI, AI Pro',
            ],


            /*
            |--------------------------------------------------------------------------
            | English
            |--------------------------------------------------------------------------
            */

            [
                'locale' => 'en',
                'name' => 'AI Tools',

                'description' =>
                    'Discover a wide range of artificial intelligence tools designed to help you write, analyze, create, and complete different tasks quickly and easily.',

                'meta_title' =>
                    'AI Tools | AI Pro',

                'meta_description' =>
                    'Explore advanced AI tools for writing, analysis, creativity, and completing a wide variety of tasks.',

                'seo_keywords' =>
                    'AI tools, artificial intelligence, AI, AI Pro, artificial intelligence tools',
            ],


            /*
            |--------------------------------------------------------------------------
            | French
            |--------------------------------------------------------------------------
            */

            [
                'locale' => 'fr',
                'name' => 'Outils d’intelligence artificielle',

                'description' =>
                    'Découvrez une variété d’outils d’intelligence artificielle conçus pour vous aider à écrire, analyser, créer et accomplir différentes tâches rapidement et facilement.',

                'meta_title' =>
                    'Outils d’intelligence artificielle | AI Pro',

                'meta_description' =>
                    'Découvrez des outils avancés d’intelligence artificielle pour la rédaction, l’analyse, la créativité et de nombreuses autres tâches.',

                'seo_keywords' =>
                    'outils IA, intelligence artificielle, IA, AI Pro, outils intelligence artificielle',
            ],


            /*
            |--------------------------------------------------------------------------
            | Chinese
            |--------------------------------------------------------------------------
            */

            [
                'locale' => 'zh',
                'name' => '人工智能工具',

                'description' =>
                    '探索各种人工智能工具，帮助您快速轻松地完成写作、分析、创作以及其他多种任务。',

                'meta_title' =>
                    '人工智能工具 | AI Pro',

                'meta_description' =>
                    '使用先进的人工智能工具进行写作、分析、创作以及完成各种任务。',

                'seo_keywords' =>
                    '人工智能工具, 人工智能, AI工具, AI, AI Pro',
            ],


            /*
            |--------------------------------------------------------------------------
            | Russian
            |--------------------------------------------------------------------------
            */

            [
                'locale' => 'ru',
                'name' => 'Инструменты искусственного интеллекта',

                'description' =>
                    'Откройте для себя различные инструменты искусственного интеллекта для написания текстов, анализа, творчества и быстрого выполнения различных задач.',

                'meta_title' =>
                    'Инструменты искусственного интеллекта | AI Pro',

                'meta_description' =>
                    'Используйте современные инструменты искусственного интеллекта для написания текстов, анализа, творчества и выполнения различных задач.',

                'seo_keywords' =>
                    'инструменты ИИ, искусственный интеллект, AI, AI Pro, инструменты искусственного интеллекта',
            ],

        ];


        /*
        |--------------------------------------------------------------------------
        | Insert / Update translations
        |--------------------------------------------------------------------------
        */

        foreach ($translations as $translation) {

            DB::table('ai_main_model_translations')->updateOrInsert(
                [
                    'tool_id' => 1,
                    'locale' => $translation['locale'],
                ],
                [
                    'name' => $translation['name'],
                    'description' => $translation['description'],
                    'meta_title' => $translation['meta_title'],
                    'meta_description' => $translation['meta_description'],
                    'seo_keywords' => $translation['seo_keywords'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
