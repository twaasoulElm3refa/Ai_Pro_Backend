<?php

namespace App\Repository\Trends;

use App\Models\SubTools;

class TrendRepository implements TrendInterface
{
    public function index()
    {
        return SubTools::whereIn('id', [41, 42])
            ->where('main_tool_id', 7)
            ->where('is_active', true)
            ->with('translation:name,sub_tool_id,id,locale,description')
            ->select(['id', 'name', 'description', 'slug'])
            ->orderBy('sort_order')
            ->get();
    }
}
