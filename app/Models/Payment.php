<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $table = 'payments';
    protected $guarded = [];
    protected $hidden = [
        'gateway_response',
    ];

    public function user()
    {
        return $this->belongsTo(User::class,'user_id');
    }

    public function walletTransaction()
    {
        return $this->hasMany(WalletTransaction::class,'payment_id');
    }
}
