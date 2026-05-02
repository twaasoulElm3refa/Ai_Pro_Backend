<?php

namespace Database\Seeders;

use App\Jobs\TranslateToolJob;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MainToolSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $mainTools = [
            [
                'name' => 'كاتب النصوص الذكي',
                'meta_name' => 'كاتب النصوص الذكي',
                'description' => 'داة تعتمد على تقنيات الذكاء الاصطناعي لمساعدتك في إنشاء محتوى احترافي بسرعة وسهولة. يقوم بتحليل طلبك وفهم الهدف من النص، ثم يولّد محتوى مناسب سواء كان مقالات، منشورات سوشيال ميديا، أو نصوص تسويقية بجودة عالية',
                'meta_description' => 'داة تعتمد على تقنيات الذكاء الاصطناعي لمساعدتك في إنشاء محتوى احترافي بسرعة وسهولة. يقوم بتحليل طلبك وفهم الهدف من النص، ثم يولّد محتوى مناسب سواء كان مقالات، منشورات سوشيال ميديا، أو نصوص تسويقية بجودة عالية',
                'image' => null,
                'slug' => 'كاتب-النصوص-الذكي',
                'is_active' => 1,
                'sort_order' => 1,
            ]
        ];

        foreach ($mainTools as $mainTool) {
           $tool= \App\Models\MainTools::create($mainTool);
           TranslateToolJob::dispatch($tool->id);
        }
    }
}
