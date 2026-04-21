<?php

use App\Http\Controllers\api\auth\AuthController;
use App\Http\Controllers\api\auth\GoogleAuthController;
use App\Http\Controllers\api\auth\RegisterController;
use App\Http\Middleware\ApiKeyMiddleware;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    Route::prefix('users')->group(function () {
        Route::post('send-otp', [RegisterController::class, 'sendOtp'])
        ->middleware(['throttle:6,1']);
        Route::post('verify-otp', [RegisterController::class, 'verifyOtp'])
        ->middleware(['throttle:6,1']);
        Route::post('register', [RegisterController::class, 'register'])
        ->middleware(['throttle:6,1']);


        Route::post('login', [AuthController::class, 'login'])
            ->middleware(['throttle:7,1']);

        Route::post('forgot-password', [AuthController::class, 'forgotPassword'])
            ->middleware(['throttle:5,1', 'guest']);

        Route::post('reset-password', [AuthController::class, 'resetPassword'])
            ->middleware(['throttle:5,1', 'guest']);

        Route::get('google-login', [GoogleAuthController::class, 'googleLogin'])
            ->middleware('guest');

        Route::get('google-callback', [GoogleAuthController::class, 'googleCallback'])
            ->middleware('guest')->withoutMiddleware(ApiKeyMiddleware::class);

        Route::middleware(['auth:sanctum', 'throttle:15,1'])->group(function () {
            Route::get('wallet', [AuthController::class, 'wallet']);
            Route::get('profile', [AuthController::class, 'profile']);


            Route::post('update-profile', [AuthController::class, 'updateProfile']);
            Route::post('password', [AuthController::class, 'updatePassword']);
            Route::post('logout', [AuthController::class, 'logout']);
            Route::post('refresh-token', [AuthController::class, 'refreshToken']);

        });
    });
});
