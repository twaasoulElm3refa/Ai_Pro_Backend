<?php

namespace App\Http\Controllers\api\admin\freeAiModels;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\FreeAiModelRequest;
use App\Http\Requests\FreeAiModelUpdateRequest;
use App\Services\FreeAiModels\MainFreeAiModelService;
use Illuminate\Support\Facades\Log;

class MainFreeAiModelController extends Controller
{
    use ApiResponse;

    public function __construct(private MainFreeAiModelService $service) {}

    public function index()
    {
        try {
            $models = $this->service->index();

            return $this->success($models, 'Free AI models fetched successfully.');
        } catch (\Throwable $th) {
            Log::error('Free AI Model Index Error', [
                'message' => $th->getMessage(),
            ]);

            return $this->error('Something went wrong.');
        }
    }

    public function show($id)
    {
        try {
            $model = $this->service->show($id);

            return $this->success($model, 'Free AI model fetched successfully.');
        } catch (\Throwable $th) {
            Log::error('Free AI Model Show Error', [
                'message' => $th->getMessage(),
            ]);

            return $this->error('Something went wrong.');
        }
    }

    public function store(FreeAiModelRequest $request)
    {
        try {
            $model = $this->service->store($request->validated());

            return $this->success($model, 'Free AI model created successfully.');
        } catch (\Throwable $th) {
            Log::error('Free AI Model Store Error', [
                'message' => $th->getMessage(),
            ]);

            return $this->error('Something went wrong.');
        }
    }

    public function update(FreeAiModelUpdateRequest $request, $id)
    {
        try {
            $model = $this->service->update($request->validated(), $id);

            return $this->success($model, 'Free AI model updated successfully.');
        } catch (\Throwable $th) {
            Log::error('Free AI Model Update Error', [
                'message' => $th->getMessage(),
            ]);

            return $this->error('Something went wrong.');
        }
    }

    public function destroy($id)
    {
        try {
            $this->service->destroy($id);

            return $this->success(null, 'Free AI model deleted successfully.');
        } catch (\Throwable $th) {
            Log::error('Free AI Model Destroy Error', [
                'message' => $th->getMessage(),
            ]);

            return $this->error('Something went wrong.');
        }
    }
}
