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
use RuntimeException;
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
            $order = $result['order'];
            $paymentUrl = $result['payment_url'];
            $payload = [
                'deposit_id' => $order->id,
                'order_id' => $order->id,
                'payment_id' => $order->moyasar_payment_id,
                'invoice_id' => $order->moyasar_invoice_id,
                'redirect_url' => $paymentUrl,
                'payment_url' => $paymentUrl,
                'status' => $this->frontendStatus($order),
                'amount' => $order->amount,
                'amount_minor' => $order->amount_minor,
                'currency' => $order->currency,
                'points' => $order->expected_points,
            ];

            return response()->json([
                'success' => true,
                'message' => 'Payment created successfully',
                ...$payload,
                'data' => $payload,
            ]);
        } catch (DomainException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 409);
        } catch (RuntimeException $e) {
            Log::error('moyasar_wallet_deposit_create_failed', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 503);
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
            ->select([
                'id',
                'user_id',
                'payment_method',
                'type',
                'status',
                'payment_status',
                'amount',
                'amount_minor',
                'expected_points',
                'currency',
                'moyasar_invoice_id',
                'wallet_credited',
                'paid_at',
                'updated_at',
            ])
            ->whereKey($id)
            ->where('user_id', $request->user()->id)
            ->where('payment_method', 'moyasar')
            ->where('type', 'wallet_deposit')
            ->first();

        if (! $order) {
            return response()->json(['status' => 'not_found'], 404);
        }

        if ($this->shouldReconcile($order)) {
            try {
                $this->moyasar->reconcile($order);
                $order = $order->fresh();
            } catch (Throwable $e) {
                Log::warning('moyasar_wallet_status_reconciliation_failed', [
                    'payment_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $payload = [
            'deposit_id' => $order->id,
            'order_id' => $order->id,
            'status' => $this->frontendStatus($order),
            'payment_status' => $order->payment_status,
            'amount' => $order->amount,
            'amount_minor' => $order->amount_minor,
            'currency' => $order->currency,
            'points' => $order->expected_points,
            'paid_at' => $order->paid_at?->toISOString(),
        ];

        return response()->json([
            ...$payload,
            'data' => $payload,
        ]);
    }

    private function frontendStatus(Payment $order): string
    {
        $status = strtolower((string) $order->status);
        $paymentStatus = strtolower((string) $order->payment_status);

        if ($status === Payment::STATUS_COMPLETED && $order->wallet_credited) {
            return 'completed';
        }

        if ($status === Payment::STATUS_FAILED) {
            return in_array($paymentStatus, ['canceled', 'expired', 'refunded', 'voided'], true)
                ? $paymentStatus
                : 'failed';
        }

        if (in_array($paymentStatus, ['paid', 'captured', 'authorized'], true)
            || $status === Payment::STATUS_APPROVED) {
            return 'processing';
        }

        return in_array($paymentStatus, ['initiated', 'verified'], true) ? $paymentStatus : 'pending';
    }

    private function shouldReconcile(Payment $order): bool
    {
        if (! $order->moyasar_invoice_id || $order->wallet_credited) {
            return false;
        }

        if (! in_array($order->status, [Payment::STATUS_PENDING, Payment::STATUS_APPROVED], true)) {
            return false;
        }

        $ageMinutes = max(1, (int) config('moyasar.reconciliation_age_minutes', 10));

        return $order->updated_at?->lte(now()->subMinutes($ageMinutes)) ?? false;
    }
}
