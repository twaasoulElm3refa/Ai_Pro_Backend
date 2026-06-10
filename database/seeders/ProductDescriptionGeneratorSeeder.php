<?php

namespace Database\Seeders;

use App\Models\MainTools;
use App\Models\SubTools;
use App\Models\SubToolTranlation;
use Illuminate\Database\Seeder;

class ProductDescriptionGeneratorSeeder extends Seeder
{
    public function run(): void
    {
        $subTool = SubTools::withTrashed()->find(8);
        $mainToolId = $subTool?->main_tool_id ?? MainTools::query()->value('id');

        if (! $mainToolId) {
            $this->command?->warn('Product description generator was not seeded because no main tool exists.');

            return;
        }

        if (! $subTool) {
            $subTool = new SubTools();
            $subTool->id = 8;
            $subTool->main_tool_id = $mainToolId;
        }

        $subTool->forceFill([
            'name' => 'وصف المنتج الذكي',
            'meta_name' => 'مولد وصف المنتج الذكي',
            'description' => 'أنشئ وصف منتج احترافي ومقنع يناسب جمهورك ومنصتك.',
            'meta_description' => 'مولد ذكي لكتابة أوصاف المنتجات بالعربية واللغات المدعومة.',
            'slug' => 'ai-product-description-generator',
            'prompt_placeholder' => 'اكتب وصف المنتج أو فكرته هنا',
            'is_active' => true,
            'sort_order' => 8,
            'endpoint' => 'tasks/product-description-generator/chat',
            'deleted_at' => null,
        ])->save();

        $translations = [
            'ar' => [
                'name' => 'وصف المنتج الذكي',
                'prompt_placeholder' => 'اكتب وصف المنتج أو فكرته هنا',
                'description' => 'أنشئ وصف منتج احترافي ومقنع يناسب جمهورك ومنصتك.',
            ],
            'en' => [
                'name' => 'Product Description Generator',
                'prompt_placeholder' => 'Write the product description or idea here',
                'description' => 'Create polished product descriptions tailored to your audience and platform.',
            ],
            'fr' => [
                'name' => 'Generateur de descriptions de produits',
                'prompt_placeholder' => 'Decrivez le produit ou votre idee ici',
                'description' => 'Creez des descriptions de produits adaptees a votre public et a votre plateforme.',
            ],
            'zh' => [
                'name' => 'Product Description Generator',
                'prompt_placeholder' => 'Write the product description or idea here',
                'description' => 'Create polished product descriptions tailored to your audience and platform.',
            ],
            'ru' => [
                'name' => 'Product Description Generator',
                'prompt_placeholder' => 'Write the product description or idea here',
                'description' => 'Create polished product descriptions tailored to your audience and platform.',
            ],
        ];

        foreach ($translations as $locale => $translation) {
            SubToolTranlation::query()->updateOrCreate(
                [
                    'sub_tool_id' => $subTool->id,
                    'locale' => $locale,
                ],
                [
                    ...$translation,
                    'meta_name' => $translation['name'],
                    'meta_description' => $translation['description'],
                ]
            );
        }
    }
}
