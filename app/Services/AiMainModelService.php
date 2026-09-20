<?php

namespace App\Services;

use App\Models\AiMainModel;

class AiMainModelService
{
    public function getCurrent(): ?AiMainModel
    {
        $locale = app()->getLocale();
        $fallbackLocale = config('app.fallback_locale', 'en');
        $locales = array_values(array_unique([$locale, $fallbackLocale]));

        $model = AiMainModel::query()
            ->with([
                'translations' => fn ($query) => $query
                    ->whereIn('locale', $locales)
                    ->select('id', 'tool_id', 'locale', 'name', 'description'),
            ])
            ->select('id', 'slug', 'name', 'description')
            ->orderBy('id')
            ->first();

        if (! $model) {
            return null;
        }

        $translation = $model->translations->firstWhere('locale', $locale)
            ?? $model->translations->firstWhere('locale', $fallbackLocale);

        $model->setRelation('translation', $translation);
        $model->unsetRelation('translations');

        return $model;
    }
}
