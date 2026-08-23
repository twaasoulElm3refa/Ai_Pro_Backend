<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('sub_tools')->where('id', 23)->exists();

        if (!$exists) {
            $mainToolId = DB::table('main_tools')->orderBy('id')->value('id');

            if (! $mainToolId) {
                return;
            }

            DB::table('sub_tools')->insert([
                'id' => 23,
                'name' => 'Image Upscaler',
                'slug' => 'ai_image_upscaler',
                'description' => 'Upscale images using AI.',
                'main_tool_id' => $mainToolId,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('sub_tool_tranlations')->insert([
                [
                    'sub_tool_id' => 23,
                    'locale' => 'en',
                    'name' => 'Image Upscaler',
                    'description' => 'Upscale images using AI.',
                ],
                [
                    'sub_tool_id' => 23,
                    'locale' => 'ar',
                    'name' => 'تكبير الصور',
                    'description' => 'قم بتكبير وتوضيح الصور باستخدام الذكاء الاصطناعي.',
                ]
            ]);
        }
    }

    public function down(): void
    {
        DB::table('sub_tool_tranlations')->where('sub_tool_id', 23)->delete();
        DB::table('sub_tools')->where('id', 23)->delete();
    }
};
