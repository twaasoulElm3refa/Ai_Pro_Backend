<?php

namespace App\Repository\tools;

use App\Models\SubTools;

class SubToolRepository implements SubToolInterface
{
    public function index($id)
    {
        $tools = SubTools::where('main_tool_id',$id)
            ->paginate(10);
        return $tools;
    }

    public function show($id)
    {
        $tool = SubTools::findOrFail($id);
        return $tool;
    }

    public function showBySlug($slug)
    {
        $tool = SubTools::with('translation')->where('slug', $slug)->first();
        return $tool;
    }

    public function store(array $data)
    {
        $tool = SubTools::create($data);
        return $tool;
    }

    public function update(array $data,$id)
    {
        $tool = SubTools::findOrFail($id);
        $tool->update($data);
        return $tool;
    }

    public function destroy($id)
    {
        $tool = SubTools::findOrFail($id);
        $tool->delete();
    }
}
