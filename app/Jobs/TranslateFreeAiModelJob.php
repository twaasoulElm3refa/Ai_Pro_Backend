<?php

namespace App\Jobs;

use App\Models\MainFreeAiModels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Stichoza\GoogleTranslate\GoogleTranslate;

class TranslateFreeAiModelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $modelId) {}

    public function handle(): void
    {
        $model = MainFreeAiModels::find($this->modelId);
        if (! $model) {
            return;
        }

        $locales = ['ar', 'en', 'fr', 'ru', 'zh'];
        foreach ($locales as $locale) {
            try {
                $tr = new GoogleTranslate($locale);
                $tr->setSource('ar');
                $name = $model->name ? $tr->translate($model->name) : null;
                $desc = $model->description ? $tr->translate($model->description) : null;
                $metaName = $model->meta_name ? $tr->translate($model->meta_name) : null;
                $metaDesc = $model->meta_description ? $tr->translate($model->meta_description) : null;
                $model->translations()->updateOrCreate(
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
                Log::error('Free AI Model Translate Error: '.$e->getMessage());
            }
        }
    }
}
