<?php

namespace App\Repository\tools;

use App\Models\MainTools;

class MainToolRepository implements MainToolInterface
{
    public function index()
    {
        $locale = app()->getLocale();
        $fallbackLocale = config('app.fallback_locale', 'en');
        $locales = array_values(array_unique([$locale, $fallbackLocale]));

        $tools = MainTools::with([
            'translations' => fn ($query) => $query
                ->whereIn('locale', $locales)
                ->select('id', 'main_tools_id', 'locale', 'name', 'description'),
        ])
            ->where('is_active', 1)
            ->select(
                'id',
                'is_active',
                'sort_order',
                'slug',
                'name',
                'image',
                'description',
                'created_at'
            )
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return $tools->each(function (MainTools $tool) use ($locale, $fallbackLocale) {
            $translation = $tool->translations->firstWhere('locale', $locale)
                ?? $tool->translations->firstWhere('locale', $fallbackLocale);

            $tool->setRelation('translation', $translation);
            $tool->unsetRelation('translations');
        });
    }

    public function show($id)
    {
        return MainTools::with([
            'translation',
            'translations',
            'subTools.translation',
            'subTools.translations',
        ])->findOrFail($id);
    }

    public function showBySlug($slug)
    {
        return MainTools::with([
            'translation',
            'translations',
            'subTools.translation',
            'subTools.translations',
        ])->where('slug', $slug)->first();
    }

    public function store(array $data)
    {
        return MainTools::create($data);
    }

    public function update(array $data, $id)
    {
        $tool = MainTools::findOrFail($id);
        $tool->update($data);

        return $tool;
    }

    public function destroy($id)
    {
        $tool = MainTools::findOrFail($id);
        $tool->delete();
    }
}
