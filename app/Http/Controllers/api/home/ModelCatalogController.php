<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\ModelCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class ModelCatalogController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly ModelCatalogService $catalogs) {}

    public function show(string $source): JsonResponse
    {
        try {
            return $this->success(
                $this->catalogs->getModels($source),
                'Model catalog fetched successfully.'
            );
        } catch (InvalidArgumentException) {
            return $this->notFound('Model catalog source not found.');
        } catch (Throwable $exception) {
            Log::warning('Model catalog proxy request failed.', [
                'source' => $source,
                'exception' => $exception::class,
            ]);

            return $this->error('Model catalog is currently unavailable.', 502);
        }
    }
}
