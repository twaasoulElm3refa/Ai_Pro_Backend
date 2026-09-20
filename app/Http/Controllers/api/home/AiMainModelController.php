<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\AiMainModelResource;
use App\Services\AiMainModelService;
use Illuminate\Http\JsonResponse;

class AiMainModelController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly AiMainModelService $aiMainModel) {}

    public function show(): JsonResponse
    {
        $model = $this->aiMainModel->getCurrent();

        if (! $model) {
            return $this->notFound('AI main model not found.');
        }

        return $this->success(
            new AiMainModelResource($model),
            'AI main model fetched successfully.'
        );
    }
}
