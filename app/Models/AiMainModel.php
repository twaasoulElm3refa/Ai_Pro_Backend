<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiMainModel extends Model
{
    protected $table = 'ai_main_models';

    protected $guarded = [];

    public function translations()
    {
        return $this->hasMany(AiMainModelTranslations::class, 'tool_id');
    }

    public function translation()
    {
        return $this->hasOne(AiMainModelTranslations::class, 'tool_id')
            ->where('locale', app()->getLocale());
    }
}
