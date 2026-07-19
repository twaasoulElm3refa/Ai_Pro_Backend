<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WalletTransaction extends Model
{
    use SoftDeletes;

    protected $table = 'wallet_transactions';
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'balance_before' => 'integer',
            'balance_after' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class,'user_id');
    }

    public function wallet()
    {
        return $this->belongsTo(Wallet::class,'wallet_id');
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class,'payment_id');
    }

}
