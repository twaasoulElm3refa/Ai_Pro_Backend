<?php

namespace App\Jobs;

use App\Models\MainTools;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Stichoza\GoogleTranslate\GoogleTranslate;

class TranslateToolJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $toolId) {}

    public function handle(): void
    {
        $tool = MainTools::find($this->toolId);

        if (! $tool) {
            return;
        }

        $locales = ['ar', 'en', 'fr', 'ru', 'zh'];

        foreach ($locales as $locale) {

            try {
                $tr = new GoogleTranslate($locale);
                $tr->setSource('ar');

                $name = $tool->name ? $tr->translate($tool->name) : null;
                $desc = $tool->description ? $tr->translate($tool->description) : null;
                $metaName = $tool->meta_name ? $tr->translate($tool->meta_name) : null;
                $metaDesc = $tool->meta_description ? $tr->translate($tool->meta_description) : null;

                $tool->translations()->updateOrCreate(
                    [
                        'locale' => $locale,
                    ],
                    [
                        'name' => $name,
                        'description' => $desc,
                        'meta_title' => $metaName,
                        'meta_description' => $metaDesc,
                    ]
                );

            } catch (\Throwable $e) {
                Log::error('Tool Translate Error: '.$e->getMessage());
            }
        }
    }
}
