<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\HomeTrendToolsResource;
use App\Http\Resources\TrendMainToolResource;
use App\Services\TrendMainToolService;
use Illuminate\Http\JsonResponse;

class TrendMainToolController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly TrendMainToolService $trendMainTool) {}

    public function show(): JsonResponse
    {
        $tool = $this->trendMainTool->get();

        if (! $tool) {
            return $this->notFound('Trend main tool not found.');
        }

        return $this->success(
            new TrendMainToolResource($tool),
            'Trend main tool fetched successfully.'
        );
    }

    public function home(): JsonResponse
    {
        $tool = $this->trendMainTool->getForHome();

        if (! $tool) {
            return $this->notFound('Trend main tool not found.');
        }

        return $this->success(
            new HomeTrendToolsResource($tool),
            'Home trend tools fetched successfully.'
        );
    }
}
