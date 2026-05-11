<?php

namespace App\Repository\cost;

use App\Models\CostLogger;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Override;

class CostRepository implements CostInterface
{
    public function index()
    {
        try {
            return CostLogger::with('user', 'conversation')->paginate(10);
        } catch (\Throwable $th) {
            Log::error($th);
            return [];
        }
    }

    public function today()
    {
        try {
            $startOfDay = now()->startOfDay();
            $endOfDay = now()->endOfDay();
            return CostLogger::with(['user', 'conversation'])
                ->whereBetween('created_at', [$startOfDay, $endOfDay])
                ->latest()
                ->paginate(10);
        } catch (\Throwable $th) {
            Log::error($th);
            return [];
        }
    }

    #[Override]
    public function show($id)
    {
        try {
            return CostLogger::find($id);
        } catch (\Throwable $th) {
            Log::error($th);
            return [];
        }
    }

    #[Override]
    public function destroy($id)
    {
        try {
            return CostLogger::destroy($id);
        } catch (\Throwable $th) {
            Log::error($th);
            return [];
        }
    }
}
