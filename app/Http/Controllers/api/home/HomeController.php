<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Repository\tools\MainToolInterface;
use App\Repository\tools\SubToolInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class HomeController extends Controller
{
    use ApiResponse;

    private $toolRepository;
    private $subToolRepository;

    public function __construct(MainToolInterface $toolRepository ,SubToolInterface $subToolRepository)
    {
        $this->toolRepository = $toolRepository;
        $this->subToolRepository=$subToolRepository;
    }

    private function cacheableSuccessResponse($data, string $message, int $maxAge = 300): JsonResponse
    {
        $response = $this->success($data, $message);
        $etag = '"' . sha1(json_encode($data)) . '"';

        $response->setEtag($etag);
        $response->headers->set('Cache-Control', "public, max-age={$maxAge}, stale-while-revalidate=600");
        $response->headers->set('Vary', 'Accept-Language');

        if ($response->isNotModified(request())) {
            return $response;
        }

        return $response;
    }

    public function index()
    {
        try {
            $locale = app()->getLocale();
            $cacheKey = "tools:index:{$locale}";
            $tools = Cache::tags(['tools'])->remember(
                $cacheKey,
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

    public function show($slug)
    {
        try {
            $locale = app()->getLocale();

            $tool = Cache::tags(['tools'])->remember(
                "tools:show:{$slug}:{$locale}",
                now()->addHour(),
                fn () => $this->toolRepository->showBySlug($slug)
            );
            return $this->success($tool, 'Tool fetched successfully.');
        } catch (\Throwable $th) {
            Log::error('Tool Show Error', [
                'error' => $th->getMessage(),
            ]);

            return $this->error('Something went wrong.');
        }
    }

     public function showChat($slug)
    {
        try {
            $locale = app()->getLocale();

            $tool = Cache::tags(['subtools'])->remember(
                "subtools:show:{$slug}:{$locale}",
                now()->addHour(),
                fn () => $this->subToolRepository->showBySlug($slug)
            );

            return $this->success($tool, 'Tool fetched successfully.');
        } catch (\Throwable $th) {
            Log::error('Tool Show Error', [
                'error' => $th->getMessage(),
            ]);

            return $this->error('Something went wrong.');
        }
    }

    public function randomSubTools()
    {
        try {
            $locale = app()->getLocale();
            $tools = Cache::tags(['subtools'])->remember(
                "subtools:random:{$locale}",
                now()->addHour(),
                fn () => $this->subToolRepository->randomSubTools()
            );
            return $this->success($tools, 'Tools fetched successfully.');
        } catch (\Throwable $th) {
            Log::error('Tool Index Error', [
                'error' => $th->getMessage(),
            ]);
            return $this->error('Something went wrong.');
        }
    }


}
