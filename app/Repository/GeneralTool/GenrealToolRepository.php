<?php

namespace App\Repository\GeneralTool;

use App\Models\SubTools;

class GenrealToolRepository implements GenrealToolInterface
{
    public function getAll()
    {
        return SubTools::where('main_tool_id', 8)->get();
    }

    public function show($id)
    {
        return SubTools::findOrFail($id);
    }
}
