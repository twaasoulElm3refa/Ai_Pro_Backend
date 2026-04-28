<?php

namespace App\Http\Controllers\api\auth;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class WalletController extends Controller
{
    use ApiResponse;

    public function wallet()
    {
        try {
            $user = auth()->user();
            $userId = $user->id;
            $wallet = Cache::tags(['wallet', "user_{$userId}"])
                ->remember("wallet_user_{$userId}", now()->addMinutes(1), function () use ($userId) {

                    return Wallet::with('user:name,email,id,role')->firstOrCreate(
                        ['user_id' => $userId],
                        [
                            'balance' => 0,
                            'uuid' => Str::uuid(),
                        ]
                    );
                });
            return $this->success($wallet, 'Wallet fetched successfully.');
        } catch (Throwable $e) {
            Log::error('Wallet Error', [
                'user_id' => auth()->id(),
                'message' => $e->getMessage(),
            ]);
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong',
            ], 500);
        }
    }

    public function walletTransactions()
    {
        try {
            $userId = auth()->id();
            $page = request()->get('page', 1);
            $cacheKey = "wallet_transactions_user_{$userId}_page_{$page}";
            $transactions = Cache::tags(['wallet', 'transactions', "user_{$userId}"])
                ->remember($cacheKey, now()->addMinutes(1), function () use ($userId) {
                    return WalletTransaction::where('user_id', $userId)
                        ->latest()
                        ->paginate(5);
                });
            return $this->success($transactions, 'Wallet transactions fetched successfully.');
        } catch (\Throwable $th) {
            Log::error($th);

            return $this->error('Something went wrong.');
        }
    }

    public function walletTransactionDetails($slug)
    {
        try {
            $transaction = WalletTransaction::with('user:id,name,email')
                ->where('slug', $slug)
                ->first();
            return $this->success($transaction, 'Wallet transaction details fetched successfully.');
        } catch (\Throwable $th) {
            Log::error($th);
            return $this->error('Something went wrong.');
        }
    }
}
