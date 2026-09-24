<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

class HomeTrendToolsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $mainTranslation = $this->relationLoaded('translation')
            ? $this->translation
            : null;
        $previewExpiresAt = now()->startOfHour()->addHours(6);

        return [
            'main_tool' => [
                'id' => (int) $this->id,
                'name' => $mainTranslation?->name ?: $this->name,
                'slug' => $this->slug,
            ],
            'tools' => $this->subTools->map(function ($tool) use ($previewExpiresAt): array {
                $translation = $tool->relationLoaded('translation')
                    ? $tool->translation
                    : null;
                $latestImage = $tool->relationLoaded('latestGeneratedImage')
                    ? $tool->latestGeneratedImage
                    : null;

                return [
                    'id' => (int) $tool->id,
                    'name' => $translation?->name ?: $tool->name,
                    'slug' => $tool->slug,
                    'image' => $latestImage ? [
                        'id' => $latestImage->public_id,
                        'preview_url' => URL::temporarySignedRoute(
                            'generated-images.home-preview',
                            $previewExpiresAt,
                            ['image' => $latestImage],
                            absolute: false
                        ),
                        'content_type' => $latestImage->content_type,
                    ] : null,
                    'endpoint' => $tool->endpoint,
                ];
            })->values()->all(),
        ];
    }
}
