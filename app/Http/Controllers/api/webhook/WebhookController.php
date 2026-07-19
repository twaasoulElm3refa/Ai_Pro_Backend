<?php

namespace App\Http\Controllers\api\webhook;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaypalWebhookEvent;
use App\Services\PayPalClientFactory;
use App\Services\PayPalServices;
use App\Services\PayPalWalletServices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class WebhookController extends Controller
{
    private const SUPPORTED_EVENTS = [
        'CHECKOUT.ORDER.APPROVED',
        'PAYMENT.CAPTURE.COMPLETED',
        'PAYMENT.CAPTURE.DECLINED',
    ];

    private const SIGNATURE_HEADERS = [
        'PAYPAL-TRANSMISSION-ID',
        'PAYPAL-TRANSMISSION-TIME',
        'PAYPAL-CERT-URL',
        'PAYPAL-AUTH-ALGO',
        'PAYPAL-TRANSMISSION-SIG',
    ];

    public function __construct(
        private readonly PayPalClientFactory $clientFactory,
        private readonly PayPalWalletServices $walletPaypal,
    ) {}

    public function handle(Request $request)
    {
        $body = $request->getContent();
        $event = json_decode($body, true);
        if (! is_array($event)) {
            return response()->json(['error' => 'invalid_json'], 400);
        }

        $eventId = $event['id'] ?? null;
        $eventType = $event['event_type'] ?? null;
        Log::info('webhook_received', [
            'event_id' => is_string($eventId) ? $eventId : null,
            'event_type' => is_string($eventType) ? $eventType : null,
        ]);

        $verificationError = $this->verifySignature($request, $body);
        if ($verificationError !== null) {
            return $verificationError;
        }
        Log::info('webhook_verified', ['event_id' => $eventId, 'event_type' => $eventType]);

        if (! is_string($eventId) || $eventId === '') {
            return response()->json(['error' => 'missing_event_id'], 422);
        }
        if (! in_array($eventType, self::SUPPORTED_EVENTS, true)) {
            return response()->json(['status' => 'ignored'], 200);
        }

        $paypalOrderId = $this->extractPaypalOrderId($event);
        $captureId = $this->extractCaptureId($event);
        if (! $paypalOrderId) {
            return response()->json(['error' => 'missing_paypal_order_id'], 422);
        }

        $record = PaypalWebhookEvent::firstOrCreate(
            ['event_id' => $eventId],
            [
                'event_type' => $eventType,
                'paypal_order_id' => $paypalOrderId,
                'capture_id' => $captureId,
                'payload' => $event,
                'status' => PaypalWebhookEvent::STATUS_RECEIVED,
            ]
        );

        $claim = DB::transaction(function () use ($record) {
            $locked = PaypalWebhookEvent::whereKey($record->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === PaypalWebhookEvent::STATUS_PROCESSED) {
                return 'duplicate';
            }
            if ($locked->status === PaypalWebhookEvent::STATUS_PROCESSING
                && $locked->updated_at->gt(now()->subMinutes(5))) {
                return 'duplicate';
            }
            $locked->update([
                'status' => PaypalWebhookEvent::STATUS_PROCESSING,
                'error_message' => null,
            ]);

            return 'claimed';
        }, 3);

        if ($claim === 'duplicate') {
            Log::info('webhook_duplicate', ['event_id' => $eventId]);
            return response()->json(['status' => 'duplicate'], 200);
        }

        try {
            $payment = Payment::where('paypal_order_id', $paypalOrderId)->first();
            if (! $payment) {
                throw new \RuntimeException('Payment not found.');
            }

            if ($payment->type === 'wallet_deposit') {
                $this->walletPaypal->handleWebhook($event);
                $handledBy = 'wallet';
            } else {
                app(PayPalServices::class)->handleWebhook($event);
                $handledBy = 'checkout';
            }

            $record->update([
                'status' => PaypalWebhookEvent::STATUS_PROCESSED,
                'processed_at' => now(),
                'error_message' => null,
            ]);

            return response()->json(['status' => 'ok', 'handled_by' => $handledBy]);
        } catch (Throwable $e) {
            $record->update([
                'status' => PaypalWebhookEvent::STATUS_FAILED,
                'error_message' => mb_substr($e->getMessage(), 0, 2000),
            ]);
            Log::error('webhook_processing_failed', [
                'event_id' => $eventId,
                'event_type' => $eventType,
                'paypal_order_id' => $paypalOrderId,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'processing_failed'], 503);
        }
    }

    private function verifySignature(Request $request, string $body): mixed
    {
        $mustVerify = (bool) config('paypal.verify_webhook', true);
        if (! $mustVerify) {
            if (! app()->environment(['local', 'testing'])) {
                Log::critical('paypal_webhook_verification_disabled_outside_local');
                return response()->json(['error' => 'unsafe_webhook_configuration'], 500);
            }
            Log::warning('paypal_webhook_verification_disabled_locally');
            return null;
        }

        foreach (self::SIGNATURE_HEADERS as $header) {
            if (! $request->hasHeader($header) || $request->header($header) === '') {
                return response()->json(['error' => 'missing_signature_headers'], 400);
            }
        }

        $webhookId = (string) config('paypal.webhook_id', '');
        if ($webhookId === '') {
            Log::error('paypal_webhook_id_missing');
            return response()->json(['error' => 'webhook_not_configured'], 500);
        }

        try {
            $verification = $this->clientFactory->make()->verifyWebHook([
                'transmission_id' => $request->header('PAYPAL-TRANSMISSION-ID'),
                'transmission_time' => $request->header('PAYPAL-TRANSMISSION-TIME'),
                'cert_url' => $request->header('PAYPAL-CERT-URL'),
                'auth_algo' => $request->header('PAYPAL-AUTH-ALGO'),
                'transmission_sig' => $request->header('PAYPAL-TRANSMISSION-SIG'),
                'webhook_id' => $webhookId,
                'webhook_event' => json_decode($body),
            ]);
        } catch (Throwable $e) {
            Log::error('paypal_webhook_verification_unavailable', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'verification_unavailable'], 503);
        }

        if (($verification['verification_status'] ?? null) !== 'SUCCESS') {
            Log::warning('paypal_webhook_signature_invalid', [
                'transmission_id' => $request->header('PAYPAL-TRANSMISSION-ID'),
            ]);
            return response()->json(['error' => 'invalid_signature'], 400);
        }

        return null;
    }

    private function extractPaypalOrderId(array $event): ?string
    {
        $resource = $event['resource'] ?? [];

        return match ($event['event_type'] ?? null) {
            'CHECKOUT.ORDER.APPROVED' => $resource['id'] ?? null,
            'PAYMENT.CAPTURE.COMPLETED', 'PAYMENT.CAPTURE.DECLINED'
                => data_get($resource, 'supplementary_data.related_ids.order_id'),
            default => null,
        };
    }

    private function extractCaptureId(array $event): ?string
    {
        return in_array($event['event_type'] ?? null, [
            'PAYMENT.CAPTURE.COMPLETED',
            'PAYMENT.CAPTURE.DECLINED',
        ], true) ? ($event['resource']['id'] ?? null) : null;
    }
}
