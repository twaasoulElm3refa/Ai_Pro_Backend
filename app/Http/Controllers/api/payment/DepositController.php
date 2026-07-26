<?php

namespace App\Http\Controllers\api\payment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wallet\StoreDepositRequest;
use App\Models\Payment;
use App\Services\PayPalWalletServices;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class DepositController extends Controller
{
    private const LOCALES = ['ar', 'en', 'ru', 'fr', 'zh'];

    public function __construct(private readonly PayPalWalletServices $paypal) {}

    public function create(StoreDepositRequest $request): JsonResponse
    {
        try {
            $result = $this->paypal->pay([
                ...$request->validated(),
                'user_id' => (int) $request->user()->id,
            ]);

            return response()->json([
                'success' => true,
                'order_id' => $result['order']->id,
                'status' => $result['order']->status,
                'amount' => $result['order']->amount,
                'currency' => $result['order']->currency,
                'approval_url' => $result['approval_url'],
            ]);
        } catch (DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 409);
        } catch (Throwable $e) {
            Log::error('wallet_deposit_create_failed', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to create payment.',
            ], 502);
        }
    }

    public function success(Request $request)
    {
        $lang = $this->locale($request->query('lang'));
        $token = (string) $request->query('token', '');
        if ($token === '' || strlen($token) > 64) {
            return redirect("/{$lang}/failed?error=TOKEN_MISSING");
        }

        try {
            $result = $this->paypal->success($token);
            $orderId = $result['order_id'] ?? null;
            if (! $orderId) {
                throw new DomainException('Local payment ID is missing.');
            }

            return redirect("/{$lang}/Deposit/waiting?provider=paypal&order_id={$orderId}");
        } catch (Throwable $e) {
            Log::warning('wallet_return_failed', [
                'paypal_order_id' => $token,
                'error' => $e->getMessage(),
            ]);

            return redirect("/{$lang}/failed?error=RETURN_INVALID");
        }
    }

    public function cancel(Request $request)
    {
        $lang = $this->locale($request->query('lang'));

        return redirect("/{$lang}/deposit/cancel");
    }

    public function orderStatus(Request $request, int $id): JsonResponse
    {
        $order = Payment::query()
            ->select(['id', 'user_id', 'type', 'status', 'amount', 'currency', 'paid_at'])
            ->whereKey($id)
            ->where('user_id', $request->user()->id)
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

    private function locale(mixed $locale): string
    {
        return in_array($locale, self::LOCALES, true) ? $locale : 'en';
    }
}
