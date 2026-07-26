<?php

namespace App\Services;

use App\Jobs\SendDepositFailedMailJob;
use App\Jobs\SendDepositSuccessMailJob;
use App\Models\Payment;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class MoyasarWalletService
{
    private const SUCCESS_STATUSES = ['paid', 'captured'];

    private const PENDING_STATUSES = ['initiated', 'verified'];

    private const FAILED_STATUSES = ['failed', 'voided', 'refunded', 'canceled', 'expired', 'abandoned'];

    private const INVOICE_FAILED_STATUSES = ['failed', 'canceled', 'expired', 'voided', 'refunded'];

    public function createPayment(array $data): array
    {
        $userId = (int) ($data['user_id'] ?? 0);
        $amount = $this->normalizeMoney((string) ($data['amount'] ?? ''));
        $currency = strtoupper((string) config('moyasar.currency', 'SAR'));
        $idempotencyKey = (string) ($data['idempotency_key'] ?? '');

        if ($userId <= 0 || $idempotencyKey === '') {
            throw new DomainException('Authenticated user and idempotency key are required.');
        }
        $this->assertConfiguration();

        $key = hash('sha256', $userId.'|'.$idempotencyKey);
        $order = $this->resolveLocalPayment($data, $key, $userId, $amount, $currency);
        $this->assertSameIdempotentPayload($order, $userId, $amount, $currency);

        if ($order->status === Payment::STATUS_COMPLETED) {
            return ['order' => $order, 'payment_url' => null];
        }
        if ($order->status === Payment::STATUS_FAILED) {
            throw new DomainException('This idempotency key belongs to a failed payment. Use a new key.');
        }
        if ($order->status === Payment::STATUS_APPROVED) {
            return ['order' => $order, 'payment_url' => null];
        }

        if ($order->moyasar_invoice_id) {
            $invoice = $this->checkInvoiceStatus($order->moyasar_invoice_id);
            $order = $this->syncInvoiceStateWithoutCrediting($order, $invoice);

            return [
                'order' => $order,
                'payment_url' => $order->status === Payment::STATUS_PENDING
                    ? $this->invoiceUrl($invoice)
                    : null,
            ];
        }

        $invoice = $this->findExistingInvoice($order)
            ?? $this->createHostedInvoice($order, (string) ($data['locale'] ?? 'en'));
        $this->validateInvoiceIdentityAndMoney($order, $invoice);

        $order = DB::transaction(function () use ($order, $invoice) {
            $locked = Payment::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $invoiceId = (string) $invoice['id'];

            if ($locked->moyasar_invoice_id && $locked->moyasar_invoice_id !== $invoiceId) {
                throw new RuntimeException('A different Moyasar invoice is already attached to this payment.');
            }

            $locked->update([
                'moyasar_invoice_id' => $invoiceId,
                'payment_status' => strtolower((string) ($invoice['status'] ?? 'initiated')),
                'gateway_response' => $this->sanitizeGatewayResponse($invoice),
            ]);

            return $locked->fresh();
        }, 3);

        Log::info('moyasar_wallet_invoice_created', [
            'payment_id' => $order->id,
            'moyasar_invoice_id' => $order->moyasar_invoice_id,
        ]);

        return ['order' => $order, 'payment_url' => $this->invoiceUrl($invoice)];
    }

    public function checkStatus(string $moyasarPaymentId): array
    {
        if (! $this->validGatewayId($moyasarPaymentId)) {
            throw new DomainException('Invalid Moyasar payment ID.');
        }

        return $this->get('/payments/'.rawurlencode($moyasarPaymentId));
    }

    public function checkInvoiceStatus(string $moyasarInvoiceId): array
    {
        if (! $this->validGatewayId($moyasarInvoiceId)) {
            throw new DomainException('Invalid Moyasar invoice ID.');
        }

        return $this->get('/invoices/'.rawurlencode($moyasarInvoiceId));
    }

    public function verifyWebhookSignature(array $payload): bool
    {
        $expected = $this->webhookSecret();
        if ($expected === '') {
            throw new RuntimeException('MOYASAR_WEBHOOK_SECRET is not configured.');
        }

        $provided = $payload['secret_token'] ?? null;

        return is_string($provided)
            && $provided !== ''
            && strlen($provided) <= 512
            && hash_equals($expected, $provided);
    }

    public function webhookLiveModeMatches(array $payload): bool
    {
        if (! array_key_exists('live', $payload) || ! is_bool($payload['live'])) {
            return false;
        }

        return $payload['live'] === ($this->mode() === 'live');
    }

    public function isSupportedWebhookEvent(string $eventType): bool
    {
        return in_array($eventType, [
            'payment_paid',
            'payment_captured',
            'payment_failed',
            'payment_faild',
            'payment_refunded',
            'payment_voided',
            'payment_authorized',
            'payment_verified',
            'payment_abandoned',
        ], true);
    }

    public function webhookEventId(array $payload): string
    {
        $eventId = (string) ($payload['id'] ?? '');
        if ($this->validGatewayId($eventId)) {
            return $eventId;
        }

        $payment = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        return hash('sha256', implode('|', [
            (string) ($payment['id'] ?? ''),
            $this->webhookEventType($payload),
            strtolower((string) ($payment['status'] ?? '')),
            (string) ($payment['updated_at'] ?? $payment['created_at'] ?? ''),
        ]));
    }

    public function webhookEventType(array $payload): string
    {
        $type = $payload['type'] ?? $payload['event'] ?? null;
        if (is_string($type) && $type !== '') {
            return strtolower($type);
        }

        $status = strtolower((string) data_get($payload, 'data.status', 'unknown'));

        return 'payment_'.$status;
    }

    public function handleWebhook(array $payload): Payment
    {
        if (! $this->verifyWebhookSignature($payload)) {
            throw new RuntimeException('Invalid Moyasar webhook signature.');
        }
        if (! $this->webhookLiveModeMatches($payload)) {
            throw new RuntimeException('Moyasar webhook mode mismatch.');
        }

        $notifiedPayment = $payload['data'] ?? null;
        if (! is_array($notifiedPayment)) {
            throw new RuntimeException('Moyasar webhook payment data is missing.');
        }

        $paymentId = (string) ($notifiedPayment['id'] ?? '');
        if (! $this->validGatewayId($paymentId)) {
            throw new RuntimeException('Moyasar webhook payment ID is invalid.');
        }

        // The webhook body is only a notification. This API response is authoritative.
        $verifiedPayment = $this->checkStatus($paymentId);
        if ((string) ($verifiedPayment['id'] ?? '') !== $paymentId) {
            throw new RuntimeException('Moyasar API returned a different payment ID.');
        }

        $invoiceId = (string) ($verifiedPayment['invoice_id'] ?? '');
        if (! $this->validGatewayId($invoiceId)) {
            throw new RuntimeException('Verified Moyasar payment has no valid invoice ID.');
        }

        $verifiedInvoice = $this->checkInvoiceStatus($invoiceId);
        $order = $this->findLocalPayment($verifiedPayment, $verifiedInvoice);
        $this->validateInvoiceIdentityAndMoney($order, $verifiedInvoice);
        $this->validateMoney($order, $verifiedPayment);
        $status = strtolower((string) ($verifiedPayment['status'] ?? ''));

        Log::info('moyasar_webhook_canonical_status', [
            'payment_id' => $order->id,
            'moyasar_payment_id' => $paymentId,
            'moyasar_status' => $status,
        ]);

        if (in_array($status, self::SUCCESS_STATUSES, true)) {
            return $this->creditWallet($order, $verifiedPayment, $verifiedInvoice);
        }

        if ($status === 'authorized') {
            return $this->markApproved($order, $verifiedPayment);
        }

        if (in_array($status, self::FAILED_STATUSES, true)) {
            return $this->markFailed($order, $verifiedPayment);
        }

        if (in_array($status, self::PENDING_STATUSES, true)) {
            return $this->storePendingStatus($order, $verifiedPayment);
        }

        throw new RuntimeException('Unsupported Moyasar payment status: '.$status);
    }

    public function creditWallet(
        Payment $order,
        array $verifiedPayment,
        array $verifiedInvoice
    ): Payment {
        $paymentId = (string) ($verifiedPayment['id'] ?? '');
        Log::info('moyasar_wallet_credit_started', [
            'payment_id' => $order->id,
            'moyasar_payment_id' => $paymentId,
        ]);

        $result = DB::transaction(function () use ($order, $verifiedPayment, $verifiedInvoice, $paymentId) {
            $locked = Payment::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $this->validateSuccessfulPayment($locked, $verifiedPayment, $verifiedInvoice);

            if ($locked->status === Payment::STATUS_COMPLETED || $locked->wallet_credited) {
                if ($locked->transaction_id !== $paymentId
                    || $locked->moyasar_payment_id !== $paymentId
                    || ! $locked->walletTransaction()->exists()) {
                    $this->validationFailure($locked, 'completed_payment_ledger_mismatch');
                }

                return ['order' => $locked, 'credited' => false];
            }

            if (Payment::where('transaction_id', $paymentId)->whereKeyNot($locked->id)->exists()
                || Payment::where('moyasar_payment_id', $paymentId)->whereKeyNot($locked->id)->exists()) {
                $this->validationFailure($locked, 'moyasar_payment_id_already_used');
            }

            Wallet::query()->insertOrIgnore([
                'user_id' => $locked->user_id,
                'uuid' => (string) Str::uuid(),
                'balance' => 0,
                'payback_balance' => 0,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $wallet = Wallet::withTrashed()
                ->where('user_id', $locked->user_id)
                ->lockForUpdate()
                ->firstOrFail();
            if ($wallet->trashed()) {
                $wallet->restore();
            }

            $points = $this->amountToPoints((string) $locked->amount);
            $before = (int) $wallet->balance;
            if ($points <= 0 || $before > PHP_INT_MAX - $points) {
                $this->validationFailure($locked, 'wallet_balance_overflow');
            }

            $wallet->forceFill(['balance' => $before + $points])->save();
            $wallet->refresh();
            $after = (int) $wallet->balance;

            WalletTransaction::create([
                'user_id' => $locked->user_id,
                'wallet_id' => $wallet->id,
                'payment_id' => $locked->id,
                'points' => $points,
                'type' => 'credit',
                'description' => 'Moyasar wallet deposit',
                'balance_before' => $before,
                'balance_after' => $after,
                'slug' => $locked->idempotency_key,
            ]);

            $locked->update([
                'status' => Payment::STATUS_COMPLETED,
                'payment_status' => strtolower((string) $verifiedPayment['status']),
                'wallet_credited' => true,
                'transaction_id' => $paymentId,
                'moyasar_payment_id' => $paymentId,
                'gateway_response' => $this->sanitizeGatewayResponse($verifiedPayment),
                'paid_at' => $this->paidAt($verifiedPayment),
                'mail_sent' => false,
            ]);

            DB::afterCommit(function () use ($locked) {
                $this->flushWalletCache($locked);
                $this->queueSuccessMail($locked);
            });

            return ['order' => $locked->fresh(), 'credited' => true];
        }, 3);

        if (! $result['credited'] && ! $result['order']->mail_sent) {
            $this->queueSuccessMail($result['order']);
        }

        Log::info('moyasar_wallet_credit_committed', [
            'payment_id' => $result['order']->id,
            'credited_now' => $result['credited'],
        ]);

        return $result['order'];
    }

    public function reconcile(Payment $order): string
    {
        if ($order->payment_method !== 'moyasar'
            || $order->type !== 'wallet_deposit'
            || ! $order->moyasar_invoice_id) {
            return 'ignored';
        }

        if ($order->wallet_credited && $order->status === Payment::STATUS_COMPLETED) {
            return 'completed';
        }

        $invoice = $this->checkInvoiceStatus($order->moyasar_invoice_id);
        $this->validateInvoiceIdentityAndMoney($order, $invoice);
        $invoiceStatus = strtolower((string) ($invoice['status'] ?? ''));

        if ($invoiceStatus === 'paid') {
            $payment = $this->successfulInvoicePayment($invoice);
            if (! $payment) {
                return 'pending';
            }

            $verifiedPayment = $this->checkStatus((string) $payment['id']);
            $this->creditWallet($order, $verifiedPayment, $invoice);

            return 'completed';
        }

        if (in_array($invoiceStatus, self::INVOICE_FAILED_STATUSES, true)) {
            $this->markFailed($order, $invoice);

            return 'failed';
        }

        return 'pending';
    }

    private function resolveLocalPayment(
        array $data,
        string $key,
        int $userId,
        string $amount,
        string $currency
    ): Payment {
        $amountMinor = $this->moneyToMinorUnits($amount);
        $expectedPoints = $this->amountToPoints($amount);

        try {
            return DB::transaction(function () use ($data, $key, $userId, $amount, $currency, $amountMinor, $expectedPoints) {
                $existing = Payment::where('idempotency_key', $key)->lockForUpdate()->first();
                if ($existing) {
                    return $existing;
                }

                return Payment::create([
                    'idempotency_key' => $key,
                    'user_id' => $userId,
                    'amount' => $amount,
                    'amount_minor' => $amountMinor,
                    'expected_points' => $expectedPoints,
                    'currency' => $currency,
                    'description' => ($data['description'] ?? null) ?: 'Wallet Deposit',
                    'payment_method' => 'moyasar',
                    'type' => 'wallet_deposit',
                    'status' => Payment::STATUS_PENDING,
                    'payment_status' => 'initiated',
                ]);
            }, 3);
        } catch (UniqueConstraintViolationException) {
            return Payment::where('idempotency_key', $key)->firstOrFail();
        }
    }

    private function assertSameIdempotentPayload(
        Payment $order,
        int $userId,
        string $amount,
        string $currency
    ): void {
        if ((int) $order->user_id !== $userId
            || $order->payment_method !== 'moyasar'
            || $order->type !== 'wallet_deposit'
            || $this->moneyToMinorUnits((string) $order->amount) !== $this->moneyToMinorUnits($amount)
            || strtoupper((string) $order->currency) !== $currency) {
            throw new DomainException('The idempotency key was already used with different payment data.');
        }
    }

    private function createHostedInvoice(Payment $order, string $locale): array
    {
        $locale = in_array($locale, ['ar', 'en', 'ru', 'fr', 'zh'], true) ? $locale : 'en';
        $expiryMinutes = max(5, min(1440, (int) config('moyasar.invoice_expiry_minutes', 30)));
        $description = mb_substr(
            (string) config('moyasar.merchant_name', config('app.name')).' - '.$order->description,
            0,
            255
        );

        $response = $this->request()->post('/invoices', [
            'amount' => $this->moneyToMinorUnits((string) $order->amount),
            'currency' => strtoupper((string) $order->currency),
            'description' => $description,
            'expired_at' => now()->addMinutes($expiryMinutes)->utc()->toIso8601String(),
            'success_url' => url("/{$locale}/wallet/charge/moyasar").'?deposit_id='.$order->id.'&provider=moyasar',
            'back_url' => url("/{$locale}/deposit/cancel"),
            'metadata' => $this->expectedMetadata($order),
        ]);

        return $this->jsonOrThrow($response, 'create Moyasar invoice', 201);
    }

    private function findExistingInvoice(Payment $order): ?array
    {
        $response = $this->get('/invoices', [
            'metadata[local_payment_id]' => (string) $order->id,
        ]);

        foreach ($response['invoices'] ?? [] as $invoice) {
            if (! is_array($invoice)) {
                continue;
            }

            try {
                $this->validateInvoiceIdentityAndMoney($order, $invoice);

                return $invoice;
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }

    private function syncInvoiceStateWithoutCrediting(Payment $order, array $invoice): Payment
    {
        $this->validateInvoiceIdentityAndMoney($order, $invoice);
        $status = strtolower((string) ($invoice['status'] ?? ''));

        return DB::transaction(function () use ($order, $invoice, $status) {
            $locked = Payment::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $updates = [
                'payment_status' => $status,
                'gateway_response' => $this->sanitizeGatewayResponse($invoice),
            ];

            if ($status === 'paid' && $locked->status === Payment::STATUS_PENDING) {
                $updates['status'] = Payment::STATUS_APPROVED;
            } elseif (in_array($status, self::INVOICE_FAILED_STATUSES, true)
                && ! $locked->wallet_credited
                && $locked->status !== Payment::STATUS_COMPLETED) {
                $updates['status'] = Payment::STATUS_FAILED;
            }

            $locked->update($updates);

            return $locked->fresh();
        }, 3);
    }

    private function findLocalPayment(array $verifiedPayment, array $verifiedInvoice): Payment
    {
        $invoiceId = (string) ($verifiedPayment['invoice_id'] ?? '');
        if ((string) ($verifiedInvoice['id'] ?? '') !== $invoiceId) {
            throw new RuntimeException('Moyasar invoice ID mismatch.');
        }

        $metadata = $this->invoiceMetadata($verifiedPayment, $verifiedInvoice);
        $localPaymentId = filter_var(
            $metadata['local_payment_id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        $query = Payment::query()
            ->where('payment_method', 'moyasar')
            ->where('type', 'wallet_deposit')
            ->where('moyasar_invoice_id', $invoiceId);

        if ($localPaymentId !== false) {
            $query->whereKey((int) $localPaymentId);
        }

        $order = $query->first();
        if (! $order) {
            throw new RuntimeException('Local Moyasar wallet payment was not found.');
        }

        return $order;
    }

    private function validateSuccessfulPayment(
        Payment $order,
        array $verifiedPayment,
        array $verifiedInvoice
    ): void {
        $status = strtolower((string) ($verifiedPayment['status'] ?? ''));
        if (! in_array($status, self::SUCCESS_STATUSES, true)) {
            $this->validationFailure($order, 'payment_status_not_successful');
        }
        if ($order->payment_method !== 'moyasar'
            || $order->type !== 'wallet_deposit'
            || ! $order->user_id
            || ! $order->user()->exists()) {
            $this->validationFailure($order, 'invalid_wallet_payment_owner_or_type');
        }

        $paymentId = (string) ($verifiedPayment['id'] ?? '');
        if (! $this->validGatewayId($paymentId)) {
            $this->validationFailure($order, 'invalid_moyasar_payment_id');
        }
        if ((string) ($verifiedPayment['invoice_id'] ?? '') !== $order->moyasar_invoice_id) {
            $this->validationFailure($order, 'moyasar_invoice_id_mismatch');
        }

        $this->validateMoney($order, $verifiedPayment);
        $this->validateInvoiceIdentityAndMoney($order, $verifiedInvoice);

        $invoicePaymentIds = collect($verifiedInvoice['payments'] ?? [])
            ->filter(fn ($payment) => is_array($payment))
            ->pluck('id')
            ->map(fn ($id) => (string) $id);
        if ($invoicePaymentIds->isNotEmpty() && ! $invoicePaymentIds->contains($paymentId)) {
            $this->validationFailure($order, 'payment_not_attached_to_invoice');
        }
    }

    private function validateInvoiceIdentityAndMoney(Payment $order, array $invoice): void
    {
        $invoiceId = (string) ($invoice['id'] ?? '');
        if (! $this->validGatewayId($invoiceId)) {
            $this->validationFailure($order, 'invalid_moyasar_invoice_id');
        }
        if ($order->moyasar_invoice_id && $order->moyasar_invoice_id !== $invoiceId) {
            $this->validationFailure($order, 'moyasar_invoice_id_mismatch');
        }

        $this->validateMoney($order, $invoice);
        $metadata = is_array($invoice['metadata'] ?? null) ? $invoice['metadata'] : [];
        $expected = $this->expectedMetadata($order);

        foreach ($expected as $key => $value) {
            $actual = isset($metadata[$key]) ? (string) $metadata[$key] : '';
            if ($actual === '' || ! hash_equals($value, $actual)) {
                $this->validationFailure($order, 'invoice_metadata_'.$key.'_mismatch');
            }
        }
    }

    private function validateMoney(Payment $order, array $resource): void
    {
        $amount = $resource['amount'] ?? null;
        $currency = strtoupper((string) ($resource['currency'] ?? ''));

        if (! is_int($amount) && ! (is_string($amount) && ctype_digit($amount))) {
            $this->validationFailure($order, 'amount_missing_or_invalid');
        }
        if ((int) $amount !== $this->moneyToMinorUnits((string) $order->amount)) {
            $this->validationFailure($order, 'amount_mismatch');
        }
        if ($currency === '' || $currency !== strtoupper((string) $order->currency)) {
            $this->validationFailure($order, 'currency_mismatch');
        }
    }

    private function expectedMetadata(Payment $order): array
    {
        $metadata = [
            'local_payment_id' => (string) $order->id,
            'payment_type' => 'wallet_deposit',
            'idempotency_key' => (string) $order->idempotency_key,
        ];

        $merchantId = $this->merchantId();
        if ($merchantId !== '') {
            $metadata['merchant_id'] = $merchantId;
        }

        return $metadata;
    }

    private function merchantId(): string
    {
        return trim((string) config('services.moyasar.merchant_id', ''));
    }

    private function invoiceMetadata(array $payment, array $invoice): array
    {
        if (is_array($payment['metadata'] ?? null) && $payment['metadata'] !== []) {
            return $payment['metadata'];
        }

        return is_array($invoice['metadata'] ?? null) ? $invoice['metadata'] : [];
    }

    private function markApproved(Payment $order, array $resource): Payment
    {
        return DB::transaction(function () use ($order, $resource) {
            $locked = Payment::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === Payment::STATUS_PENDING) {
                $locked->status = Payment::STATUS_APPROVED;
            }
            $locked->payment_status = strtolower((string) ($resource['status'] ?? 'authorized'));
            $locked->gateway_response = $this->sanitizeGatewayResponse($resource);
            $locked->save();

            return $locked->fresh();
        }, 3);
    }

    private function storePendingStatus(Payment $order, array $resource): Payment
    {
        return DB::transaction(function () use ($order, $resource) {
            $locked = Payment::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (! $locked->wallet_credited && $locked->status !== Payment::STATUS_COMPLETED) {
                $locked->update([
                    'payment_status' => strtolower((string) ($resource['status'] ?? 'initiated')),
                    'gateway_response' => $this->sanitizeGatewayResponse($resource),
                ]);
            }

            return $locked->fresh();
        }, 3);
    }

    private function markFailed(Payment $order, array $resource): Payment
    {
        return DB::transaction(function () use ($order, $resource) {
            $locked = Payment::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $status = strtolower((string) ($resource['status'] ?? 'failed'));

            if ($locked->wallet_credited || $locked->status === Payment::STATUS_COMPLETED) {
                Log::critical('moyasar_terminal_event_after_wallet_credit', [
                    'payment_id' => $locked->id,
                    'moyasar_status' => $status,
                ]);
                $locked->update([
                    'payment_status' => $status,
                    'gateway_response' => $this->sanitizeGatewayResponse($resource),
                ]);

                return $locked->fresh();
            }

            $shouldQueueMail = $locked->status !== Payment::STATUS_FAILED || ! $locked->mail_sent;
            $updates = [
                'status' => Payment::STATUS_FAILED,
                'payment_status' => $status,
                'gateway_response' => $this->sanitizeGatewayResponse($resource),
            ];
            if ($shouldQueueMail) {
                $updates['mail_sent'] = false;
            }
            $locked->update($updates);

            if ($shouldQueueMail) {
                DB::afterCommit(fn () => $this->queueFailedMail($locked));
            }

            return $locked->fresh();
        }, 3);
    }

    private function successfulInvoicePayment(array $invoice): ?array
    {
        $payments = array_values(array_filter(
            $invoice['payments'] ?? [],
            fn ($payment) => is_array($payment)
                && in_array(strtolower((string) ($payment['status'] ?? '')), self::SUCCESS_STATUSES, true)
                && $this->validGatewayId((string) ($payment['id'] ?? ''))
        ));

        usort($payments, fn (array $left, array $right) => strcmp(
            (string) ($right['updated_at'] ?? $right['created_at'] ?? ''),
            (string) ($left['updated_at'] ?? $left['created_at'] ?? '')
        ));

        return $payments[0] ?? null;
    }

    private function invoiceUrl(array $invoice): string
    {
        $url = $invoice['url'] ?? null;
        if (! is_string($url)
            || ! str_starts_with($url, 'https://')
            || filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new RuntimeException('Moyasar hosted payment URL is missing or invalid.');
        }

        return $url;
    }

    private function get(string $path, array $query = []): array
    {
        $attempts = max(1, min(5, (int) config('moyasar.get_retries', 2) + 1));
        $response = $this->request()
            ->retry($attempts, 250, null, false)
            ->get($path, $query);

        return $this->jsonOrThrow($response, 'fetch Moyasar resource');
    }

    private function request(): PendingRequest
    {
        $this->assertConfiguration();

        return Http::baseUrl(rtrim((string) config('moyasar.api_url'), '/'))
            ->withBasicAuth($this->secretKey(), '')
            ->acceptJson()
            ->asJson()
            ->connectTimeout(max(1, (int) config('moyasar.connect_timeout', 5)))
            ->timeout(max(2, (int) config('moyasar.timeout', 20)));
    }

    private function jsonOrThrow(Response $response, string $operation, int $expectedStatus = 200): array
    {
        if ($response->status() !== $expectedStatus) {
            Log::error('moyasar_api_request_failed', [
                'operation' => $operation,
                'status' => $response->status(),
                'request_id' => $response->header('Request-Id'),
            ]);
            $response->throw();
        }

        $data = $response->json();
        if (! is_array($data)) {
            throw new RuntimeException('Moyasar API returned invalid JSON while attempting to '.$operation.'.');
        }

        return $data;
    }

    private function secretKey(): string
    {
        $mode = $this->mode();

        $key = (string) config("moyasar.{$mode}.secret_key", '');
        $expectedPrefix = $mode === 'live' ? 'sk_live_' : 'sk_test_';
        if ($key === '') {
            throw new RuntimeException("Moyasar {$mode} secret key is not configured.");
        }
        if (! str_starts_with($key, $expectedPrefix)) {
            throw new RuntimeException("Moyasar {$mode} secret key does not match the current mode.");
        }

        return $key;
    }

    private function mode(): string
    {
        $mode = strtolower((string) config('moyasar.mode', 'test'));
        if (! in_array($mode, ['test', 'live'], true)) {
            throw new RuntimeException('MOYASAR_MODE must be test or live.');
        }

        return $mode;
    }

    private function webhookSecret(): string
    {
        $servicesSecret = config('services.moyasar.webhook_secret');
        if (is_string($servicesSecret) && $servicesSecret !== '') {
            return $servicesSecret;
        }

        return (string) config('moyasar.webhook_secret', '');
    }

    private function assertConfiguration(): void
    {
        $apiUrl = rtrim((string) config('moyasar.api_url', ''), '/');
        if (! str_starts_with($apiUrl, 'https://')
            || filter_var($apiUrl, FILTER_VALIDATE_URL) === false) {
            throw new RuntimeException('MOYASAR_API_URL must be a valid HTTPS URL.');
        }
        if (strtoupper((string) config('moyasar.currency', 'SAR')) !== 'SAR') {
            throw new RuntimeException('This wallet integration requires MOYASAR_CURRENCY=SAR.');
        }
        $this->secretKey();
        $this->expectedPointsPerSar();
    }

    private function paidAt(array $payment): CarbonImmutable
    {
        $timestamp = $payment['captured_at'] ?? $payment['updated_at'] ?? null;
        if (is_string($timestamp) && $timestamp !== '') {
            try {
                return CarbonImmutable::parse($timestamp);
            } catch (Throwable) {
                // Use server receipt time when Moyasar sends an invalid timestamp.
            }
        }

        return CarbonImmutable::now();
    }

    private function sanitizeGatewayResponse(array $resource): array
    {
        unset($resource['secret_token']);
        if (isset($resource['source']) && is_array($resource['source'])) {
            unset($resource['source']['token']);
        }
        if (isset($resource['payments']) && is_array($resource['payments'])) {
            foreach ($resource['payments'] as &$payment) {
                if (is_array($payment) && isset($payment['source']) && is_array($payment['source'])) {
                    unset($payment['source']['token']);
                }
            }
            unset($payment);
        }

        return $resource;
    }

    private function normalizeMoney(string $value): string
    {
        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) {
            throw new DomainException('Amount must have no more than two decimal places.');
        }
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $whole = ltrim($whole, '0');

        return ($whole === '' ? '0' : $whole).'.'.str_pad($fraction, 2, '0');
    }

    private function moneyToMinorUnits(string $value): int
    {
        [$whole, $fraction] = explode('.', $this->normalizeMoney($value));

        return ((int) $whole * 100) + (int) $fraction;
    }

    private function amountToPoints(string $amount): int
    {
        $minorUnits = $this->moneyToMinorUnits($amount);
        $pointsPerSar = $this->expectedPointsPerSar();
        $pointsPerHalala = intdiv($pointsPerSar, 100);

        if ($minorUnits > intdiv(PHP_INT_MAX, $pointsPerHalala)) {
            throw new RuntimeException('Moyasar deposit points overflow.');
        }

        return $minorUnits * $pointsPerHalala;
    }

    private function expectedPointsPerSar(): int
    {
        $pointsPerSar = (int) config('moyasar.points_per_sar', 1_000_000);
        if ($pointsPerSar <= 0 || $pointsPerSar % 100 !== 0) {
            throw new RuntimeException('MOYASAR_POINTS_PER_SAR must be positive and divisible by 100.');
        }

        return $pointsPerSar;
    }

    private function validGatewayId(string $id): bool
    {
        return $id !== ''
            && strlen($id) <= 64
            && preg_match('/^[A-Za-z0-9_-]+$/', $id) === 1;
    }

    private function flushWalletCache(Payment $order): void
    {
        try {
            Cache::tags(['wallet', 'transactions', "user_{$order->user_id}"])->flush();
        } catch (Throwable $e) {
            Log::warning('moyasar_wallet_cache_flush_failed', [
                'payment_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function queueSuccessMail(Payment $order): void
    {
        try {
            SendDepositSuccessMailJob::dispatch($order->id)->afterCommit();
            Log::info('email_queued', ['payment_id' => $order->id, 'kind' => 'moyasar_deposit_success']);
        } catch (Throwable $e) {
            Log::error('email_failed', [
                'payment_id' => $order->id,
                'kind' => 'moyasar_deposit_success_queue',
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function queueFailedMail(Payment $order): void
    {
        try {
            SendDepositFailedMailJob::dispatch($order->id)->afterCommit();
            Log::info('email_queued', ['payment_id' => $order->id, 'kind' => 'moyasar_deposit_failed']);
        } catch (Throwable $e) {
            Log::error('email_failed', [
                'payment_id' => $order->id,
                'kind' => 'moyasar_deposit_failed_queue',
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function validationFailure(Payment $order, string $reason): never
    {
        Log::warning('moyasar_payment_validation_failed', [
            'payment_id' => $order->id,
            'reason' => $reason,
        ]);

        throw new RuntimeException('Moyasar payment validation failed: '.$reason);
    }
}
