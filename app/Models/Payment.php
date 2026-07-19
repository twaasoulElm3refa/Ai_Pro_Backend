<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payment extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $table = 'payments';
    protected $guarded = [];
    protected $hidden = [
        'gateway_response',
        'payer_email',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'gateway_response' => 'array',
            'mail_sent' => 'boolean',
            'wallet_credited' => 'boolean',
            'paid_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function walletTransaction(): HasOne
    {
        return $this->hasOne(WalletTransaction::class, 'payment_id');
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED
            && ($this->type !== 'wallet_deposit' || $this->wallet_credited);
    }
}
