<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Repository\AiModels\AiModelsInterface;
use App\Repository\tools\MainToolInterface;
use App\Repository\tools\SubToolInterface;
use App\Repository\Trends\TrendInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class HomeController extends Controller
{
    use ApiResponse;

    private $toolRepository;
    private $subToolRepository;
    private $AiModelsRepository;
    private $trendsRepository;

    private function remember(array $tags, string $key, callable $resolver): mixed
    {
        try {
            if (Cache::supportsTags()) {
                return Cache::tags($tags)->remember($key, now()->addHour(), $resolver);
            }

            return Cache::remember($key, now()->addHour(), $resolver);
        } catch (\Throwable $exception) {
            Log::warning('Home cache unavailable; using repository fallback.', [
                'key' => $key,
                'error' => $exception->getMessage(),
            ]);

            return $resolver();
        }
    }

    public function __construct(MainToolInterface $toolRepository ,SubToolInterface $subToolRepository, AiModelsInterface $AiModelsRepository , TrendInterface $trendsRepository)
    {
        $this->toolRepository = $toolRepository;
        $this->subToolRepository=$subToolRepository;
        $this->AiModelsRepository=$AiModelsRepository;
        $this->trendsRepository=$trendsRepository;
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
            $tools = $this->remember(['tools'], $cacheKey, fn () => $this->toolRepository->index());
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

            $tool = $this->remember(
                ['tools'],
                "tools:show:{$slug}:{$locale}",
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

            $tool = $this->remember(
                ['subtools'],
                "subtools:show:{$slug}:{$locale}",
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
            $tools = $this->remember(
                ['subtools'],
                "subtools:random:{$locale}",
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

    public function AiModels()
    {
        try {
            $locale = app()->getLocale();
            $tools = $this->remember(
                ['AiModels'],
                "AiModels:{$locale}",
                fn () => $this->AiModelsRepository->AiModels()
            );
            return $this->success($tools, 'Tools fetched successfully.');
        } catch (\Throwable $th) {
            Log::error('Tool Index Error', [
                'error' => $th->getMessage(),
            ]);
            return $this->error('Something went wrong.');
        }
    }

    public function trends()
    {
         try {
            $locale = app()->getLocale();
            $tools = $this->remember(
                ['trends'],
                "trends:{$locale}",
                fn () => $this->trendsRepository->index()
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
