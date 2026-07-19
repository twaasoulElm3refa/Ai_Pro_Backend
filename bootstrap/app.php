<?php

use App\Exceptions\AiServiceException;
use App\Console\Commands\ReconcilePayPalWalletPayments;
use App\Http\Middleware\AcceptLanguage;
use App\Http\Middleware\ApiKeyMiddleware;
use App\Http\Middleware\Utf8JsonResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        ReconcilePayPalWalletPayments::class,
    ])
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(ApiKeyMiddleware::class);
        $middleware->api(AcceptLanguage::class);
        $middleware->api(Utf8JsonResponse::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (AiServiceException $e, Request $request) {
            Log::error('AI service exception captured by global handler.', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'context' => $e->context(),
                'path' => $request->path(),
                'method' => $request->method(),
            ]);

            if (! $request->is('api/*')) {
                return null;
            }

            if (app()->environment('local')) {
                return response()->json([
                    'success' => false,
                    'error' => $e->getMessage(),
                    'trace' => collect($e->getTrace())->take(5)->values(),
                    'context' => $e->context(),
                ], 502);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to generate AI response.',
            ], 502);
        });
    })->create();
