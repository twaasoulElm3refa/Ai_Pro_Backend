<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MainFreeAiModelsTranslations extends Model
{
    protected $table = 'main_free_ai_models_translations';

    protected $fillable = [
        'main_free_ai_models_id',
        'locale',
        'name',
        'description',
        'meta_title',
        'meta_description',
        'seo_keywords',
    ];

    public function mainFreeAiModels(): BelongsTo
    {
        return $this->belongsTo(MainFreeAiModels::class, 'main_free_ai_models_id');
    }
}
