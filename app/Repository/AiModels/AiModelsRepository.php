<?php

namespace App\Repository\AiModels;

use App\Models\MainFreeAiModels;

class AiModelsRepository implements AiModelsInterface
{
    public function AiModels()
    {
        return MainFreeAiModels::with('translation:name,main_free_ai_models_id,id,locale,description')->take(5)->select(['id', 'slug'])->get();
    }
}
