<?php

namespace App\Http\Controllers\api\admin\cost;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\AdminCostIndexRequest;
use App\Repository\cost\CostInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class AdminCostController extends Controller
{
    use ApiResponse;

    public function __construct(private CostInterface $costRepository) {}

    public function index(AdminCostIndexRequest $request): JsonResponse
    {
        try {
            $result = $this->costRepository->paginate($request->validated());

            return $this->success($result, 'All Costs Fetched Successfully');
        } catch (\Throwable $th) {
            Log::error('Admin cost index failed', ['error' => $th->getMessage()]);

            return $this->error('Something went wrong.');
        }
    }

    public function today(AdminCostIndexRequest $request): JsonResponse
    {
        try {
            $result = $this->costRepository->today($request->validated());

            return $this->success($result, 'All today Costs Fetched Successfully');
        } catch (\Throwable $th) {
            Log::error('Admin cost today failed', ['error' => $th->getMessage()]);

            return $this->error('Something went wrong.');
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $cost = $this->costRepository->find($id);

            if (! $cost) {
                return $this->notFound('Cost not found.');
            }

            return $this->success($cost, 'Cost Fetched Successfully');
        } catch (\Throwable $th) {
            Log::error('Admin cost show failed', ['id' => $id, 'error' => $th->getMessage()]);

            return $this->error('Something went wrong.');
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $deleted = $this->costRepository->destroy($id);

            if (! $deleted) {
                return $this->notFound('Cost not found.');
            }

            return $this->success(null, 'Cost Deleted Successfully');
        } catch (\Throwable $th) {
            Log::error('Admin cost destroy failed', ['id' => $id, 'error' => $th->getMessage()]);

            return $this->error('Something went wrong.');
        }
    }
}
