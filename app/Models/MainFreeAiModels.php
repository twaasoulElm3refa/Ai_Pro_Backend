<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class MainFreeAiModels extends Model
{
    use SoftDeletes;

    protected $table = 'main_free_ai_models';

    protected $fillable = [
        'name',
        'meta_name',
        'description',
        'meta_description',
        'image',
        'slug',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function translations(): HasMany
    {
        return $this->hasMany(MainFreeAiModelsTranslations::class, 'main_free_ai_models_id');
    }

    public function translation(): HasOne
    {
        return $this->hasOne(MainFreeAiModelsTranslations::class, 'main_free_ai_models_id')
            ->where('locale', app()->getLocale());
    }

    public function model_conversations()
    {
        return $this->hasMany(ModelsConverstaions::class,'model_id');
    }
}
