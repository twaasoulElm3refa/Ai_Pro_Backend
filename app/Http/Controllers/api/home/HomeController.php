<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Repository\tools\MainToolInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class HomeController extends Controller
{
    use ApiResponse;

    private $toolRepository;

    public function __construct(MainToolInterface $toolRepository)
    {
        $this->toolRepository = $toolRepository;
    }

    public function index()
    {
        try {
            $locale = app()->getLocale();

            $tools = Cache::tags(['tools'])->remember(
                "tools:index:{$locale}",
                now()->addHour(),
                fn () => $this->toolRepository->index()
            );

            return $this->success($tools, 'Tools fetched successfully.');
        } catch (\Throwable $th) {
            Log::error('Tool Index Error', [
                'error' => $th->getMessage(),
            ]);

            return $this->error('Something went wrong.');
        }
    }

    public function show($id)
    {
        try {
            $locale = app()->getLocale();

            $tool = Cache::tags(['tools'])->remember(
                "tools:show:{$id}:{$locale}",
                now()->addHour(),
                fn () => $this->toolRepository->show($id)
            );

            return $this->success($tool, 'Tool fetched successfully.');
        } catch (\Throwable $th) {
            Log::error('Tool Show Error', [
                'error' => $th->getMessage(),
            ]);

            return $this->error('Something went wrong.');
        }
    }
}
