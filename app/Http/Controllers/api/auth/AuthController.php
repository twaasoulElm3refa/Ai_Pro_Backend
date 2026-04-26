<?php

namespace App\Http\Controllers\api\auth;

use App\Http\Controllers\concerns\authApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\loginRequest;
use App\Http\Resources\userResource;
use App\Mail\OtpMail;
use App\Models\User;
use App\Repository\Register\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AuthController extends Controller
{
    use authApiResponse;

    public function __construct(private AuthService $authService) {}

    // ─────────────────────────────────────────
    //  Login – Entry Point
    // ─────────────────────────────────────────
    public function login(LoginRequest $request)
    {
        try {
            $user = User::where('email', $request->email)->first();

            if (! $user || ! Hash::check($request->password, $user->password)) {
                return $this->unauthorized('Invalid credentials.');
            }

            if (! $user->is_active) {
                return $this->forbidden('Account is disabled.');
            }
            if (! $user->is_verified) {
                $this->authService->sendOtp($user->email);

                return $this->success([
                    'requires_verification' => true,
                    'email'                 => $user->email,
                ], 'Account not verified. OTP sent to your email.');
            }
            $user->update(['last_seen' => now()]);
            $token = $user->createToken('rag-token')->plainTextToken;
            return $this->success([
                'user'  => new userResource($user),
                'token' => $token,
            ], 'Logged in successfully.');

        } catch (\Exception $e) {
            Log::error('Login Error: ' . $e->getMessage(), [
                'email' => $request->email ?? null,
            ]);
            return $this->error('Something went wrong. Please try again.', 500);
        }
    }

    // ─────────────────────────────────────────
    //  Verify OTP عند Login
    // ─────────────────────────────────────────
    public function verifyLoginOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp'   => 'required|digits:6',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return $this->error('User not found.', 404);
        }

        if (! $this->authService->verifyOtp($request->email, $request->otp)) {
            return $this->error('Invalid or expired OTP.', 400);
        }

        // ── تحديث الحساب كـ verified
        $user->update([
            'is_verified'       => true,
            'email_verified_at' => now(),
            'last_seen'         => now(),
        ]);

        $this->authService->clearOtp($request->email);

        $token = $user->createToken('rag-token')->plainTextToken;

        return $this->success([
            'user'  => new userResource($user),
            'token' => $token,
        ], 'Email verified. Logged in successfully.');
    }

    // ─────────────────────────────────────────
    //  Resend OTP
    // ─────────────────────────────────────────
    public function resendOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $user = User::where('email', $request->email)->first();

        if ($user->is_verified) {
            return $this->error('Account already verified.', 400);
        }

        $this->authService->sendOtp($request->email);

        return $this->success([], 'OTP resent successfully.');
    }

    // ─────────────────────────────────────────
    //  Logout
    // ─────────────────────────────────────────
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return $this->success([], 'Logged out successfully.');
    }

    // ─────────────────────────────────────────
    //  Forgot Password
    // ─────────────────────────────────────────
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        // دايما نرجع نفس الرسالة للأمان
        if (! $user) {
            return response()->json(['message' => 'لو الإيميل موجود هيوصلك كود']);
        }

        $otp = rand(100000, 999999);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            ['token' => Hash::make($otp), 'created_at' => now()]
        );

        Mail::to($user->email)->send(new OtpMail($otp));

        return response()->json(['message' => 'تم إرسال كود التحقق على الإيميل']);
    }

    // ─────────────────────────────────────────
    //  Reset Password
    // ─────────────────────────────────────────
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'otp'      => 'required',
            'password' => 'required|confirmed|min:8',
        ]);

        $record = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (! $record || ! Hash::check($request->otp, $record->token)) {
            return response()->json(['message' => 'الكود غير صحيح'], 400);
        }

        User::where('email', $request->email)->update([
            'password' => Hash::make($request->password),
        ]);

        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json(['message' => 'تم تغيير كلمة المرور بنجاح']);
    }
}
