<?php

namespace App\Http\Controllers\api\admin\auth;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AdminAuthController extends Controller
{
    use ApiResponse;

    public function login(Request $request)
    {
        try {
            $request->all();
            $user = User::where('email', $request->email)->first();

            if (! $user || ! Hash::check($request->password, $user->password)) {
                return $this->unauthorized('Invalid credentials.');
            }
            if($user->role != 'admin'){
                return $this->unauthorized('You are not an admin.');
            }

            $user->update(['last_seen' => now()]);

            $token = $user->createToken('rag-token')->plainTextToken;

            return $this->success([
                'user' => new UserResource($user),
                'token' => $token,
            ], 'Logged in successfully.');

        } catch (\Exception $e) {
            Log::error('Login Error: '.$e->getMessage(), [
                'email' => $request->email ?? null,
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error('Something went wrong during login. Please try again later.', 500);
        }
    }
}
