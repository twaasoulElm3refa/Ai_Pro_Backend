<?php

namespace App\Services;

use App\Models\MainTools;

class TrendMainToolService
{
    private const MAIN_TOOL_ID = 7;

    public function get(): ?MainTools
    {
        $locale = app()->getLocale();
        $fallbackLocale = config('app.fallback_locale', 'en');
        $locales = array_values(array_unique([$locale, $fallbackLocale]));

        $tool = MainTools::query()
            ->with([
                'translations' => fn ($query) => $query
                    ->whereIn('locale', $locales)
                    ->select('id', 'main_tools_id', 'locale', 'name', 'description'),
            ])
            ->select('id', 'slug', 'name', 'description', 'image')
            ->find(self::MAIN_TOOL_ID);

        if (! $tool) {
            return null;
        }

        $translation = $tool->translations->firstWhere('locale', $locale)
            ?? $tool->translations->firstWhere('locale', $fallbackLocale);

        $tool->setRelation('translation', $translation);
        $tool->unsetRelation('translations');

        return $tool;
    }
}
