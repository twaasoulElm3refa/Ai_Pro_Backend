<?php

namespace App\Repository\freeAiModels;

use App\Models\MainFreeAiModels;

class MainFreeAiModelRepository implements MainFreeAiModelInterface
{
    public function index()
    {
        return MainFreeAiModels::with('translation')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function show($id)
    {
        return MainFreeAiModels::with([
            'translation',
            'translations',
        ])->findOrFail($id);
    }

    public function store(array $data)
    {
        return MainFreeAiModels::create($data);
    }

    public function update(array $data, $id)
    {
        $model = MainFreeAiModels::findOrFail($id);
        $model->update($data);

        return $model->fresh([
            'translation',
            'translations',
        ]);
    }

    public function destroy($id)
    {
        $model = MainFreeAiModels::findOrFail($id);
        $model->delete();
    }
}
