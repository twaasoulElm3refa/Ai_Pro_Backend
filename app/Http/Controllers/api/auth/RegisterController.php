<?php

namespace App\Http\Controllers\api\auth;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\registerRequest;
use App\Http\Resources\UserResource;
use App\Repository\Register\AuthService;
use App\Repository\Register\UserRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Throwable;

class RegisterController extends Controller
{
    use ApiResponse;

    public function __construct(
        private AuthService $authService,
        private UserRepository $userRepository
    ) {}

    public function sendOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|unique:users,email',
        ]);

        $this->authService->sendOtp($request->email);

        return $this->success([], 'OTP sent successfully');
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|digits:6',
        ]);

        if (! $this->authService->verifyOtp($request->email, $request->otp)) {
            return $this->error('Invalid or expired OTP', 400);
        }

        return $this->success([], 'OTP verified');
    }

    public function register(registerRequest $request)
    {
        try {
            if (! $this->authService->isVerified($request->email)) {
                return $this->error('OTP not verified', 403);
            }

            $user = DB::transaction(function () use ($request) {
                $data = $request->validated();
                return $this->userRepository->create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'image' => $data['image'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'password' => Hash::make($data['password']),
                    'role' => 'user',
                    'is_active' => true,
                    'is_verified' => true,
                    'email_verified_at' => now(),
                    'last_seen' => now(),
                ]);
            });

            if ($request->hasFile('image')) {
                $user->image = $request->image->store('users', 'public');
                $user->save();
            }

            $this->authService->clearOtp($request->email);

            $token = $user->createToken('rag-token')->plainTextToken;

            return $this->success([
                'token' => $token,
                'user' => new UserResource($user),
            ], 'Registered successfully.');

        } catch (Throwable $e) {
            Log::error('Register Error', [
                'message' => $e->getMessage(),
            ]);

            return $this->error('Registration failed.', 500);
        }
    }
}
