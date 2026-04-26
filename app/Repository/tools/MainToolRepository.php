<?php

namespace App\Repository\tools;

use App\Models\MainTools;

class MainToolRepository implements MainToolInterface
{
    public function index()
    {
        return MainTools::with('translation:id,main_tools_id,locale,name,description,meta_title,meta_description')->select('id','is_active','slug', 'name', 'image','description','created_at')
            ->paginate(10);
    }

    public function show($id)
    {
        return MainTools::with([
            'translation',
            'subTools.translation',
            'subTools.provider',
        ])->findOrFail($id);
    }

    public function showBySlug($slug)
    {
        return MainTools::with([
            'translation',
            'subTools',
        ])->where('slug', $slug)->first();
    }

    public function store(array $data)
    {
        return MainTools::create($data);
    }

    public function update(array $data, $id)
    {
        $tool = MainTools::findOrFail($id);
        $tool->update($data);
        return $tool;
    }

    public function destroy($id)
    {
        $tool = MainTools::findOrFail($id);
        $tool->delete();
    }
}
