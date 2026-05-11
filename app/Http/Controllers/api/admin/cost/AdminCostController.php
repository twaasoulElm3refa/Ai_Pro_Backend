<?php

namespace App\Http\Controllers\api\admin\cost;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Repository\cost\CostInterface;
use Illuminate\Support\Facades\Log;

class AdminCostController extends Controller
{
    use ApiResponse;

    private $costRepository;
    public function __construct(CostInterface $costRepository)
    {
        $this->costRepository = $costRepository;
    }

    public function index()
    {
        try {
            $all=$this->costRepository->index();
            return $this->success($all,'All Costs Fetched Successfully');
        } catch (\Throwable $th) {
            Log::error($th);
            return $this->error('Something went wrong.');
        }
    }

    public function today()
    {
        try {
            $all=$this->costRepository->today();
            return $this->success($all,'All today Costs Fetched Successfully');
        } catch (\Throwable $th) {
            Log::error($th);
            return $this->error('Something went wrong.');
        }
    }

    public function show($id)
    {
        try {
            $all=$this->costRepository->show($id);
            return $this->success($all,'Cost Fetched Successfully');
        } catch (\Throwable $th) {
            Log::error($th);
            return $this->error('Something went wrong.');
        }
    }

    public function destroy($id)
    {
        try {
            $this->costRepository->destroy($id);
            return $this->success(null,'Cost Deleted Successfully');
        } catch (\Throwable $th) {
            Log::error($th);
            return $this->error('Something went wrong.');
        }
    }
}
