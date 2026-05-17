<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FreeCreditClaim extends Model
{
    protected $table = 'free_credit_claims';
    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
