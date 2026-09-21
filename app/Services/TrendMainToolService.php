<?php

namespace App\Services;

use App\Models\GeneratedImage;
use App\Models\MainTools;

class TrendMainToolService
{
    private const MAIN_TOOL_ID = 7;

    private const HOME_TOOLS_LIMIT = 6;

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

    public function getForHome(?int $userId = null): ?MainTools
    {
        $tool = MainTools::query()
            ->with('translation')
            ->select('id', 'slug', 'name', 'description', 'image')
            ->find(self::MAIN_TOOL_ID);

        if (! $tool) {
            return null;
        }

        $subTools = $tool->subTools()
            ->with('translation')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->limit(self::HOME_TOOLS_LIMIT)
            ->get([
                'id',
                'main_tool_id',
                'name',
                'slug',
                'image',
                'endpoint',
            ]);

        $latestImages = collect();

        if ($userId !== null && $subTools->isNotEmpty()) {
            $latestImages = GeneratedImage::query()
                ->where('user_id', $userId)
                ->whereIn('sub_tool_id', $subTools->pluck('id'))
                ->whereHas('message', fn ($query) => $query
                    ->where('role', 'assistant')
                    ->where('is_error', false))
                ->latest('id')
                ->get()
                ->unique('sub_tool_id')
                ->keyBy('sub_tool_id');
        }

        $subTools->each(function ($subTool) use ($latestImages): void {
            $subTool->setRelation(
                'latestGeneratedImage',
                $latestImages->get($subTool->id)
            );
        });

        $tool->setRelation('subTools', $subTools);

        return $tool;
    }
}
