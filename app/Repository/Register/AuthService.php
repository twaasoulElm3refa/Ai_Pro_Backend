<?php

namespace App\Repository\Register;

use App\Mail\OtpMail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class AuthService
{
    public function sendOtp($email)
    {
        $otp = random_int(100000, 999999);

        Cache::put('otp_'.$email, $otp, now()->addMinutes(5));

        Mail::to($email)->send(new OtpMail($otp));
    }

    public function verifyOtp($email, $otp)
    {
        $cachedOtp = Cache::get('otp_'.$email);

        if (! $cachedOtp || $cachedOtp != $otp) {
            return false;
        }

        Cache::put('verified_'.$email, true, now()->addMinutes(10));

        return true;
    }

    public function isVerified($email)
    {
        return Cache::get('verified_'.$email);
    }

    public function clearOtp($email)
    {
        Cache::forget('otp_'.$email);
        Cache::forget('verified_'.$email);
    }
}
