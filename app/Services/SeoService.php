<?php

namespace App\Services;

use Illuminate\Support\Str;

class SeoService
{
    public function generateMeta(string $name, ?string $description = null): array
    {
        return [
            'meta_name' => Str::limit($name, 60),
            'meta_description' => Str::limit(
                "{$name} - " . strip_tags($description ?? ''),
                155
            ),
        ];
    }
}
