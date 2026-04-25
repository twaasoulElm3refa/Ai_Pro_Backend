<?php

use App\Http\Controllers\api\admin\AdminUserController;
use App\Http\Controllers\api\admin\auth\AdminAuthController;
use App\Http\Controllers\api\admin\contact\ContactController;
use App\Http\Controllers\api\admin\footer\FooterController;
use App\Http\Controllers\api\admin\tools\MainToolController;
use App\Http\Controllers\api\auth\AuthController;
use App\Http\Controllers\api\auth\GoogleAuthController;
use App\Http\Controllers\api\auth\ProfileController;
use App\Http\Controllers\api\auth\RegisterController;
use App\Http\Controllers\api\auth\WalletController;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\ApiKeyMiddleware;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    Route::prefix('users')->group(function () {
        // ── Register (مرة واحدة بدون OTP)
        Route::post('register', [RegisterController::class, 'register'])
            ->middleware('throttle:6,1');

        // ── Login + OTP Verification
        Route::post('login', [AuthController::class, 'login'])
            ->middleware('throttle:7,1');
        Route::post('verify-otp', [AuthController::class, 'verifyLoginOtp'])
            ->middleware('throttle:6,1');
        Route::post('resend-otp', [AuthController::class, 'resendOtp'])
            ->middleware('throttle:5,1');

        // ── Forgot / Reset Password
        Route::post('forgot-password', [AuthController::class, 'forgotPassword'])
            ->middleware(['throttle:5,1', 'guest']);
        Route::post('reset-password', [AuthController::class, 'resetPassword'])
            ->middleware(['throttle:5,1', 'guest']);

        Route::get('google-login', [GoogleAuthController::class, 'googleLogin'])
            ->middleware('guest');
        Route::get('google-callback', [GoogleAuthController::class, 'googleCallback'])
            ->middleware('guest')->withoutMiddleware(ApiKeyMiddleware::class);

        Route::middleware(['auth:sanctum', 'throttle:15,1'])->group(function () {
            Route::get('wallet', [WalletController::class, 'wallet']);
            Route::get('profile', [ProfileController::class, 'profile']);
            Route::post('update-profile', [ProfileController::class, 'updateProfile']);
            Route::post('password', [ProfileController::class, 'updatePassword']);
            Route::post('logout', [AuthController::class, 'logout']);
            Route::delete('delete-account', [ProfileController::class, 'deleteAccount']);

        });
    });
});


Route::prefix('admin')->group(function () {

    Route::post('/login', [AdminAuthController::class, 'login']);

    Route::prefix('')->middleware(['auth:sanctum', AdminMiddleware::class])->group(function () {
        Route::get('/profile', [AdminAuthController::class, 'profile']);
        Route::post('/profile', [AdminAuthController::class, 'updateProfile']);
        Route::post('/password', [AdminAuthController::class, 'updatePassword']);
        Route::post('/logout', [AdminAuthController::class, 'logout']);
    });

    Route::prefix('users')->middleware(['auth:sanctum', AdminMiddleware::class])->group(function () {
        Route::get('/', [AdminUserController::class, 'index']);
        Route::get('/{id}', [AdminUserController::class, 'show']);
        Route::post('/', [AdminUserController::class, 'store']);
        Route::post('/{id}', [AdminUserController::class, 'update']);
        Route::delete('/{id}', [AdminUserController::class, 'destroy']);
    });

    Route::prefix('footer')->middleware(['auth:sanctum', AdminMiddleware::class])->group(function () {
        Route::get('/', [FooterController::class, 'index']);
        Route::post('/', [FooterController::class, 'update']);
    });

    Route::prefix('contact')->middleware(['auth:sanctum', AdminMiddleware::class])->group(function () {
        Route::get('/', [ContactController::class, 'index']);
        Route::get('/{id}', [ContactController::class, 'show']);
        Route::post('/{id}', [ContactController::class, 'update']);
        Route::delete('/{id}', [ContactController::class, 'destroy']);
    });

    Route::prefix('tools')->middleware(['auth:sanctum', AdminMiddleware::class])->group(function () {
        Route::get('/', [MainToolController::class, 'index']);
        Route::get('/{id}', [MainToolController::class, 'show']);
        Route::post('/', [MainToolController::class, 'store']);
        Route::post('/{id}', [MainToolController::class, 'update']);
        Route::delete('/{id}', [MainToolController::class, 'destroy']);
    });

});
