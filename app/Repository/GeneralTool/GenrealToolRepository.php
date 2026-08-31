<?php

namespace App\Repository\GeneralTool;

use App\Models\SubTools;

class GenrealToolRepository implements GenrealToolInterface
{
    public function getAll()
    {
        return SubTools::where('main_tool_id', 8)
            ->select([
                'id',
                'main_tool_id',
                'name',
                'meta_name',
                'slug',
                'prompt_placeholder',
                'is_active',
                'tier',
                'is_free',
            ])
            ->get();
    }

    public function show($id)
    {
        return SubTools::findOrFail($id);
    }
}
