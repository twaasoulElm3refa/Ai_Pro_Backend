<?php

namespace App\Http\Controllers\api\auth;

use App\Http\Controllers\concerns\authApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\loginRequest;
use App\Http\Resources\userResource;
use App\Mail\OtpMail;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Throwable;

class AuthController extends Controller
{
    use authApiResponse;

    public function login(LoginRequest $request)
    {
        try {
            $request->validated();
            $user = User::where('email', $request->email)->first();

            if (! $user || ! Hash::check($request->password, $user->password)) {
                return $this->unauthorized('Invalid credentials.');
            }

            if (! $user->is_active) {
                return $this->forbidden('Account is disabled.');
            }

            $user->update(['last_seen' => now()]);

            $token = $user->createToken('rag-token')->plainTextToken;

            return $this->success([
                'user' => new userResource($user),
                'token' => $token,
            ], 'Logged in successfully.');

        } catch (\Exception $e) {
            \Log::error('Login Error: '.$e->getMessage(), [
                'email' => $request->email ?? null,
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error('Something went wrong during login. Please try again later.', 500);
        }
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success([], 'Logged out successfully.');
    }

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return response()->json([
                'message' => 'لو الإيميل موجود هيوصلك كود',
            ]);
        }

        $otp = rand(100000, 999999);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            [
                'token' => Hash::make($otp),
                'created_at' => now(),
            ]
        );

        Mail::to($user->email)->send(new OtpMail($otp));

        return response()->json([
            'message' => 'تم إرسال كود التحقق على الإيميل',
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required',
            'password' => 'required|confirmed|min:8',
        ]);

        $record = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (! $record || ! Hash::check($request->otp, $record->token)) {
            return response()->json([
                'message' => 'الكود غير صحيح',
            ], 400);
        }

        User::where('email', $request->email)->update([
            'password' => Hash::make($request->password),
        ]);

        DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->delete();

        return response()->json([
            'message' => 'تم تغيير كلمة المرور بنجاح',
        ]);
    }

    public function clearProfileCache($userId = null)
    {
        $userId = $userId ?? auth()->id();

        if (! $userId) {
            return;
        }

        Cache::forget("user_profile_{$userId}");
    }
}
