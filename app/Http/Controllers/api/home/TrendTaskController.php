<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Trend\TrendImageRequest;
use App\Services\TrendTaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class TrendTaskController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly TrendTaskService $trendTaskService) {}

    public function cupLift(TrendImageRequest $request): JsonResponse
    {
        return $this->generate($request, 'cup-lifting-moment');
    }

    public function cupLiftingMoment(TrendImageRequest $request): JsonResponse
    {
        return $this->generate($request, 'cup-lifting-moment');
    }

    public function lockerRoom(TrendImageRequest $request): JsonResponse
    {
        return $this->generate($request, 'locker-room');
    }

    public function playersTunnel(TrendImageRequest $request): JsonResponse
    {
        return $this->generate($request, 'players-tunnel');
    }

    public function paparazzi(TrendImageRequest $request): JsonResponse
    {
        return $this->generate($request, 'paparazzi');
    }

    private function generate(TrendImageRequest $request, string $trendSlug): JsonResponse
    {
        try {
            $result = $this->trendTaskService->handle(
                $trendSlug,
                $request->safe()->except(['payload', 'file']),
                $request->file('file'),
                (int) $request->user()->id
            );

            return $this->success($result, 'Trend image generated successfully.');
        } catch (HttpExceptionInterface $exception) {
            return $this->error(
                $exception->getMessage() ?: 'The request could not be completed.',
                $exception->getStatusCode()
            );
        } catch (RuntimeException $exception) {
            Log::warning('Trend image generation request failed.', [
                'trend' => $trendSlug,
                'user_id' => $request->user()?->id,
                'conversation_uuid' => $request->input('conversation_uuid'),
                'message' => $exception->getMessage(),
            ]);

            return $this->error(
                'Trend image generation failed: '.$exception->getMessage(),
                502
            );
        } catch (Throwable $exception) {
            Log::error('Trend image request failed.', [
                'trend' => $trendSlug,
                'user_id' => $request->user()?->id,
                'conversation_uuid' => $request->input('conversation_uuid'),
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return $this->error('The image could not be generated right now. Please try again.', 502);
        }
    }
}
