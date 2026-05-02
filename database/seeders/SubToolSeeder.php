<?php

namespace Database\Seeders;

use App\Jobs\TranslateSubToolJob;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SubToolSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $subTools = [
            [
                'name' => 'محرر النصوص',
                'meta_name' => 'محرر النصوص',
                'description' => 'أداة تتيح لك كتابة وتعديل وتنسيق النصوص بسهولة ومرونة داخل بيئة بسيطة وسريعة. يساعدك على إنشاء محتوى منظم وواضح، سواء كنت بتكتب مقالات، ملاحظات، أو نصوص لموقعك.',
                'meta_description' => 'أداة تتيح لك كتابة وتعديل وتنسيق النصوص بسهولة ومرونة داخل بيئة بسيطة وسريعة. يساعدك على إنشاء محتوى منظم وواضح، سواء كنت بتكتب مقالات، ملاحظات، أو نصوص لموقعك.',
                'image' => null,
                'prompt_placeholder' => 'حرر النص التالي',
                'slug' => 'محرر-النصوص',
                'main_tool_id' => 1,
                'is_active' => 1,
                'sort_order' => 1,
            ]
        ];

        foreach ($subTools as $subTool) {
           $subtool= \App\Models\SubTools::create($subTool);
            TranslateSubToolJob::dispatch($subtool->id);
        }
    }
}
