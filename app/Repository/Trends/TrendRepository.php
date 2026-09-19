<?php

namespace App\Repository\Trends;

use App\Models\SubTools;

class TrendRepository implements TrendInterface
{
    public function index()
    {
        return SubTools::whereIn('id', [41, 42, 43, 44, 45])
            ->with('translation:name,sub_tools_id,id,locale,description')
            ->select(['id', 'name', 'description', 'slug'])
            ->get();
    }
}