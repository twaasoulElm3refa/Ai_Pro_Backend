<?php

namespace App\Http\Controllers\api\webhook;

use App\Http\Controllers\Controller;
use App\Models\MoyasarWebhookEvent;
use App\Services\MoyasarWalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class MoyasarWebhookController extends Controller
{
    public function __construct(private readonly MoyasarWalletService $moyasar) {}

    public function handle(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (! is_array($payload)) {
            return response()->json(['error' => 'invalid_json'], 400);
        }

        $moyasarPaymentId = data_get($payload, 'data.id');
        $eventType = $this->moyasar->webhookEventType($payload);
        Log::info('moyasar_webhook_received', [
            'event_type' => $eventType,
            'moyasar_payment_id' => is_string($moyasarPaymentId) ? $moyasarPaymentId : null,
        ]);

        try {
            if (! $this->moyasar->verifyWebhookSignature($payload)) {
                Log::warning('moyasar_webhook_secret_invalid', [
                    'moyasar_payment_id' => is_string($moyasarPaymentId) ? $moyasarPaymentId : null,
                ]);

                return response()->json(['error' => 'invalid_signature'], 401);
            }
        } catch (RuntimeException $e) {
            Log::critical('moyasar_webhook_not_configured', ['error' => $e->getMessage()]);

            return response()->json(['error' => 'webhook_not_configured'], 500);
        }

        if (! is_string($moyasarPaymentId) || $moyasarPaymentId === '') {
            return response()->json(['error' => 'missing_payment_id'], 422);
        }

        Log::info('moyasar_webhook_verified', [
            'event_type' => $eventType,
            'moyasar_payment_id' => $moyasarPaymentId,
        ]);

        $eventId = $this->moyasar->webhookEventId($payload);
        $safePayload = $payload;
        unset($safePayload['secret_token']);

        $record = MoyasarWebhookEvent::firstOrCreate(
            ['event_id' => $eventId],
            [
                'event_type' => $eventType,
                'moyasar_payment_id' => $moyasarPaymentId,
                'payload' => $safePayload,
                'status' => MoyasarWebhookEvent::STATUS_RECEIVED,
            ]
        );

        $claim = DB::transaction(function () use ($record) {
            $locked = MoyasarWebhookEvent::whereKey($record->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === MoyasarWebhookEvent::STATUS_PROCESSED) {
                return 'duplicate';
            }
            if ($locked->status === MoyasarWebhookEvent::STATUS_PROCESSING
                && $locked->updated_at->gt(now()->subMinutes(5))) {
                return 'duplicate';
            }

            $locked->update([
                'status' => MoyasarWebhookEvent::STATUS_PROCESSING,
                'error_message' => null,
            ]);

            return 'claimed';
        }, 3);

        if ($claim === 'duplicate') {
            Log::info('moyasar_webhook_duplicate', [
                'event_id' => $eventId,
                'moyasar_payment_id' => $moyasarPaymentId,
            ]);

            return response()->json(['status' => 'duplicate']);
        }

        try {
            $payment = $this->moyasar->handleWebhook($payload);
            $record->update([
                'local_payment_id' => $payment->id,
                'status' => MoyasarWebhookEvent::STATUS_PROCESSED,
                'processed_at' => now(),
                'error_message' => null,
            ]);

            Log::info('moyasar_webhook_processed', [
                'event_id' => $eventId,
                'payment_id' => $payment->id,
                'moyasar_payment_id' => $moyasarPaymentId,
                'payment_status' => $payment->status,
            ]);

            return response()->json(['status' => 'ok']);
        } catch (Throwable $e) {
            $record->update([
                'status' => MoyasarWebhookEvent::STATUS_FAILED,
                'error_message' => mb_substr($e->getMessage(), 0, 2000),
            ]);
            Log::error('moyasar_webhook_processing_failed', [
                'event_id' => $eventId,
                'event_type' => $eventType,
                'moyasar_payment_id' => $moyasarPaymentId,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'processing_failed'], 503);
        }
    }
}
