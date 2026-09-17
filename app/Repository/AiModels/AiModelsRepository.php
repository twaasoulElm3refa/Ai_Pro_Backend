<?php

namespace App\Repository\AiModels;

use App\Models\MainFreeAiModels;

class AiModelsRepository implements AiModelsInterface
{
    public function AiModels()
    {
        return MainFreeAiModels::with('translation')->take(5)->select(['id', 'slug'])->get();
    }
}
