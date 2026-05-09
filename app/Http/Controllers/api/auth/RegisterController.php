<?php

namespace App\Http\Controllers\api\auth;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\userResource;
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
        private UserRepository $userRepository
    ) {}

    public function register(RegisterRequest $request)
    {
        try {
            $user = DB::transaction(function () use ($request) {
                $data = $request->validated();

                $user = $this->userRepository->create([
                    'name'       => $data['name'],
                    'email'      => $data['email'],
                    'phone'      => $data['phone'] ?? null,
                    'password'   => Hash::make($data['password']),
                    'role'       => 'user',
                    'is_active'  => true,
                    'is_verified' => false,
                    'last_seen'  => now(),
                ]);

                if ($request->hasFile('image')) {
                    $user->image = $request->file('image')->store('users', 'public');
                    $user->save();
                }

                return $user;
            });

            return $this->success([
                'user' => new userResource($user),
            ], 'Account created. Please login to verify your email.');

        } catch (Throwable $e) {
            Log::error('Register Error', ['message' => $e->getMessage()]);
            return $this->error('Registration failed.', 500);
        }
    }
}
