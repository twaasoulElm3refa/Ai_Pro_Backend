<?php

namespace App\Http\Middleware;

use App\Http\Controllers\concerns\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyMiddleware
{
    use ApiResponse;
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = env('API_KEY');

        if (! is_string($apiKey) || $apiKey === '' || ! hash_equals($apiKey, (string) $request->header('X-API-KEY', ''))) {
            return $this->unauthorized('Must provide valid api key');
        }
        return $next($request);
    }
}
