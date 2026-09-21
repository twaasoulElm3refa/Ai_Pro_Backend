<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HomeTrendToolsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $mainTranslation = $this->relationLoaded('translation')
            ? $this->translation
            : null;

        return [
            'main_tool' => [
                'id' => (int) $this->id,
                'name' => $mainTranslation?->name ?: $this->name,
                'slug' => $this->slug,
            ],
            'tools' => $this->subTools->map(function ($tool): array {
                $translation = $tool->relationLoaded('translation')
                    ? $tool->translation
                    : null;

                return [
                    'id' => (int) $tool->id,
                    'name' => $translation?->name ?: $tool->name,
                    'slug' => $tool->slug,
                    'image' => $tool->image,
                    'endpoint' => $tool->endpoint,
                ];
            })->values()->all(),
        ];
    }
}
