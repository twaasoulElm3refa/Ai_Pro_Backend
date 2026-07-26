<?php

namespace App\Http\Controllers\api\payment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wallet\MoyasarDepositRequest;
use App\Models\Payment;
use App\Services\MoyasarWalletService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class MoyasarDepositController extends Controller
{
    public function __construct(private readonly MoyasarWalletService $moyasar) {}

    public function create(MoyasarDepositRequest $request): JsonResponse
    {
        try {
            $result = $this->moyasar->createPayment([
                ...$request->validated(),
                'user_id' => (int) $request->user()->id,
            ]);

            return response()->json([
                'success' => true,
                'order_id' => $result['order']->id,
                'status' => $result['order']->status,
                'amount' => $result['order']->amount,
                'currency' => $result['order']->currency,
                'payment_url' => $result['payment_url'],
            ]);
        } catch (DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 409);
        } catch (Throwable $e) {
            Log::error('moyasar_wallet_deposit_create_failed', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to create Moyasar payment.',
            ], 502);
        }
    }

    public function status(Request $request, int $id): JsonResponse
    {
        $order = Payment::query()
            ->select(['id', 'user_id', 'payment_method', 'type', 'status', 'amount', 'currency', 'paid_at'])
            ->whereKey($id)
            ->where('user_id', $request->user()->id)
            ->where('payment_method', 'moyasar')
            ->where('type', 'wallet_deposit')
            ->first();

        if (! $order) {
            return response()->json(['status' => 'not_found'], 404);
        }

        return response()->json([
            'order_id' => $order->id,
            'status' => $order->status,
            'amount' => $order->amount,
            'currency' => $order->currency,
            'paid_at' => $order->paid_at?->toISOString(),
        ]);
    }
}
