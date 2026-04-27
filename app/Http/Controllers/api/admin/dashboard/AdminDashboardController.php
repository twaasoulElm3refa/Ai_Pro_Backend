<?php

namespace App\Http\Controllers\api\admin\dashboard;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\MainTools;
use App\Models\Payment;
use App\Models\SubTools;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminDashboardController extends Controller
{
    use ApiResponse;

    public function index()
    {
        try {
            $statistics = Cache::remember('admin:statistics', now()->addMinutes(5), function () {
                $paymentAggregates = Payment::selectRaw("
                    COUNT(*) as total_payments,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_payments,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_payments,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_payments,
                    COALESCE(SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END), 0) as total_revenue
                ")->first();

                $userAggregates = User::selectRaw("
                    COUNT(*) as total_users,
                    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_users,
                    SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as new_users
                ", [now()->subDays(7)])->first();

                $walletAggregates = Wallet::selectRaw("
                    COALESCE(SUM(balance), 0) as total_wallet_balance,
                    COUNT(*) as total_wallets
                ")->first();

                $toolAggregates = [
                    'total_tools' => MainTools::count(),
                    'total_subtools' => SubTools::count(),
                ];

                $latestPayments = Payment::with('user:id,name,email')
                    ->select('id', 'transaction_id', 'status', 'amount', 'currency', 'paypal_order_id', 'user_id', 'created_at')
                    ->latest('id')
                    ->limit(5)
                    ->get();

                $latestUsers = User::select('id', 'name', 'email', 'created_at', 'is_active')
                    ->latest('id')
                    ->limit(5)
                    ->get();

                return [
                    'payments' => [
                        'total_payments' => (int) ($paymentAggregates->total_payments ?? 0),
                        'completed_payments' => (int) ($paymentAggregates->completed_payments ?? 0),
                        'failed_payments' => (int) ($paymentAggregates->failed_payments ?? 0),
                        'pending_payments' => (int) ($paymentAggregates->pending_payments ?? 0),
                        'total_revenue' => (float) ($paymentAggregates->total_revenue ?? 0),
                    ],
                    'users' => [
                        'total_users' => (int) ($userAggregates->total_users ?? 0),
                        'active_users' => (int) ($userAggregates->active_users ?? 0),
                        'new_users' => (int) ($userAggregates->new_users ?? 0),
                    ],
                    'wallets' => [
                        'total_wallet_balance' => (float) ($walletAggregates->total_wallet_balance ?? 0),
                        'total_wallets' => (int) ($walletAggregates->total_wallets ?? 0),
                    ],
                    'tools' => $toolAggregates,
                    'latest' => [
                        'payments' => $latestPayments,
                        'users' => $latestUsers,
                    ],
                ];
            });

            return $this->success($statistics, 'Statistics fetched successfully.');
        } catch (\Throwable $th) {
            Log::error('Admin Statistics Error', [
                'message' => $th->getMessage(),
                'trace' => $th->getTraceAsString(),
            ]);

            return $this->error('Something went wrong while fetching dashboard statistics.');
        }
    }
}
