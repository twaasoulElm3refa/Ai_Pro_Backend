<?php

use App\Http\Controllers\api\auth\AuthController;
use App\Http\Controllers\api\auth\GoogleAuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    Route::prefix('users')->group(function () {
        Route::post('register', [AuthController::class, 'register'])
            ->middleware(['throttle:3,1', 'guest']);

        Route::post('login', [AuthController::class, 'login'])
            ->middleware(['throttle:7,1', 'guest']);

        Route::post('forgot-password', [AuthController::class, 'forgotPassword'])
            ->middleware(['throttle:5,1', 'guest']);

        Route::post('reset-password', [AuthController::class, 'resetPassword'])
            ->middleware(['throttle:5,1', 'guest']);

        Route::get('google-login', [GoogleAuthController::class, 'googleLogin'])
            ->middleware('guest');

        Route::get('google-callback', [GoogleAuthController::class, 'googleCallback'])
            ->middleware('guest');

        Route::middleware(['auth:sanctum', 'throttle:15,1'])->group(function () {
            Route::get('wallet', [AuthController::class, 'wallet']);
            Route::get('profile', [AuthController::class, 'profile']);
            Route::post('update-profile', [AuthController::class, 'updateProfile']);
            Route::put('password', [AuthController::class, 'updatePassword']);
        });
    });
});
