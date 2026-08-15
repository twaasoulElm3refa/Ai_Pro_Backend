<?php

namespace App\Services\FreeAiModels;

use App\Jobs\TranslateFreeAiModelJob;
use App\Repository\freeAiModels\MainFreeAiModelInterface;
use App\Services\SeoService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class MainFreeAiModelService
{
    public function __construct(
        private MainFreeAiModelInterface $repository,
        private SeoService $seoService,
    ) {}

    public function index()
    {
        return $this->repository->index();
    }

    public function show($id)
    {
        return $this->repository->show($id);
    }

    public function store(array $data)
    {
        if (($data['image'] ?? null) instanceof UploadedFile) {
            $data['image'] = $data['image']->store('free-ai-models', 'public');
        }

        $data['slug'] = Str::slug($data['name']).'-'.Str::random(6);
        $data = array_merge(
            $data,
            $this->seoService->generateMeta(
                $data['name'] ?? '',
                $data['description'] ?? ''
            )
        );

        $model = $this->repository->store($data);
        TranslateFreeAiModelJob::dispatch($model->id);

        return $model;
    }

    public function update(array $data, $id)
    {
        if (($data['image'] ?? null) instanceof UploadedFile) {
            $data['image'] = $data['image']->store('free-ai-models', 'public');
        }

        return $this->repository->update($data, $id);
    }

    public function destroy($id): void
    {
        $this->repository->destroy($id);
    }
}
