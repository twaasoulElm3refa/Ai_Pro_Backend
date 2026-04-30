<?php

use App\Http\Controllers\api\admin\AdminUserController;
use App\Http\Controllers\api\admin\auth\AdminAuthController;
use App\Http\Controllers\api\admin\contact\ContactController;
use App\Http\Controllers\api\admin\dashboard\AdminDashboardController;
use App\Http\Controllers\api\admin\footer\FooterController;
use App\Http\Controllers\api\admin\payment\AdminPaymentController;
use App\Http\Controllers\api\admin\tools\MainToolController;
use App\Http\Controllers\api\admin\tools\SubToolController;
use App\Http\Controllers\api\auth\AuthController;
use App\Http\Controllers\api\auth\GoogleAuthController;
use App\Http\Controllers\api\auth\ProfileController;
use App\Http\Controllers\api\auth\RegisterController;
use App\Http\Controllers\api\auth\WalletController;
use App\Http\Controllers\api\home\ConversationController;
use App\Http\Controllers\api\home\HomeController;
use App\Http\Controllers\api\home\MessageController;
use App\Http\Controllers\api\payment\DepositController;
use App\Http\Controllers\api\webhook\WebhookController;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\ApiKeyMiddleware;
use App\Http\Middleware\ConversationOwnerMiddleware;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    Route::prefix('users')->group(function () {
        Route::post('register', [RegisterController::class, 'register'])
            ->middleware('throttle:6,1');
        Route::post('login', [AuthController::class, 'login'])
            ->middleware('throttle:7,1');
        Route::post('verify-otp', [AuthController::class, 'verifyLoginOtp'])
            ->middleware('throttle:6,1');
        Route::post('resend-otp', [AuthController::class, 'resendOtp'])
            ->middleware('throttle:5,1');
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
            Route::get('wallet/transactions', [WalletController::class, 'walletTransactions']);
            Route::get('wallet/transaction/{slug}', [WalletController::class, 'walletTransactionDetails']);
            Route::get('profile', [ProfileController::class, 'profile']);
            Route::post('update-profile', [ProfileController::class, 'updateProfile']);
            Route::post('password', [ProfileController::class, 'updatePassword']);
            Route::post('logout', [AuthController::class, 'logout']);
            Route::delete('delete-account', [ProfileController::class, 'deleteAccount']);
        });
    });

    Route::prefix('tools')->middleware(['throttle:15,1'])->group(function () {
        Route::get('/', [HomeController::class, 'index']);
        Route::get('/{slug}', [HomeController::class, 'show']);
        Route::get('/subtool/{slug}', [HomeController::class, 'showChat']);
    });

    Route::get('/conversation/{uuid}/stream', [ConversationController::class, 'conversationStream'])
        ->middleware('throttle:30,1')
        ->withoutMiddleware(ApiKeyMiddleware::class);

    Route::prefix('conversation')->middleware(['auth:sanctum', 'throttle:45,1'])->group(function () {
        Route::get('/', [ConversationController::class, 'conversation']);
        Route::get('/{uuid}', [ConversationController::class, 'conversationDetails']);
        Route::post('/{slug}', [ConversationController::class, 'createConversation']);
        Route::delete('/{uuid}', [ConversationController::class, 'conversationDelete'])->middleware(ConversationOwnerMiddleware::class);
    });

    Route::prefix('message')->middleware(['auth:sanctum', 'throttle:45,1'])->group(function () {
        Route::post('/send', [MessageController::class, 'sendMessage']);
    });

    Route::prefix('deposit')->middleware(['auth:sanctum', 'throttle:10,1'])->group(function () {
        Route::post('/pay', [DepositController::class, 'create']);
    });
    Route::get('/wallet/success', [DepositController::class, 'success'])->name('wallet.success')->middleware('throttle:30,1')->withoutMiddleware(ApiKeyMiddleware::class);
    Route::get('/wallet/cancel',  [DepositController::class, 'cancel'])->name('wallet.cancel')->middleware('throttle:30,1')->withoutMiddleware(ApiKeyMiddleware::class);
    Route::post('/paypal/webhook', [WebhookController::class, 'handle'])->name('paypal.webhook')->middleware('throttle:60,1')->withoutMiddleware(ApiKeyMiddleware::class);
    Route::get('/wallet/order-status/{id}', [DepositController::class, 'orderStatus'])
        ->middleware(['auth:sanctum', 'throttle:30,1'])
        ->withoutMiddleware(ApiKeyMiddleware::class);
});


Route::prefix('admin')->group(function () {
    Route::post('/login', [AdminAuthController::class, 'login'])->middleware('throttle:5,1');
    Route::prefix('')->middleware(['auth:sanctum', AdminMiddleware::class, 'throttle:60,1'])->group(function () {
        Route::get('/profile', [AdminAuthController::class, 'profile']);
        Route::post('/profile', [AdminAuthController::class, 'updateProfile']);
        Route::post('/password', [AdminAuthController::class, 'updatePassword']);
        Route::post('/logout', [AdminAuthController::class, 'logout']);
        Route::get('/statistics', [AdminDashboardController::class, 'index']);
    });
    Route::prefix('users')->middleware(['auth:sanctum', AdminMiddleware::class, 'throttle:60,1'])->group(function () {
        Route::get('/', [AdminUserController::class, 'index']);
        Route::get('/{id}', [AdminUserController::class, 'show']);
        Route::post('/', [AdminUserController::class, 'store']);
        Route::post('/{id}', [AdminUserController::class, 'update']);
        Route::delete('/{id}', [AdminUserController::class, 'destroy']);
    });
    Route::prefix('footer')->middleware(['auth:sanctum', AdminMiddleware::class, 'throttle:60,1'])->group(function () {
        Route::get('/', [FooterController::class, 'index']);
        Route::post('/', [FooterController::class, 'update']);
    });
    Route::prefix('contact')->middleware(['auth:sanctum', AdminMiddleware::class, 'throttle:60,1'])->group(function () {
        Route::get('/', [ContactController::class, 'index']);
        Route::get('/{id}', [ContactController::class, 'show']);
        Route::post('/{id}', [ContactController::class, 'update']);
        Route::delete('/{id}', [ContactController::class, 'destroy']);
    });
    Route::prefix('tools')->middleware(['auth:sanctum', AdminMiddleware::class, 'throttle:60,1'])->group(function () {
        Route::get('/', [MainToolController::class, 'index']);
        Route::get('/{id}', [MainToolController::class, 'show']);
        Route::post('/', [MainToolController::class, 'store']);
        Route::post('/{id}', [MainToolController::class, 'update']);
        Route::delete('/{id}', [MainToolController::class, 'destroy']);
    });
    Route::prefix('subtools')->middleware(['auth:sanctum', AdminMiddleware::class, 'throttle:60,1'])->group(function () {
        Route::get('/{id}', [SubToolController::class, 'index']);
        Route::get('/show/{id}', [SubToolController::class, 'show']);
        Route::post('/{id}', [SubToolController::class, 'store']);
        Route::post('/update/{id}', [SubToolController::class, 'update']);
        Route::delete('/delete/{id}', [SubToolController::class, 'destroy']);
    });
    Route::prefix('payments')->middleware(['auth:sanctum', AdminMiddleware::class, 'throttle:60,1'])->group(function () {
        Route::get('/', [AdminPaymentController::class, 'index']);
        Route::get('/{id}', [AdminPaymentController::class, 'show']);
        Route::post('/{id}', [AdminPaymentController::class, 'update']);
        Route::delete('/{id}', [AdminPaymentController::class, 'destroy']);
    });
    // 60 Api For Now
});
