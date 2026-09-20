<?php

namespace App\Repository\AiModels;

use App\Models\MainFreeAiModels;

class AiModelsRepository implements AiModelsInterface
{
    public function AiModels()
    {
        $locale = app()->getLocale();
        $fallbackLocale = config('app.fallback_locale', 'en');
        $locales = array_values(array_unique([$locale, $fallbackLocale]));

        $tools = MainFreeAiModels::with([
            'translations' => fn ($query) => $query
                ->whereIn('locale', $locales)
                ->select('id', 'main_free_ai_models_id', 'locale', 'name', 'description'),
        ])
            ->where('is_active', true)
            ->select(['id', 'slug', 'name', 'description', 'image', 'sort_order'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return $tools->each(function (MainFreeAiModels $tool) use ($locale, $fallbackLocale) {
            $translation = $tool->translations->firstWhere('locale', $locale)
                ?? $tool->translations->firstWhere('locale', $fallbackLocale);

            $tool->setRelation('translation', $translation);
            $tool->unsetRelation('translations');
        });
    }
}
