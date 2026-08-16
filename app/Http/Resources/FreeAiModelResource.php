<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class FreeAiModelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $requestedLocale = app()->getLocale();
        $translations = $this->relationLoaded('translations')
            ? $this->translations
            : collect();
        $translation = $translations->firstWhere('locale', $requestedLocale);
        $englishTranslation = $translations->firstWhere('locale', 'en');

        return [
            'slug' => $this->slug,
            'name' => $translation?->name ?: ($englishTranslation?->name ?: $this->name),
            'description' => $translation?->description
                ?: ($englishTranslation?->description ?: $this->description),
            'image_url' => $this->image ? Storage::disk('public')->url($this->image) : null,
            'meta_title' => $translation?->meta_title
                ?: ($englishTranslation?->meta_title ?: ($this->meta_name ?: $this->name)),
            'meta_description' => $translation?->meta_description
                ?: ($englishTranslation?->meta_description ?: ($this->meta_description ?: $this->description)),
            'seo_keywords' => $translation?->seo_keywords ?: $englishTranslation?->seo_keywords,
        ];
    }
}
