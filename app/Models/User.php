<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;


class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable ,SoftDeletes ,HasApiTokens;

    protected $guarded = [];
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function conversation()
    {
        return $this->hasMany(Conversation::class,'user_id');
    }

    public function wallet()
    {
        return $this->hasOne(Wallet::class,'user_id');
    }

    public function payment()
    {
        return $this->hasMany(Payment::class,'user_id');
    }

    public function walletTransaction()
    {
        return $this->hasMany(WalletTransaction::class,'user_id');
    }
}
