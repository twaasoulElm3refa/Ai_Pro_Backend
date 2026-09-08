<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\ModelCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class ModelCatalogController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly ModelCatalogService $catalogs) {}

    public function show(Request $request, string $source): JsonResponse
    {
        try {
            $operation = $request->query('operation');

            if ($operation !== null && ! is_string($operation)) {
                throw new InvalidArgumentException('Invalid model catalog operation.');
            }

            return $this->success(
                $this->catalogs->getModels($source, $operation),
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
