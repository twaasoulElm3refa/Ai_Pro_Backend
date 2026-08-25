<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_trends', function (Blueprint $table) {
            $table->id();

            $table->string('slug', 100)->unique();
            $table->string('name_ar', 255);
            $table->string('name_en', 255)->nullable();

            $table->text('description')->nullable();

            $table->string('thumbnail_url', 1000)->nullable();
            $table->string('preview_url', 1000)->nullable();

            $table->text('prompt_template');
            $table->text('negative_prompt')->nullable();

            $table->json('input_schema')->nullable();
            $table->json('output_schema')->nullable();
            $table->json('allowed_model_ids')->nullable();

            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);

            $table->timestamp('created_at')
                ->nullable()
                ->useCurrent();

            $table->timestamp('updated_at')
                ->nullable()
                ->useCurrent()
                ->useCurrentOnUpdate();

            $table->softDeletes();

            $table->index(
                ['is_active', 'sort_order'],
                'idx_ai_trends_active_sort'
            );
        });


        $trends = [

            /*
             * =====================================================
             * CUP LIFT
             * =====================================================
             */
            [
                'slug' => 'cup-lift',

                'name_ar' => 'لحظة رفع الكأس',
                'name_en' => 'Cup Lift Moment',

                'description' =>
                    'تحويل صورة المستخدم إلى لحظة احتفالية واقعية أثناء رفع كأس بطولة.',

                'prompt_template' =>
                    'Create a premium photorealistic championship celebration using the uploaded person as the identity reference. Preserve the exact facial identity, skin tone, age, hairstyle, and recognizable features. Show the person lifting a prestigious trophy above their head inside a packed stadium, teammates celebrating around them, gold confetti, powerful stadium lights, emotional victorious expression, authentic sports photography, dynamic low camera angle, realistic hands and trophy grip, cinematic depth of field. Do not copy a real team logo unless supplied by the user. User customization: {user_request}',

                'negative_prompt' =>
                    'different identity, distorted face, malformed hands, extra fingers, duplicate person, floating trophy, unreadable logo, cartoon, low resolution',

                'input_schema' => json_encode([
                    'file' => [
                        'required' => true,
                        'accept' => [
                            'image/png',
                            'image/jpeg',
                            'image/webp',
                        ],
                    ],

                    'reference_file' => [
                        'required' => false,
                    ],
                ], JSON_UNESCAPED_UNICODE),

                'output_schema' => json_encode([
                    'type' => 'image',
                    'recommended_aspect_ratio' => '4:5',
                ], JSON_UNESCAPED_UNICODE),

                'allowed_model_ids' => null,

                'is_active' => true,
                'sort_order' => 10,
            ],


            /*
             * =====================================================
             * LOCKER ROOM
             * =====================================================
             */
            [
                'slug' => 'locker-room',

                'name_ar' => 'غرفة الملابس',
                'name_en' => 'Locker Room',

                'description' =>
                    'وضع المستخدم داخل غرفة ملابس فريق في مشهد سينمائي واقعي.',

                'prompt_template' =>
                    'Create a cinematic photorealistic locker-room scene using the uploaded person as the identity reference. Preserve exact identity and natural body proportions. Place the person seated confidently in a premium professional sports locker room before or after a major match, jerseys and equipment arranged naturally, dramatic overhead lighting, subtle atmosphere, documentary sports photography, realistic fabric, skin, hands, and reflections. Avoid unauthorized real club logos unless explicitly supplied. User customization: {user_request}',

                'negative_prompt' =>
                    'different identity, distorted anatomy, duplicate limbs, fake text, random logos, plastic skin, cartoon, blur',

                'input_schema' => json_encode([
                    'file' => [
                        'required' => true,
                        'accept' => [
                            'image/png',
                            'image/jpeg',
                            'image/webp',
                        ],
                    ],

                    'reference_file' => [
                        'required' => false,
                    ],
                ], JSON_UNESCAPED_UNICODE),

                'output_schema' => json_encode([
                    'type' => 'image',
                    'recommended_aspect_ratio' => '4:5',
                ], JSON_UNESCAPED_UNICODE),

                'allowed_model_ids' => null,

                'is_active' => true,
                'sort_order' => 20,
            ],


            /*
             * =====================================================
             * PLAYERS TUNNEL
             * =====================================================
             */
            [
                'slug' => 'players-tunnel',

                'name_ar' => 'ممر اللاعبين',
                'name_en' => 'Players Tunnel',

                'description' =>
                    'مشهد دخول اللاعب من ممر اللاعبين إلى الملعب.',

                'prompt_template' =>
                    'Create a dramatic photorealistic players-tunnel entrance using the uploaded person as the identity reference. Preserve exact facial identity and appearance. Show the person walking through a professional stadium tunnel toward the bright pitch, focused pre-match expression, cinematic backlight from the stadium, subtle haze, staff and players softly out of focus, authentic sports editorial photography, realistic motion and anatomy, strong leading lines. Avoid real team branding unless supplied. User customization: {user_request}',

                'negative_prompt' =>
                    'different identity, deformed hands, extra limbs, duplicated people, fake brand marks, cartoon, excessive blur',

                'input_schema' => json_encode([
                    'file' => [
                        'required' => true,
                        'accept' => [
                            'image/png',
                            'image/jpeg',
                            'image/webp',
                        ],
                    ],

                    'reference_file' => [
                        'required' => false,
                    ],
                ], JSON_UNESCAPED_UNICODE),

                'output_schema' => json_encode([
                    'type' => 'image',
                    'recommended_aspect_ratio' => '9:16',
                ], JSON_UNESCAPED_UNICODE),

                'allowed_model_ids' => null,

                'is_active' => true,
                'sort_order' => 30,
            ],


            /*
             * =====================================================
             * PAPARAZZI
             * =====================================================
             */
            [
                'slug' => 'paparazzi',

                'name_ar' => 'باباراتزي',
                'name_en' => 'Paparazzi',

                'description' =>
                    'تحويل الصورة إلى لحظة وصول مشاهير محاطة بالمصورين.',

                'prompt_template' =>
                    'Create a high-end photorealistic paparazzi arrival scene using the uploaded person as the identity reference. Preserve exact identity, skin tone, and recognizable facial details. Show the person arriving confidently at an elegant evening event while photographers surround the walkway, realistic camera flashes, premium fashion styling, candid celebrity editorial feeling, nighttime city ambience, cinematic highlights, natural posture and hands, shallow depth of field. No real brand or event logos unless supplied. User customization: {user_request}',

                'negative_prompt' =>
                    'different identity, distorted face, malformed hands, duplicate cameras, fake logos, wax skin, illustration, low detail',

                'input_schema' => json_encode([
                    'file' => [
                        'required' => true,
                        'accept' => [
                            'image/png',
                            'image/jpeg',
                            'image/webp',
                        ],
                    ],

                    'reference_file' => [
                        'required' => false,
                    ],
                ], JSON_UNESCAPED_UNICODE),

                'output_schema' => json_encode([
                    'type' => 'image',
                    'recommended_aspect_ratio' => '4:5',
                ], JSON_UNESCAPED_UNICODE),

                'allowed_model_ids' => null,

                'is_active' => true,
                'sort_order' => 40,
            ],


            /*
             * =====================================================
             * MEET PAST SELF
             * =====================================================
             */
            [
                'slug' => 'meet-past-self',

                'name_ar' => 'مقابلة نفسك في الماضي',
                'name_en' => 'Meet Your Past Self',

                'description' =>
                    'مشهد عاطفي يجمع المستخدم الحالي بنسخته الأصغر سنًا.',

                'prompt_template' =>
                    'Create an emotional photorealistic scene of the current person meeting their younger past self. Use the first uploaded image for the current identity. If a second reference image is provided, use it for the younger identity; otherwise infer a believable younger version while preserving the same core facial identity. Show both versions naturally facing or interacting with each other in one coherent scene, clear age difference, warm cinematic light, realistic anatomy and hands, subtle nostalgic environment, emotionally authentic expressions, premium editorial photography. Do not create unrelated identities. User customization: {user_request}',

                'negative_prompt' =>
                    'unrelated faces, identical ages, duplicate adult, distorted anatomy, extra fingers, uncanny face, cartoon, collage seam, low resolution',

                'input_schema' => json_encode([
                    'file' => [
                        'required' => true,
                        'label' => 'current_photo',
                        'accept' => [
                            'image/png',
                            'image/jpeg',
                            'image/webp',
                        ],
                    ],

                    'reference_file' => [
                        'required' => false,
                        'label' => 'past_photo',
                    ],
                ], JSON_UNESCAPED_UNICODE),

                'output_schema' => json_encode([
                    'type' => 'image',
                    'recommended_aspect_ratio' => '4:5',
                ], JSON_UNESCAPED_UNICODE),

                'allowed_model_ids' => null,

                'is_active' => true,
                'sort_order' => 50,
            ],
        ];


        /*
         * نفس فكرة:
         *
         * ON DUPLICATE KEY UPDATE
         */
        DB::table('ai_trends')->upsert(
            $trends,

            // Unique key
            ['slug'],

            // Columns to update
            [
                'name_ar',
                'name_en',
                'description',
                'prompt_template',
                'negative_prompt',
                'input_schema',
                'output_schema',
                'allowed_model_ids',
                'is_active',
                'sort_order',
            ]
        );
    }


    public function down(): void
    {
        Schema::dropIfExists('ai_trends');
    }
};
