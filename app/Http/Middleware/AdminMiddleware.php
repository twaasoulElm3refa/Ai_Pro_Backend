<?php

namespace App\Http\Middleware;

use App\Http\Controllers\api\admin\apiResponse;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    use apiResponse;
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): JsonResponse|Response
    {
        $user = auth()->user();
        if (!$user || $user->role !== 'admin') {
            return $this->unauthorized();
        }
        return $next($request);
    }
}