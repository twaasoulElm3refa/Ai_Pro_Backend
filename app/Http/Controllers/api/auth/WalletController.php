<?php

namespace App\Http\Controllers\api\auth;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Wallet;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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

            $wallet = Cache::remember(
                "wallet_user_{$user->id}",
                now()->addMinutes(10),
                function () use ($user) {

                    return DB::transaction(function () use ($user) {

                        $wallet = Cache::remember(
                            "wallet_user_{$user->id}",
                            now()->addMinutes(10),
                            function () use ($user) {

                                return Wallet::firstOrCreate(
                                    ['user_id' => $user->id],
                                    [
                                        'balance' => 0,
                                        'uuid' => Str::uuid(),
                                    ]
                                )->toArray();
                            }
                        );

                        return $wallet;
                    });
                }
            );
            $user->load('wallet');
            $this->clearProfileCache($user->id);

            return $this->success($user, 'Wallet fetched successfully.');

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

    private function clearProfileCache($userId)
    {
        Cache::forget("user_profile_{$userId}");
    }
}
