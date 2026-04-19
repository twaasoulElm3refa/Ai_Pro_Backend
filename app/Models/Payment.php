<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use SoftDeletes;

    protected $table = 'payments';
    protected $guarded = [];
    public function user()
    {
        return $this->belongsTo(User::class,'user_id');
    }

    public function walletTransaction()
    {
        return $this->hasMany(WalletTransaction::class,'payment_id');
    }
}
