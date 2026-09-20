<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AiMainModelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $translation = $this->relationLoaded('translation')
            ? $this->translation
            : null;

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $translation?->name ?: $this->name,
            'description' => $translation?->description ?: $this->description,
            'translation' => $translation ? [
                'locale' => $translation->locale,
                'name' => $translation->name,
                'description' => $translation->description,
            ] : null,
        ];
    }
}
